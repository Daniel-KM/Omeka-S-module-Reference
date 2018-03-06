<?php
namespace Reference\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;
use Reference\Form\ReferenceBlockForm;
use Reference\Mvc\Controller\Plugin\Reference as ReferencePlugin;
use Zend\Form\FormElementManager\FormElementManagerV3Polyfill as FormElementManager;
use Zend\View\Renderer\PhpRenderer;

class Reference extends AbstractBlockLayout
{
    /**
     * @var Api
     */
    protected $api;

    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @var ReferencePlugin
     */
    protected $referencePlugin;

    /**
     * @var array
     */
    protected $defaultSettings;

    /**
     * @param Api $api
     * @param FormElementManager $formElementManager
     * @param array $defaultSettings
     * @param ReferencePlugin
     */
    public function __construct(
        Api $api,
        FormElementManager $formElementManager,
        ReferencePlugin $referencePlugin,
        array $defaultSettings
    ) {
        $this->api = $api;
        $this->formElementManager = $formElementManager;
        $this->referencePlugin = $referencePlugin;
        $this->defaultSettings = $defaultSettings;
    }

    public function getLabel()
    {
        return 'Reference'; // @translate
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        /** @var \Reference\Form\ReferenceBlockForm $form */
        $form = $this->formElementManager->get(ReferenceBlockForm::class);
        $form->init();

        $addedBlock = empty($block);
        if ($addedBlock) {
            $data = $this->defaultSettings;
        } else {
            $data = $block->data();
        }

        $mode = isset($data['reference']['mode']) && $data['reference']['mode'] === 'tree' ? 'tree' : 'list';

        switch ($data['reference']['type']) {
            case 'resource_classes':
                $data['reference']['resource_class'] = $data['reference']['term'];
                break;
            case 'properties':
                if ($mode === 'tree') {
                    $data['options']['tree_term'] = $data['reference']['term'];
                } else {
                    $data['reference']['property'] = $data['reference']['term'];
                }
                break;
        }

        $data['reference']['tree'] = $mode === 'tree'
            ? $this->referencePlugin->convertLevelsToTree($data['reference']['tree'])
            : '';

        // TODO Fix set data for radio buttons.
        $form->setData([
            'o:block[__blockIndex__][o:data][reference]' => $data['reference'],
            'o:block[__blockIndex__][o:data][options]' => $data['options'],
        ]);

        $form->prepare();

        $html = '<p>';
        $html .= $view->translate('Select the type of reference (property, resource class or static tree) and remove the previous ones.'); // @translate.
        $html .= '</p>';
        $html .= $view->formCollection($form);
        return $html;
    }

    public function prepareRender(PhpRenderer $view)
    {
        // TODO Get the block of the page and check if there are a list and/or a tree.
        // For list.
        $view->headLink()->appendStylesheet($view->assetUrl('css/reference.css', 'Reference'));
        // For tree.
        $view->headLink()->appendStylesheet($view->assetUrl('vendor/jquery-simplefolders/main.css', 'Reference'));
        $view->headScript()->appendFile($view->assetUrl('vendor/jquery-simplefolders/main.js', 'Reference'));
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $data = $block->data();
        $args = $data['reference'];
        $options = $data['options'];

        $mode = $data['reference']['mode'];
        switch ($mode) {
            case 'tree':
                $total = count($data['reference']['tree']);
                break;
            case 'list':
                $term = $args['term'];
                $type = $args['type'];
                $resourceName = $args['resource_name'];
                $total = $this->referencePlugin->count($term, $type, $resourceName);
                break;
        }

        return $view->partial(
            'common/block-layout/reference',
            [
                'block' => $block,
                'mode' => $mode,
                'total' => $total,
                'args' => $args,
                'options' => $options,
            ]
        );
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();

        $data['options']['link_to_single'] = (bool) $data['options']['link_to_single'];
        $data['options']['skiplinks'] = (bool) $data['options']['skiplinks'];
        $data['options']['headings'] = (bool) $data['options']['headings'];
        $data['options']['total'] = (bool) $data['options']['total'];
        $data['options']['branch'] = (bool) $data['options']['branch'];
        $data['options']['expanded'] = (bool) $data['options']['expanded'];
        if (empty($data['options']['query_type'])) {
            $data['options']['query_type'] = 'eq';
        }

        if (!empty($data['reference']['property'])) {
            $data['reference']['term'] = $data['reference']['property'];
            $data['reference']['type'] = 'properties';
            $data['reference']['mode'] = 'list';
            unset($data['reference']['tree']);
        } elseif (!empty($data['reference']['resource_class'])) {
            $data['reference']['term'] = $data['reference']['resource_class'];
            $data['reference']['type'] = 'resource_classes';
            $data['reference']['mode'] = 'list';
            unset($data['reference']['tree']);
        } elseif (!empty($data['reference']['tree'])) {
            $data['reference']['tree'] = $this->referencePlugin->convertTreeToLevels($data['reference']['tree']);
            $data['reference']['term'] = empty($data['options']['tree_term'])
                ? 'dcterms:subject'
                : $data['options']['tree_term'];
            $data['reference']['type'] = 'properties';
            $data['reference']['mode'] = 'tree';
        } else {
            $errorStore->addError('property', 'To create references, there must be a property, a resource class or a tree.'); // @translate
            return;
        }

        if (empty($data['reference']['resource_name'])) {
            $data['reference']['resource_name'] = 'items';
        }

        $data['reference']['termId'] = $this->api->searchOne($data['reference']['type'], [
            'term' => $data['reference']['term'],
        ])->getContent()->id();

        unset($data['reference']['property']);
        unset($data['reference']['resource_class']);
        unset($data['options']['tree_term']);

        $block->setData($data);
    }
}
