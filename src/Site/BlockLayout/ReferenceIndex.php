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

class ReferenceIndex extends AbstractBlockLayout
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/reference-index';

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
        return 'Reference index'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore): void
    {
        $data = $block->getData();

        // Check if data are already formatted, checking the main value.
        if (!empty($data['args']['terms'])) {
            return;
        }

        $properties = isset($data['args']['properties'])
            ? array_filter($data['args']['properties'], 'strlen')
            : [];
        $resourceClasses = isset($data['args']['resource_classes'])
            ? array_filter($data['args']['resource_classes'], 'strlen')
            : [];
        unset($data['args']['properties']);
        unset($data['args']['resource_classes']);

        $data['args']['terms'] = [];
        if (!empty($properties)) {
            $data['args']['terms'] = $properties;
        }
        if (!empty($resourceClasses)) {
            $data['args']['terms'] = array_merge($data['args']['terms'], $resourceClasses);
        }
        if (empty($data['args']['terms'])) {
            $errorStore->addError('properties', 'To create a list of references, there must be properties or resource classes.'); // @translate
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

        // Normalize options.
        $data['options']['total'] = (bool) $data['options']['total'];

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
        $defaultSettings = $services->get('Config')['reference']['block_settings']['referenceIndex'];
        $blockFieldset = \Reference\Form\ReferenceIndexFieldset::class;

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

        foreach ($data['args']['terms'] as $term) {
            if ($this->isResourceClass($term)) {
                $data['args']['resource_classes'][] = $term;
            } else {
                $data['args']['properties'][] = $term;
            }
        }
        unset($data['args']['terms']);

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

        $html = '<p>' . $view->translate('Choose a list of property or resource class.');
        $html = ' ' . $view->translate('The pages for the selected terms should be created manually with the terms as slug, with the ":" replaced by a "-".') . '</p>';
        $html .= $view->formCollection($fieldset);
        return $html;
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $data = $block->data();
        $args = $data['args'];
        $options = $data['options'];

        // TODO Update forms and saved params.
        // Use new format for references.
        $metadata = $args['terms'];
        $query = $args['query'];
        unset($args['terms']);
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
            'block' => $block,
            'metadata' => $metadata,
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
