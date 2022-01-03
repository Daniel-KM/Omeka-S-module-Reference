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
        if (!empty($data['args']['fields'])) {
            return;
        }

        if (!empty($data['args']['properties'])) {
            $data['args']['fields'] = $data['args']['properties'];
            $data['args']['type'] = 'properties';
        } elseif (!empty($data['args']['resource_classes'])) {
            $data['args']['fields'] = $data['args']['resource_classes'];
            $data['args']['type'] = 'resource_classes';
        } else {
            $errorStore->addError('properties', 'To create references, there must be one or more properties or resource classes.'); // @translate
            return;
        }
        if (empty($data['args']['resource_name'])) {
            $data['args']['resource_name'] = 'items';
        }
        $query = [];
        parse_str(ltrim((string) $data['args']['query'], "? \t\n\r\0\x0B"), $query);
        $data['args']['query'] = $query;

        $data['args']['order'] = empty($data['args']['order'])
            ? ['alphabetic' => 'ASC']
            : [strtok($data['args']['order'], ' ') => strtok(' ')];

        $data['args']['languages'] = strlen(trim($data['args']['languages']))
            ? array_unique(array_map('trim', explode('|', $data['args']['languages'])))
            : [];

        // Normalize options.
        $data['options']['by_initial'] = !empty($data['options']['by_initial']);
        $data['options']['link_to_single'] = !empty($data['options']['link_to_single']);
        $data['options']['custom_url'] = !empty($data['options']['custom_url']);
        $data['options']['skiplinks'] = !empty($data['options']['skiplinks']);
        $data['options']['headings'] = !empty($data['options']['headings']);
        $data['options']['total'] = !empty($data['options']['total']);
        $data['options']['list_by_max'] = (int) $data['options']['list_by_max'];

        unset($data['args']['properties']);
        unset($data['args']['resource_classes']);

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

        if (empty($data['args']['fields'])) {
            // Nothing.
        } else {
            foreach ($data['args']['fields'] as $field) {
                if ($this->isResourceClass($field)) {
                    $data['args']['resource_classes'][] = $field;
                } else {
                    $data['args']['properties'][] = $field;
                }
            }
        }
        unset($data['args']['fields']);

        $data['args']['order'] = (key($data['args']['order']) === 'alphabetic' ? 'alphabetic' : 'total') . ' ' . reset($data['args']['order']);

        if (isset($data['args']['languages']) && is_array($data['args']['languages'])) {
            $data['args']['languages'] = implode('|', $data['args']['languages']);
        }

        $fieldset = $formElementManager->get($blockFieldset);
        $fieldset
            ->get('o:block[__blockIndex__][o:data][args]')
            ->get('query')
            ->setOption('query_resource_type', $data['resource_type'] ?? 'items');
        // TODO Fix set data for radio buttons.
        $fieldset->setData([
            'o:block[__blockIndex__][o:data][args]' => $data['args'],
            'o:block[__blockIndex__][o:data][options]' => $data['options'],
        ]);

        $fieldset->prepare();

        $html = '<p>' . $view->translate('Choose one or more properties or one or more resource classes.') . '</p>';
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
        $fields = ['fields' => $args['fields']];
        $query = $args['query'];
        unset(
            $args['fields'],
            $args['query']
        );
        $options = $options + $args;

        $languages = @$options['languages'];
        unset($options['languages']);
        if ($languages) {
            $options['filters']['languages'] = $languages;
        }

        $byInitial = !empty($options['by_initial']);
        if ($byInitial) {
            $options['filters']['begin'] = $view->params()->fromQuery('begin') ?: 'a';
        }

        $options['sort_order'] = reset($args['order']);
        $options['sort_by'] = key($args['order']) === 'alphabetic' ? 'alphabetic' : 'total';
        $options['per_page'] = 0;

        $template = $options['template'] ?? self::PARTIAL_NAME;
        unset($options['template']);

        $vars = [
            'fields' => $fields,
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
