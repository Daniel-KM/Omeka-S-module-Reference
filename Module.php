<?php declare(strict_types=1);

namespace Reference;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Omeka\Settings\SettingsInterface;
use Reference\Entity\Metadata;

/**
 * Reference
 *
 * Allows to serve an alphabetized and a hierarchical page of links to searches
 * for all resources classes and properties of all resources of Omeka S.
 *
 * @copyright Daniel Berthereau, 2017-2021
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        $this->getServiceLocator()->get('Omeka\Acl')
            ->allow(
                null,
                [\Reference\Controller\Site\ReferenceController::class],
                ['browse', 'list']
            )
            ->allow(
                null,
                [\Reference\Controller\ApiController::class]
            );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );

        // The job should be run when template title and description are updated.

        // Only prePersist can be used, because other entity events are called
        // during flush. But prePersist is not triggered for now, so use postPersist
        // and postUpdate with insert through sql.
        // But, unlike postUpdate, the values have no id yet during postPersist.
        // So use common api.create.post for each resource too.
        // Deletion is automatically managed via database "on delete cascade"
        // in all cases.
        $adapters = [
            \Omeka\Api\Adapter\ItemAdapter::class,
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            \Omeka\Api\Adapter\MediaAdapter::class,
            \Annotate\Api\Adapter\AnnotationAdapter::class,
            \Article\Api\Adapter\ArticleAdapter::class,
        ];
        foreach ($adapters as $adapter) {
            $sharedEventManager->attach(
                $adapter,
                'api.create.post',
                [$this, 'updateReferenceMetadataApiPost']
            );
            // $sharedEventManager->attach(
            //     $adapter,
            //     'api.update.post',
            //     [$this, 'updateReferenceMetadataApiPost']
            // );
        }
        // $sharedEventManager->attach(
        //     \Omeka\Entity\Resource::class,
        //     'entity.persist.post',
        //     [$this, 'updateReferenceMetadata']
        // );
        $sharedEventManager->attach(
            \Omeka\Entity\Resource::class,
            'entity.update.post',
            [$this, 'updateReferenceMetadata']
        );
    }

    protected function initDataToPopulate(SettingsInterface $settings, string $settingsType, $id = null, iterable $values = []): bool
    {
        // Check site settings , because array options cannot be set by default
        // automatically.
        if ($settingsType === 'site_settings') {
            $exist = $settings->get('reference_resource_name');
            if (is_null($exist)) {
                $config = $this->getConfig();
                $settings->set('reference_options', $config['reference']['site_settings']['reference_options']);
                $settings->set('reference_slugs', $config['reference']['site_settings']['reference_slugs']);
            }
        }
        return parent::initDataToPopulate($settings, $settingsType, $id, $values);
    }

    public function updateReferenceMetadataApiPost(Event $event): void
    {
        /** @var \Omeka\Entity\Resource $resource */
        $resource = $event->getParam('response')->getContent();
        $referenceMetadatas = $this->updateReferenceMetadataResource($resource, $event->getName());
        if (!count($referenceMetadatas)) {
            return;
        }

        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        foreach ($referenceMetadatas as $metadata) {
            $entityManager->persist($metadata);
        }
        $entityManager->flush();
    }

    public function updateReferenceMetadata(Event $event): void
    {
        /** @var \Omeka\Entity\Resource $resource */
        $resource = $event->getTarget();
        $referenceMetadatas = $this->updateReferenceMetadataResource($resource, $event->getName());
        if (!count($referenceMetadatas)) {
            return;
        }

        // When a post flush event is used, flush is not available to avoid loop,
        // so use only sql.
        $parameters = [];
        $sql = "INSERT INTO `reference_metadata` (`resource_id`, `value_id`, `field`, `lang`, `is_public`, `text`) VALUES\n";
        foreach ($referenceMetadatas as $key => $metadata) {
            $sql .= "(:resource_id_$key, :value_id_$key, :field_$key, :lang_$key, :is_public_$key, :text_$key),\n";
            $parameters["resource_id_$key"] = $metadata->getResource()->getId();
            $parameters["value_id_$key"] = $metadata->getValue()->getId();
            $parameters["field_$key"] = $metadata->getField();
            $parameters["lang_$key"] = $metadata->getLang();
            $parameters["is_public_$key"] = $metadata->getIsPublic();
            $parameters["text_$key"] = $metadata->getText();
        }
        $sql = trim($sql, " \n,");
        $this->getServiceLocator()->get('Omeka\Connection')->executeStatement($sql, $parameters);
    }

    protected function updateReferenceMetadataResource(\Omeka\Entity\Resource $resource, string $eventName): array
    {
        // Remove all existing reference metadata of the resource.
        if (!in_array($eventName, ['api.create.post', 'api.persist.post'])) {
            $this->getServiceLocator()->get('Omeka\Connection')->executeStatement(
                'DELETE FROM `reference_metadata` WHERE `resource_id` = :resource',
                ['resource' => $resource->getId()]
            );
        }

        // Create new reference metadata.

        $referenceMetadatas = [];

        // Add the core main fields (title and description).
        $template = $resource->getResourceTemplate();
        if ($template) {
            $titlePropertyId = $template->getTitleProperty();
            $titlePropertyId = $titlePropertyId ? $titlePropertyId->getId() : 1;
            $descriptionPropertyId = $template->getDescriptionProperty();
            $descriptionPropertyId = $descriptionPropertyId ? $descriptionPropertyId->getId() : 4;
        } else {
            $titlePropertyId = 1;
            $descriptionPropertyId = 4;
        }
        $coreFields = [
            ['field' => 'display_title', 'property_id' => $titlePropertyId],
            ['field' => 'display_description', 'property_id' => $descriptionPropertyId],
        ];
        // Unlike standard values, only first value in each language is
        // stored, but a value resource can have multiple languages.
        foreach ($coreFields as $fieldData) {
            $languages = [];
            $privateLanguages = [];
            foreach ($resource->getValues() as $value) {
                if ($value->getProperty()->getId() !== $fieldData['property_id']) {
                    continue;
                }
                $isPublic = $value->getIsPublic();
                $langTexts = $this->getValueResourceLangTexts($value, $isPublic);
                $langTexts = $isPublic
                    ? array_diff_key($langTexts['public'], $languages)
                    : array_diff_key($langTexts['private'], $privateLanguages);
                foreach ($langTexts as $lang => $text) {
                    $metadata = new \Reference\Entity\Metadata();
                    $metadata
                        ->setResource($resource)
                        ->setValue($value)
                        ->setField($fieldData['field'])
                        ->setLang($lang)
                        ->setIsPublic($isPublic)
                        ->setText($text);
                    $referenceMetadatas[] = $metadata;
                    if ($isPublic) {
                        $languages[$lang] = true;
                    }
                    $privateLanguages[$lang] = true;
                }
            }
        }

        foreach ($resource->getValues() as $value) {
            $property = $value->getProperty();
            $field = $property->getVocabulary()->getPrefix() . ':' . $property->getLocalName();
            $isPublic = $value->getIsPublic();
            $langTexts = $this->getValueResourceLangTexts($value, $isPublic);
            $langTexts = $isPublic ? $langTexts['public'] : $langTexts['private'];
            foreach ($langTexts as $lang => $text) {
                $metadata = new \Reference\Entity\Metadata();
                $metadata
                    ->setResource($resource)
                    ->setValue($value)
                    ->setField($field)
                    ->setLang($lang)
                    ->setIsPublic($isPublic)
                    ->setText($text);
                $referenceMetadatas[] = $metadata;
            }
        }

        return $referenceMetadatas;
    }

    /**
     * Get the text to use for a value for the value language or multiple languages.
     *
     * If the source value is public, only public content will be returned.
     *
     * The function is recursive to get the translated title of linked resource.
     */
    protected function getValueResourceLangTexts(
        \Omeka\Entity\Value $value,
        bool $isPublic,
        int $count = 0,
        array $langTexts = ['public' => [], 'private' => []]
    ): array {
        $isPublicValue = $value->getIsPublic();
        if ($isPublic && !$isPublicValue) {
            return $langTexts;
        }

        $valueResource = $value->getValueResource();
        if (!$valueResource) {
            // The value can be a translated literal or a uri, but only
            // the label is stored in that case.
            // TODO Add a trigger to manage translated uris.
            $text = (string) $value->getValue();
            if (!strlen($text)) {
                $text = $value->getUri();
                // Normally never here.
                if (!strlen($text)) {
                    return $langTexts;
                }
            }

            // Keep the first translation.
            $lang = (string) $value->getLang();
            if ($isPublic && $isPublicValue) {
                if (!isset($langTexts['public'][$lang])) {
                    $langTexts['public'][$lang] = $text;
                }
                if (!isset($langTexts['private'][$lang])) {
                    $langTexts['private'][$lang] = $text;
                }
            } elseif (!$isPublic) {
                if (!isset($langTexts['private'][$lang])) {
                    $langTexts['private'][$lang] = $text;
                }
            }

            return $langTexts;
        }

        if ($isPublic && !$valueResource->isPublic()) {
            return $langTexts;
        }

        ++$count;

        // Get the property title of the valueResource.
        $template = $valueResource->getResourceTemplate();
        $titlePropertyId = $template && ($property = $template->getTitleProperty())
            ? $property->getId()
            : 1;
        foreach ($valueResource->getValues() as $subValue) {
            if ($subValue->getProperty()->getId() !== $titlePropertyId) {
                continue;
            }
            $isPublicSubValue = $subValue->getIsPublic();
            if ($isPublic && !$isPublicSubValue) {
                continue;
            }
            $subValueResource = $subValue->getValueResource();
            if ($subValueResource && $count > 10) {
                $this->getServiceLocator()->get('Omeka\Logger')->warn(sprintf(
                    'Resource #%d has a recursive title.', // @translate
                    // TODO Ideally, get initial source value id. Probably very rare anyway above one or two levels.
                    $value->getResource()->getId()
                ));
                $lang = '';
                $text = $subValueResource->getTitle();
                $isPublicSubValueResource = $isPublicSubValue && $subValueResource->isPublic();
                if ($isPublic && $isPublicSubValueResource) {
                    if (!isset($langTexts['public'][$lang])) {
                        $langTexts['public'][$lang] = $text;
                    }
                    if (!isset($langTexts['private'][$lang])) {
                        $langTexts['private'][$lang] = $text;
                    }
                } elseif (!$isPublic) {
                    if (!isset($langTexts['private'][$lang])) {
                        $langTexts['private'][$lang] = $text;
                    }
                }
            } else {
                $subLangTexts = $this->getValueResourceLangTexts($subValue, $isPublic, $count, $langTexts);
                $langTexts = [
                    'public' => $langTexts['public'] + $subLangTexts['public'],
                    'private' => $langTexts['private'] + $subLangTexts['private'],
                ];
            }
        }

        return $langTexts;
    }
}
