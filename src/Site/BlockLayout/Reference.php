<?php declare(strict_types=1);
namespace Reference\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;

class Reference extends AbstractBlockLayout
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/reference';

    /**
     * @var Api
     */
    protected $api;

    /**
     * @param Api $api
     */
    public function __construct(
        Api $api
    ) {
        $this->api = $api;
    }

    public function getLabel()
    {
        return 'Reference'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore): void
    {
        $data = $block->getData();

        // Check if data are already formatted, checking the main value.
        if (!empty($data['args']['term'])) {
            return;
        }

        if (!empty($data['args']['property'])) {
            $data['args']['term'] = $data['args']['property'];
            $data['args']['type'] = 'properties';
        } elseif (!empty($data['args']['resource_class'])) {
            $data['args']['term'] = $data['args']['resource_class'];
            $data['args']['type'] = 'resource_classes';
        } else {
            $errorStore->addError('property', 'To create references, there must be a property or a resource class.'); // @translate
            return;
        }
        if (empty($data['args']['resource_name'])) {
            $data['args']['resource_name'] = 'items';
        }
        $query = [];
        parse_str((string) $data['args']['query'], $query);
        $data['args']['query'] = $query;

        $data['args']['order'] = empty($data['args']['order'])
            ? ['alphabetic' => 'ASC']
            : [strtok($data['args']['order'], ' ') => strtok(' ')];

        $data['args']['languages'] = strlen(trim($data['args']['languages']))
            ? array_unique(array_map('trim', explode('|', $data['args']['languages'])))
            : [];

        // Make the search simpler and quicker later on display.
        // TODO To be removed in Omeka 1.2.
        $data['args']['termId'] = $this->api->searchOne($data['args']['type'], [
            'term' => $data['args']['term'],
        ])->getContent()->id();

        // Normalize options.
        $data['options']['link_to_single'] = (bool) $data['options']['link_to_single'];
        $data['options']['custom_url'] = (bool) $data['options']['custom_url'];
        $data['options']['skiplinks'] = (bool) $data['options']['skiplinks'];
        $data['options']['headings'] = (bool) $data['options']['headings'];
        $data['options']['total'] = (bool) $data['options']['total'];
        $data['options']['list_by_max'] = (int) $data['options']['list_by_max'];

        unset($data['args']['property']);
        unset($data['args']['resource_class']);

        $block->setData($data);
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        // Factory is not used to make rendering simpler.
        $services = $site->getServiceLocator();
        $formElementManager = $services->get('FormElementManager');
        $defaultSettings = $services->get('Config')['reference']['block_settings']['reference'];
        $blockFieldset = \Reference\Form\ReferenceFieldset::class;

        // TODO Fill the fieldset like other blocks (cf. blockplus).

        if ($block) {
            $data = $block->data() + $defaultSettings;
            if (is_array($data['args']['query'])) {
                $data['args']['query'] = urldecode(
                    http_build_query($data['args']['query'], '', '&', PHP_QUERY_RFC3986)
                );
            }
        } else {
            $data = $defaultSettings;
            $data['args']['query'] = 'site_id=' . $site->id();
        }

        if (empty($data['args']['term'])) {
            // Nothing.
        } elseif ($this->isResourceClass($data['args']['term'])) {
            $data['args']['resource_class'] = $data['args']['term'];
        } else {
            $data['args']['property'] = $data['args']['term'];
        }
        unset($data['args']['term']);

        $data['args']['order'] = (key($data['args']['order']) === 'alphabetic' ? 'alphabetic' : 'total') . ' ' . reset($data['args']['order']);

        if (isset($data['args']['languages']) && is_array($data['args']['languages'])) {
            $data['args']['languages'] = implode('|', $data['args']['languages']);
        }

        $fieldset = $formElementManager->get($blockFieldset);
        // TODO Fix set data for radio buttons.
        $fieldset->setData([
            'o:block[__blockIndex__][o:data][args]' => $data['args'],
            'o:block[__blockIndex__][o:data][options]' => $data['options'],
        ]);

        $fieldset->prepare();

        $html = '<p>' . $view->translate('Choose a property or a resource class.') . '</p>';
        $html .= $view->formCollection($fieldset);
        return $html;
    }

    public function prepareRender(PhpRenderer $view): void
    {
        $view->headLink()
            ->appendStylesheet($view->assetUrl('css/reference.css', 'Reference'));
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $data = $block->data();
        $args = $data['args'] + ['order' => ['alphabetic' => 'ASC']];
        $options = $data['options'];

        // TODO Update forms and saved params.
        // Use new format for references.
        $term = $args['term'];
        $query = $args['query'];
        unset($args['term']);
        unset($args['query']);
        $options = $options + $args;

        $languages = @$options['languages'];
        unset($options['languages']);
        if ($languages) {
            $options['filters']['languages'] = $languages;
        }

        $options['sort_order'] = reset($args['order']);
        $options['sort_by'] = key($args['order']) === 'alphabetic' ? 'alphabetic' : 'total';
        $options['per_page'] = 0;

        $template = $options['template'] ?? self::PARTIAL_NAME;
        unset($options['template']);

        $vars = [
            'term' => $term,
            'query' => $query,
            'options' => $options,
        ];

        return $template !== self::PARTIAL_NAME && $view->resolver($template)
            ? $view->partial($template, $vars)
            : $view->partial(self::PARTIAL_NAME, $vars);
    }

    protected function isResourceClass($term)
    {
        static $resourceClasses;

        if (is_null($resourceClasses)) {
            $resourceClasses = [];
            foreach ($this->api->search('resource_classes')->getContent() as $resourceClass) {
                $resourceClasses[$resourceClass->term()] = $resourceClass;
            }
        }

        return isset($resourceClasses[$term]);
    }
}
