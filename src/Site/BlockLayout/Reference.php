<?php declare(strict_types=1);

namespace Reference\Site\BlockLayout;

use Common\Stdlib\EasyMeta;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Site\BlockLayout\TemplateableBlockLayoutInterface;
use Omeka\Stdlib\ErrorStore;

class Reference extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/reference';

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    public function __construct(
        EasyMeta $easyMeta
    ) {
        $this->easyMeta = $easyMeta;
    }

    public function getLabel()
    {
        return 'Reference'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore): void
    {
        $data = $block->getData();

        // Check if data are already formatted, checking the main value.
        if (!empty($data['fields'])) {
            return;
        }

        if (!empty($data['properties'])) {
            $data['fields'] = $data['properties'];
            $data['type'] = 'properties';
        } elseif (!empty($data['resource_classes'])) {
            $data['fields'] = $data['resource_classes'];
            $data['type'] = 'resource_classes';
        } else {
            $errorStore->addError('properties', 'To create references, there must be one or more properties or resource classes.'); // @translate
            return;
        }

        unset($data['properties']);
        unset($data['resource_classes']);

        if (empty($data['resource_name'])) {
            $data['resource_name'] = 'items';
        }

        $query = [];
        parse_str(ltrim((string) $data['query'], "? \t\n\r\0\x0B"), $query);
        $data['query'] = $query;

        $data['sort_by'] = isset($data['sort_by']) && $data['sort_by'] === 'total' ? 'total' : 'alphabetic';
        $data['sort_order'] = isset($data['sort_order']) && strcasecmp($data['sort_order'], 'desc') === 0 ? 'desc' : 'asc';

        $data['languages'] ??= [];

        // Normalize options one time.
        $data['by_initial'] = !empty($data['by_initial']);
        $data['search_config'] = $data['search_config'] ?? null;
        $data['link_to_single'] = !empty($data['link_to_single']);
        $data['custom_url'] = !empty($data['custom_url']);
        $data['skiplinks'] = !empty($data['skiplinks']);
        $data['headings'] = !empty($data['headings']);
        $data['total'] = !empty($data['total']);
        $data['thumbnail'] = $data['thumbnail'] ?? null;
        $data['list_by_max'] = (int) $data['list_by_max'];

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

        $data = $block ? ($block->data() ?? []) + $defaultSettings : $defaultSettings;

        $dataForm = [];
        foreach ($data as $key => $value) {
            if ($key === 'fields') {
                if (empty($value)) {
                    continue;
                } else {
                    // Properties and resource classes cannot be mixed
                    $key = $this->easyMeta->resourceClassTerm($key)
                        ? 'resource_classes'
                        : 'properties';
                }
            } elseif ($key === 'query') {
                if (is_array($value)) {
                    $value = urldecode(http_build_query($value, '', '&', PHP_QUERY_RFC3986));
                } else {
                    $value = $block ? $value : 'site_id=' . $site->id();
                }
            }
            $dataForm['o:block[__blockIndex__][o:data][' . $key . ']'] = $value;
        }

        /** @var \Reference\Form\ReferenceFieldset $fieldset */
        $fieldset = $formElementManager->get($blockFieldset);
        $fieldset->populateValues($dataForm);
        $fieldset
            ->get('o:block[__blockIndex__][o:data][query]')
            ->setOption('query_resource_type', $data['resource_type'] ?? 'items');

        $html = '<p>' . $view->translate('Choose one or more properties or one or more resource classes.') . '</p>';
        $html .= '<p>' . $view->translate('With the layout template "Reference Index", the pages for the selected terms should be created manually with the terms as slug, with the ":" replaced by a "-".') . '</p>';
        $html .= $view->formCollection($fieldset, false);
        return $html;
    }

    public function prepareRender(PhpRenderer $view): void
    {
        $view->headLink()
            ->appendStylesheet($view->assetUrl('css/reference.css', 'Reference'));
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = self::PARTIAL_NAME)
    {
        $data = $block->data();

        // TODO Is this check still needed?
        $options = $data + [
            'sort_by' => 'alphabetic',
            'sort_order' => 'ASC',
        ];

        // Use new format for references.
        $fields = ['fields' => $options['fields'] ?? []];
        $query = $options['query'] ?? [];

        $languages = $options['languages'] ?? [];
        unset($options['languages']);
        if ($languages) {
            $options['filters']['languages'] = $languages;
        }

        $byInitial = !empty($options['by_initial']);
        if ($byInitial) {
            $options['filters']['begin'] = $view->params()->fromQuery('begin') ?: 'a';
        }

        $options['per_page'] = 0;

        unset(
            $options['fields'],
            $options['query'],
            $options['languages']
        );

        $vars = [
            'block' => $block,
            'fields' => $fields,
            'query' => $query,
            'options' => $options,
        ];

        return $view->partial($templateViewScript, $vars);
    }
}
