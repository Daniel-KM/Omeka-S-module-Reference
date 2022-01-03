<?php declare(strict_types=1);

namespace Reference\Mvc\Controller\Plugin;

use Laminas\Log\Logger;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Entity\Resource;
use Reference\Entity\Metadata as ReferenceMetadata;

class CurrentReferenceMetadata extends AbstractPlugin
{
    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get the references metadata of a resource from it.
     *
     * @internal
     * @return \Reference\Entity\Metadata[]
     */
    public function __invoke(Resource $resource): array
    {
        static $templateDataIds = [];

        $referenceMetadatas = [];

        // Add the core main fields (title and description).
        $template = $resource->getResourceTemplate();
        if ($template) {
            $templateId = $template->getId();
            if (!isset($templateDataIds[$templateId])) {
                $propertyId = $template->getTitleProperty();
                $templateDataIds[$templateId]['title'] = $propertyId ? $propertyId->getId() : 1;
                $propertyId = $template->getDescriptionProperty();
                $templateDataIds[$templateId]['description'] = $propertyId ? $propertyId->getId() : 4;
            }
            $titlePropertyId = $templateDataIds[$templateId]['title'];
            $descriptionPropertyId = $templateDataIds[$templateId]['description'];
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
                $langTexts = array_diff_key($langTexts, $isPublic ? $languages : $privateLanguages);
                foreach ($langTexts as $lang => $text) {
                    $metadata = new ReferenceMetadata();
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
            foreach ($langTexts as $lang => $text) {
                $metadata = new ReferenceMetadata();
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
     * The function is recursive to get the translated title of linked resources.
     */
    protected function getValueResourceLangTexts(
        \Omeka\Entity\Value $value,
        bool $isPublic,
        int $count = 0,
        array $langTexts = []
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
                $text = (string) $value->getUri();
                // Normally never here.
                if (!strlen($text)) {
                    return $langTexts;
                }
            }

            // Keep the first translation.
            $lang = (string) $value->getLang();
            if (($isPublic && $isPublicValue) || !$isPublic) {
                if (!isset($langTexts[$lang])) {
                    $langTexts[$lang] = $text;
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
                $this->logger->warn(sprintf(
                    'Resource #%d has a recursive title.', // @translate
                    // TODO Ideally, get initial source value id. Probably very rare anyway above one or two levels.
                    $value->getResource()->getId()
                ));
                $lang = '';
                $text = $subValueResource->getTitle();
                $isPublicSubValueResource = $isPublicSubValue && $subValueResource->isPublic();
                if (($isPublic && $isPublicSubValueResource) || !$isPublic) {
                    if (!isset($langTexts[$lang])) {
                        $langTexts[$lang] = $text;
                    }
                }
            } else {
                $subLangTexts = $this->getValueResourceLangTexts($subValue, $isPublic, $count, $langTexts);
                $langTexts += $subLangTexts;
            }
        }

        return $langTexts;
    }
}
