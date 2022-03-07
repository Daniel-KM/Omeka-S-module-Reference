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
use Reference\Mvc\Controller\Plugin\ReferenceTree as ReferenceTreePlugin;

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
     * @var ReferenceTreePlugin
     */
    protected $referenceTreePlugin;

    /**
     * @param Api $api
     * @param ReferenceTreePlugin $ReferenceTreePlugin
     */
    public function __construct(
        Api $api,
        ReferenceTreePlugin $ReferenceTreePlugin
    ) {
        $this->api = $api;
        $this->referenceTreePlugin = $ReferenceTreePlugin;
    }

    public function getLabel()
    {
        return 'Reference tree'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore): void
    {
        $data = $block->getData();

        // Check if data are already formatted, checking the main value.
        if (!isset($data['tree']) || is_array($data['tree'])) {
            return;
        }

        $defaultSettings = include dirname(__DIR__, 3) . '/config/module.config.php';
        $data += $defaultSettings['reference']['block_settings']['referenceTree'];

        $data['tree'] = $this->referenceTreePlugin->convertTreeToLevels($data['tree']);
        if (empty($data['resource_name'])) {
            $data['resource_name'] = 'items';
        }
        $query = [];
        parse_str(trim(ltrim((string) $data['query'], "? \t\n\r\0\x0B\u{a0}\u{202f}")), $query);
        $data['query'] = $query;

        // Normalize options.
        $data['link_to_single'] = (bool) ($data['link_to_single'] ?? true);
        $data['custom_url'] = (bool) $data['custom_url'];
        $data['total'] = (bool) $data['total'];
        $data['branch'] = (bool) $data['branch'];
        $data['expanded'] = (bool) $data['expanded'];
        $data['query_type'] = $data['query_type'] ?? 'eq';

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

        if ($block) {
            $data = $block->data() + $defaultSettings;
            if (is_array($data['query'])) {
                $data['query'] = urldecode(
                    http_build_query($data['query'], '', '&', PHP_QUERY_RFC3986)
                );
            }
        } else {
            $data = $defaultSettings;
            $data['query'] = 'site_id=' . $site->id();
        }

        $data['tree'] = $this->referenceTreePlugin->convertLevelsToTree($data['tree']);

        $dataForm = [];
        foreach ($data as $key => $value) {
            $dataForm['o:block[__blockIndex__][o:data][' . $key . ']'] = $value;
        }

        $fieldset = $formElementManager->get($blockFieldset);
        $fieldset->get('o:block[__blockIndex__][o:data][query]')
            ->setOption('query_resource_type', $data['resource_type'] ?? 'items');
        $fieldset->populateValues($dataForm);

        return $view->formCollection($fieldset);
    }

    public function prepareRender(PhpRenderer $view): void
    {
        $assetUrl = $view->plugin('assetUrl');
        $view->headLink()->appendStylesheet($assetUrl('vendor/jquery-simplefolders/main.css', 'Reference'));
        $view->headScript()->appendFile($assetUrl('vendor/jquery-simplefolders/main.js', 'Reference'), 'text/javascript', ['defer' => 'defer']);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $options = $block->data() + [
            'heading' => '',
            'tree' => [],
            'query' => [],
        ];

        $heading = $options['heading'];
        $tree = $options['tree'];
        $query = $options['query'];
        unset($options['heading'], $options['tree']);

        $template = $options['template'] ?? self::PARTIAL_NAME;
        unset($options['template']);

        $vars = [
            'block' => $block,
            'heading' => $heading,
            'total' => count($tree),
            'tree' => $tree,
            'query' => $query,
            'options' => $options,
        ];

        return $template !== self::PARTIAL_NAME && $view->resolver($template)
            ? $view->partial($template, $vars)
            : $view->partial(self::PARTIAL_NAME, $vars);
    }
}
