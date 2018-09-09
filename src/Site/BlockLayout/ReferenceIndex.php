<?php
namespace Reference\Site\BlockLayout;

use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;
use Reference\Form\ReferenceIndexBlockForm;
use Reference\Mvc\Controller\Plugin\Reference as ReferencePlugin;
use Zend\Form\FormElementManager\FormElementManagerV3Polyfill as FormElementManager;
use Zend\View\Renderer\PhpRenderer;

class ReferenceIndex extends AbstractBlockLayout
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
        return 'Reference index'; // @translate
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        /** @var \Reference\Form\ReferenceBlockForm $form */
        $form = $this->formElementManager->get(ReferenceIndexBlockForm::class);

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

        switch ($data['args']['type']) {
            case 'resource_classes':
                $data['args']['resource_classes'] = $data['args']['terms'];
                break;
            case 'properties':
                $data['args']['properties'] = $data['args']['terms'];
                break;
        }
        unset($data['args']['terms']);

        $data['args']['order'] = key($data['args']['order']) . ' ' . reset($data['args']['order']);

        // TODO Fix set data for radio buttons.
        $form->setData([
            'o:block[__blockIndex__][o:data][args]' => $data['args'],
            'o:block[__blockIndex__][o:data][options]' => $data['options'],
        ]);

        $form->prepare();

        $html = '<p>' . $view->translate('Choose a list of property or resource class.');
        $html = ' ' . $view->translate('The pages for the selected terms should be created manually with the terms as slug, with the ":" replaced by a "-".') . '</p>';
        $html .= $view->formCollection($form);
        return $html;
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $data = $block->data();
        $args = $data['args'];
        $options = $data['options'];

        $terms = $args['terms'];
        /*
        $totals = [];
        if ($options['total']) {
            // TODO Use one single query to get all the count for properties or resource classes.
            $totals = $this->referencePlugin->count(
                $args['terms'],
                $args['type'],
                $args['resource_name'],
                $args['query']
            );
        }
        */
        $totals = $options['total'];

        return $view->partial(
            'common/block-layout/reference-index',
            [
                'block' => $block,
                'terms' => $terms,
                'totals' => $totals,
                'args' => $args,
                'options' => $options,
            ]
        );
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
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

        if (!empty($properties)) {
            $data['args']['terms'] = $properties;
            $data['args']['type'] = 'properties';
        } elseif (!empty($resourceClasses)) {
            $data['args']['terms'] = $resourceClasses;
            $data['args']['type'] = 'resource_classes';
        } else {
            $errorStore->addError('properties', 'To create a list of references, there must be properties or resource classes.'); // @translate
            return;
        }
        if (empty($data['args']['resource_name'])) {
            $data['args']['resource_name'] = $this->defaultSettings['args']['resource_name'];
        }
        $query = [];
        parse_str($data['args']['query'], $query);
        $data['args']['query'] = $query;

        $data['args']['order'] = empty($data['args']['order'])
            ? $this->defaultSettings['args']['order']
            : [strtok($data['args']['order'], ' ') => strtok(' ')];

        // Normalize options.
        $data['options']['total'] = (bool) $data['options']['total'];

        $block->setData($data);
    }
}
