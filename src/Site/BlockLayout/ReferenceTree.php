<?php
namespace Reference\Site\BlockLayout;

use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;
use Reference\Mvc\Controller\Plugin\Reference as ReferencePlugin;
use Zend\View\Renderer\PhpRenderer;

class ReferenceTree extends AbstractBlockLayout
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/reference-tree';

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var ReferencePlugin
     */
    protected $referencePlugin;

    /**
     * @param Api $api
     * @param ReferencePlugin $referencePlugin
     */
    public function __construct(
        Api $api,
        ReferencePlugin $referencePlugin
    ) {
        $this->api = $api;
        $this->referencePlugin = $referencePlugin;
    }

    public function getLabel()
    {
        return 'Reference tree'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();

        // Check if data are already formatted, checking the main value.
        if (is_array($data['args']['tree'])) {
            return;
        }

        $data['args']['tree'] = $this->referencePlugin->convertTreeToLevels($data['args']['tree']);
        if (empty($data['args']['resource_name'])) {
            $data['args']['resource_name'] = 'items';
        }
        $query = [];
        parse_str($data['args']['query'], $query);
        $data['args']['query'] = $query;

        // Make the search simpler and quicker later on display.
        // TODO To be removed in Omeka 1.2.
        $data['args']['termId'] = $this->api->searchOne('properties', [
            'term' => $data['args']['term'],
        ])->getContent()->id();

        // Normalize options.
        $data['options']['link_to_single'] = (bool) $data['options']['link_to_single'];
        $data['options']['custom_url'] = (bool) $data['options']['custom_url'];
        $data['options']['total'] = (bool) $data['options']['total'];
        $data['options']['branch'] = (bool) $data['options']['branch'];
        $data['options']['expanded'] = (bool) $data['options']['expanded'];
        if (empty($data['options']['query_type'])) {
            $data['options']['query_type'] = 'eq';
        }

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
        $defaultSettings = $services->get('Config')['reference']['block_settings']['referenceTree'];
        $blockFieldset = \Reference\Form\ReferenceTreeFieldset::class;

        // TODO Fill the fieldset like other blocks (cf. blockplus).

        if ($block) {
            $data = $block->data() + $defaultSettings;
            if (is_array($data['args']['query'])) {
                $data['args']['query'] = urldecode(
                    http_build_query($data['args']['query'], "\n", '&', PHP_QUERY_RFC3986)
                );
            }
        } else {
            $data = $defaultSettings;
            $data['args']['query'] = 'site_id=' . $site->id();
        }

        $data['args']['tree'] = $this->referencePlugin->convertLevelsToTree($data['args']['tree']);

        $fieldset = $formElementManager->get($blockFieldset);
        // TODO Fix set data for radio buttons.
        $fieldset->setData([
            'o:block[__blockIndex__][o:data][args]' => $data['args'],
            'o:block[__blockIndex__][o:data][options]' => $data['options'],
        ]);

        $fieldset->prepare();

        $html = $view->formCollection($fieldset);
        return $html;
    }

    public function prepareRender(PhpRenderer $view)
    {
        $view->headLink()->appendStylesheet($view->assetUrl('vendor/jquery-simplefolders/main.css', 'Reference'));
        $view->headScript()->appendFile($view->assetUrl('vendor/jquery-simplefolders/main.js', 'Reference'), 'text/javascript', ['defer' => 'defer']);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $data = $block->data();
        $args = $data['args'];
        $options = $data['options'];

        $tree = $args['tree'];
        unset($args['tree']);
        $total = count($tree);

        $template = isset($options['template']) ? $options['template'] : self::PARTIAL_NAME;
        unset($options['template']);

        $vars = [
            'block' => $block,
            'total' => $total,
            'tree' => $tree,
            'args' => $args,
            'options' => $options,
        ];

        return $view->resolver($template)
            ? $view->partial($template, $vars)
            : $view->partial(self::PARTIAL_NAME, $vars);
    }
}
