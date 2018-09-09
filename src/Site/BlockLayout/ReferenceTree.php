<?php
namespace Reference\Site\BlockLayout;

use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;
use Reference\Form\ReferenceTreeBlockForm;
use Reference\Mvc\Controller\Plugin\Reference as ReferencePlugin;
use Zend\Form\FormElementManager\FormElementManagerV3Polyfill as FormElementManager;
use Zend\View\Renderer\PhpRenderer;

class ReferenceTree extends AbstractBlockLayout
{
    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @var array
     */
    protected $defaultSettings = [];

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var ReferencePlugin
     */
    protected $referencePlugin;

    /**
     * @param FormElementManager $formElementManager
     * @param array $defaultSettings
     * @param Api $api
     * @param ReferencePlugin $referencePlugin
     */
    public function __construct(
        FormElementManager $formElementManager,
        array $defaultSettings,
        Api $api,
        ReferencePlugin $referencePlugin
    ) {
        $this->formElementManager = $formElementManager;
        $this->defaultSettings = $defaultSettings;
        $this->api = $api;
        $this->referencePlugin = $referencePlugin;
    }

    public function getLabel()
    {
        return 'Reference tree'; // @translate
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        /** @var \Reference\Form\ReferenceTreeBlockForm $form */
        $form = $this->formElementManager->get(ReferenceTreeBlockForm::class);

        $addedBlock = empty($block);
        if ($addedBlock) {
            $data = $this->defaultSettings;
            $data['args']['query'] = 'site_id=' . $site->id();
        } else {
            $data = $block->data() + $this->defaultSettings;
            if (is_array($data['args']['query'])) {
                $data['args']['query'] = urldecode(
                    http_build_query($data['args']['query'], "\n", '&', PHP_QUERY_RFC3986)
                );
            }
        }

        $data['args']['tree'] = $this->referencePlugin->convertLevelsToTree($data['args']['tree']);

        // TODO Fix set data for radio buttons.
        $form->setData([
            'o:block[__blockIndex__][o:data][args]' => $data['args'],
            'o:block[__blockIndex__][o:data][options]' => $data['options'],
        ]);

        $form->prepare();

        $html = $view->formCollection($form);
        return $html;
    }

    public function prepareRender(PhpRenderer $view)
    {
        $view->headLink()->appendStylesheet($view->assetUrl('vendor/jquery-simplefolders/main.css', 'Reference'));
        $view->headScript()->appendFile($view->assetUrl('vendor/jquery-simplefolders/main.js', 'Reference'));
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $data = $block->data();
        $args = $data['args'];
        $options = $data['options'];

        $tree = $args['tree'];
        unset($args['tree']);
        $total = count($tree);

        return $view->partial(
            'common/block-layout/reference-tree',
            [
                'block' => $block,
                'total' => $total,
                'tree' => $tree,
                'args' => $args,
                'options' => $options,
            ]
        );
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
            $data['args']['resource_name'] = $this->defaultSettings['args']['resource_name'];
        }
        $query = [];
        parse_str($data['args']['query'], $query);
        $data['args']['query'] = $query;

        // Make the search simpler and quicker later on display.
        $data['args']['termId'] = $this->api->searchOne('properties', [
            'term' => $data['args']['term'],
        ])->getContent()->id();

        // Normalize options.
        $data['options']['link_to_single'] = (bool) $data['options']['link_to_single'];
        $data['options']['total'] = (bool) $data['options']['total'];
        $data['options']['branch'] = (bool) $data['options']['branch'];
        $data['options']['expanded'] = (bool) $data['options']['expanded'];
        if (empty($data['options']['query_type'])) {
            $data['options']['query_type'] = $this->defaultSettings['options']['query_type'];
        }

        $block->setData($data);
    }
}
