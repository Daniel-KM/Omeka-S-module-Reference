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
        return 'Reference'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
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
        parse_str($data['args']['query'], $query);
        $data['args']['query'] = $query;

        $data['args']['order'] = empty($data['args']['order'])
            ? ['alphabetic' => 'ASC']
            : [strtok($data['args']['order'], ' ') => strtok(' ')];

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
                    http_build_query($data['args']['query'], "\n", '&', PHP_QUERY_RFC3986)
                );
            }
        } else {
            $data = $defaultSettings;
            $data['args']['query'] = 'site_id=' . $site->id();
        }

        switch ($data['args']['type']) {
            case 'resource_classes':
                $data['args']['resource_class'] = $data['args']['term'];
                break;
            case 'properties':
                $data['args']['property'] = $data['args']['term'];
                break;
        }
        unset($data['args']['terms']);

        $data['args']['order'] = key($data['args']['order']) . ' ' . reset($data['args']['order']);

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

    public function prepareRender(PhpRenderer $view)
    {
        $view->headLink()
            ->appendStylesheet($view->assetUrl('css/reference.css', 'Reference'));
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $data = $block->data();
        $args = $data['args'];
        $options = $data['options'];

        $term = $args['term'];
        $total = $this->referencePlugin->count(
            $args['term'],
            $args['type'],
            $args['resource_name'],
            $args['query']
        );

        return $view->partial(
            self::PARTIAL_NAME,
            [
                'block' => $block,
                'term' => $term,
                'total' => $total,
                'args' => $args,
                'options' => $options,
            ]
        );
    }
}
