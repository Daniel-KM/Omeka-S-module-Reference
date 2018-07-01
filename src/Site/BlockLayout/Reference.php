<?php
namespace Reference\Site\BlockLayout;

use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
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
        return 'Reference'; // @translate
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        /** @var \Reference\Form\ReferenceBlockForm $form */
        $form = $this->formElementManager->get(ReferenceBlockForm::class);

        $addedBlock = empty($block);
        if ($addedBlock) {
            $data = $this->defaultSettings;
            $data['reference']['query'] = 'site_id=' . $site->id();
        } else {
            $data = $block->data() + $this->defaultSettings;
        }

        switch ($data['reference']['type']) {
            case 'resource_classes':
                $data['reference']['resource_class'] = $data['reference']['term'];
                break;
            case 'properties':
                $data['reference']['property'] = $data['reference']['term'];
                break;
        }

        $data['reference']['order'] = key($data['reference']['order']) . ' ' . reset($data['reference']['order']);

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
        $view->headLink()->appendStylesheet($view->assetUrl('css/reference.css', 'Reference'));
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $data = $block->data();
        $args = $data['reference'];
        $options = $data['options'];

        $term = $args['term'];
        $total = $this->referencePlugin->count(
            $args['term'],
            $args['type'],
            $args['resource_name'],
            $args['query']
        );

        return $view->partial(
            'common/block-layout/reference',
            [
                'block' => $block,
                'total' => $total,
                'term' => $term,
                'args' => $args,
                'options' => $options,
            ]
        );
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();

        if (!empty($data['reference']['property'])) {
            $data['reference']['term'] = $data['reference']['property'];
            $data['reference']['type'] = 'properties';
        } elseif (!empty($data['reference']['resource_class'])) {
            $data['reference']['term'] = $data['reference']['resource_class'];
            $data['reference']['type'] = 'resource_classes';
        } else {
            $errorStore->addError('property', 'To create references, there must be a property, a resource class or a tree.'); // @translate
            return;
        }
        if (empty($data['reference']['resource_name'])) {
            $data['reference']['resource_name'] = $this->defaultSettings['reference']['resource_name'];
        }
        parse_str($data['reference']['query'], $query);
        $data['reference']['query'] = $query;

        $data['reference']['order'] = empty($data['reference']['order'])
            ? $this->defaultSettings['reference']['order']
            : [strtok($data['reference']['order'], ' ') => strtok(' ')];

        // Make the search simpler and quicker later on display.
        $data['reference']['termId'] = $this->api->searchOne($data['reference']['type'], [
            'term' => $data['reference']['term'],
        ])->getContent()->id();

        // Normalize options.
        $data['options']['link_to_single'] = (bool) $data['options']['link_to_single'];
        $data['options']['skiplinks'] = (bool) $data['options']['skiplinks'];
        $data['options']['headings'] = (bool) $data['options']['headings'];
        $data['options']['total'] = (bool) $data['options']['total'];

        unset($data['reference']['property']);
        unset($data['reference']['resource_class']);

        $block->setData($data);
    }
}
