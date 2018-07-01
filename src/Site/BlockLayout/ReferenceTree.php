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
            $data['reference']['query'] = 'site_id=' . $site->id();
        } else {
            $data = $block->data() + $this->defaultSettings;
        }

        $data['reference']['tree'] = $this->referencePlugin->convertLevelsToTree($data['reference']['tree']);
        if (is_array($data['reference']['query'])) {
            $data['reference']['query'] = urldecode(
                http_build_query($data['reference']['query'], "\n", '&', PHP_QUERY_RFC3986)
            );
        }

        // TODO Fix set data for radio buttons.
        $form->setData([
            'o:block[__blockIndex__][o:data][reference]' => $data['reference'],
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
        $args = $data['reference'];
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

        $data['reference']['tree'] = $this->referencePlugin->convertTreeToLevels($data['reference']['tree']);
        if (empty($data['reference']['resource_name'])) {
            $data['reference']['resource_name'] = $this->defaultSettings['reference']['resource_name'];
        }
        parse_str($data['reference']['query'], $query);
        $data['reference']['query'] = $query;

        // Make the search simpler and quicker later on display.
        $data['reference']['termId'] = $this->api->searchOne('properties', [
            'term' => $data['reference']['term'],
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
