<?php declare(strict_types=1);

namespace Reference;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$translator = $services->get('MvcTranslator');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

$defaultConfig = require dirname(__DIR__, 2) . '/config/module.config.php';

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.62')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.62'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

if (version_compare($oldVersion, '3.4.7', '<')) {
    // The reference plugin is not available during upgrade, so prepare it.
    include_once dirname(__DIR__, 2) . '/src/Mvc/Controller/Plugin/References.php';
    include_once dirname(__DIR__, 2) . '/src/Mvc/Controller/Plugin/ReferenceTree.php';

    if (version_compare($oldVersion, '3.4.43', '<')) {
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('AdvancedSearch');
        $hasAdvancedSearch = $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
        $referencesPlugin = new Mvc\Controller\Plugin\References(
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\ApiAdapterManager'),
            $services->get('Omeka\Acl'),
            $services->get('Omeka\AuthenticationService')->getIdentity(),
            $services->get('ControllerPluginManager')->get('api'),
            $services->get('ControllerPluginManager')->get('translate'),
            false,
            $hasAdvancedSearch
        );
        $referenceTreePlugin = new Mvc\Controller\Plugin\ReferenceTree($api, $referencesPlugin);
    } else {
        $referencesPlugin = new Mvc\Controller\Plugin\References(
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\Connection'),
            $services->get('Omeka\ApiAdapterManager'),
            $services->get('Omeka\Acl'),
            $services->get('Omeka\AuthenticationService')->getIdentity(),
            $services->get('Omeka\ApiManager'),
            $plugins->get('translate'),
            false
        );
        $referenceTreePlugin = new Mvc\Controller\Plugin\ReferenceTree($api, $referencesPlugin);
    }
}

if (version_compare($oldVersion, '3.4.5', '<')) {
    $referenceSlugs = $settings->get('reference_slugs');
    foreach ($referenceSlugs as &$slugData) {
        $slugData['term'] = $slugData['id'];
        unset($slugData['id']);
    }
    unset($slugData);
    $settings->set('reference_slugs', $referenceSlugs);

    $tree = $settings->get('reference_tree_hierarchy', '');
    $settings->set(
        'reference_tree_hierarchy',
        $referenceTreePlugin->convertTreeToLevels($tree)
    );

    $settings->set(
        'reference_resource_name',
        $defaultConfig['reference']['config']['reference_resource_name'] ?? null
    );
    $settings->set(
        'reference_total',
        $defaultConfig['reference']['config']['reference_total'] ?? null
    );
}

if (version_compare($oldVersion, '3.4.7', '<')) {
    $tree = $settings->get('reference_tree_hierarchy') ?: [];
    $tree = (array) $tree;
    $treeString = $referenceTreePlugin->convertFlatLevelsToTree($tree);
    $settings->set(
        'reference_tree_hierarchy',
        $referenceTreePlugin->convertTreeToLevels($treeString)
    );

    $repository = $entityManager->getRepository(\Omeka\Entity\SitePageBlock::class);
    $blocks = $repository->findBy(['layout' => 'reference']);
    foreach ($blocks as $block) {
        $data = $block->getData();
        if (empty($data['reference']['tree']) || $data['reference']['mode'] !== 'tree') {
            continue;
        }
        $treeString = $referenceTreePlugin->convertFlatLevelsToTree($data['reference']['tree']);
        $data['reference']['tree'] = $referenceTreePlugin->convertTreeToLevels($treeString);
        $block->setData($data);
        $entityManager->persist($block);
    }
    $entityManager->flush();
}

if (version_compare($oldVersion, '3.4.9', '<')) {
    // Append item pool (query) to reference block.
    $sql = <<<'SQL'
UPDATE site_page_block
SET data = CONCAT('{"reference":{"order":{"alphabetic":"ASC"},"query":[],', SUBSTR(data, 15))
WHERE layout = "reference";
SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.4.10', '<')) {
    $settings->set('reference_tree_query_type', $settings->get('reference_query_type'));
    $settings->delete('reference_query_type');

    $repository = $entityManager->getRepository(\Omeka\Entity\SitePageBlock::class);
    /** @var \Omeka\Entity\SitePageBlock[] $blocks */
    $blocks = $repository->findBy(['layout' => 'reference']);
    foreach ($blocks as $block) {
        $data = $block->getData();
        $mode = empty($data['reference']['mode']) ? null : $data['reference']['mode'];
        $data['args'] = $data['reference'];
        unset($data['reference']);
        unset($data['args']['mode']);
        switch ($mode) {
            case 'list':
                unset($data['args']['tree']);
                unset($data['options']['query_type']);
                unset($data['options']['branch']);
                unset($data['options']['expanded']);
                break;
            case 'tree':
                $block->setLayout('referenceTree');
                unset($data['args']['type']);
                unset($data['args']['order']);
                unset($data['options']['skiplinks']);
                unset($data['options']['headings']);
                break;
            default:
                $data = $defaultConfig['reference']['block_settings']['args'] ?? null;
                break;
        }
        $block->setData($data);
        $entityManager->persist($block);
    }
    $entityManager->flush();
}

if (version_compare($oldVersion, '3.4.16', '<')) {
    $properties = [];
    foreach ($api->search('properties')->getContent() as $property) {
        $properties[$property->id()] = $property->term();
    }

    $resourceClasses = [];
    foreach ($api->search('resource_classes')->getContent() as $resourceClass) {
        $resourceClasses[$resourceClass->id()] = $resourceClass->term();
    }

    $referenceSlugs = $settings->get('reference_slugs') ?: [];
    foreach ($referenceSlugs as $slug => &$slugData) {
        $slugData['term'] = $slugData['type'] === 'resource_classes'
            ? @$resourceClasses[$slugData['term']]
            : @$properties[$slugData['term']];
        if (empty($slugData['term'])) {
            unset($referenceSlugs[$slug]);
        }
    }
    unset($slugData);
    $settings->set('reference_slugs', $referenceSlugs);
}

if (version_compare($oldVersion, '3.4.23.3', '<')) {
    $message = new PsrMessage(
        'This release changed some features, so check your theme: the config has been moved to each site; the key "o-module-reference:values" is replaced by "o:references"; the helper $this->reference() is deprecated and is now an alias of $this->references().'
    );
    $messenger->addWarning($message);
    $message = new PsrMessage(
        'If you want to keep old features, use release 3.4.22.3.'
    );
    $messenger->addWarning($message);

    // Convert the main tree if any into a standard page with block Reference Tree.
    if ($settings->get('reference_tree_enabled')) {
        $term = $settings->get('reference_tree_term');
        $hierarchy = $settings->get('reference_tree_hierarchy');
        if ($term && $hierarchy) {
            $branch = $settings->get('reference_tree_branch');
            $queryType = $settings->get('reference_tree_query_type');
            $expanded = $settings->get('reference_tree_expanded');
            $sites = $entityManager->getRepository(\Omeka\Entity\Site::class)->findAll();
            foreach ($sites as $site) {
                // Check if the site page slug exists.
                $sitePageSlug = $api->searchOne('site_pages', ['site_id' => $site->getId(), 'slug' => 'reference-tree'])->getContent();
                $sitePageSlug = $sitePageSlug ? 'reference-tree-' . random_int(10000, 99999) : 'reference-tree';

                $page = new \Omeka\Entity\SitePage();
                $page->setSite($site);
                $page->setTitle('Tree of references');
                $page->setSlug($sitePageSlug);
                $page->setIsPublic(true);
                $page->setCreated(new \DateTime('now'));

                $block = new \Omeka\Entity\SitePageBlock();
                $block->setLayout('referenceTree');
                $block->setPage($page);
                $block->setPosition(1);
                $block->setData([
                    'heading' => 'Tree of references ({total} total)',
                    'term' => $term,
                    'tree' => $hierarchy,
                    'resource_name' => 'items',
                    'query' => [],
                    'query_type' => $queryType,
                    'link_to_single' => true,
                    'custom_url' => false,
                    'total' => true,
                    'branch' => $branch,
                    'expanded' => $expanded,
                    'template' => '',
                ]);

                $entityManager->persist($page);
                $entityManager->persist($block);
                // Flush below.
            }

            $message = new PsrMessage(
                'The main tree of references (/reference-tree) is now available only as a page block, not as a special page.'
            );
            $messenger->addWarning($message);
            if (count($sites)) {
                $message = new PsrMessage(
                    'A new page with the tree of references (/page/reference-tree) has been added to {count} sites with the existing config.',
                    ['count' => count($sites)]
                );
                $messenger->addSuccess($message);
            }
        }
    }

    $removeds = [
        'reference_tree_enabled',
        'reference_tree_term',
        'reference_tree_hierarchy',
        'reference_tree_branch',
        'reference_tree_query_type',
        'reference_tree_expanded',
    ];
    foreach ($removeds as $removed) {
        $settings->delete($removed);
    }

    // Update the args of the site page block for referenceTree.
    $repository = $entityManager->getRepository(\Omeka\Entity\SitePageBlock::class);
    /** @var \Omeka\Entity\SitePageBlock[] $blocks */
    $blocks = $repository->findBy(['layout' => 'referenceTree']);
    foreach ($blocks as $block) {
        $data = $block->getData();
        $data = ($data['args'] ?? []) + ($data['options'] ?? []);
        unset($data['termId']);
        $block->setData($data);
        $entityManager->persist($block);
    }

    /**
     * List properties and resource classes by term.
     *
     * @return array
     */
    $listTerms = function (): array {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        $terms = [];

        $qb = $connection->createQueryBuilder();
        $qb
            ->select(
                'DISTINCT CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                'property.id AS id'
            )
            ->from('property', 'property')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id');
        $result = $connection->executeQuery($qb)->fetchAllKeyValue();
        $terms['properties'] = array_map('intval', $result);

        $qb = $connection->createQueryBuilder();
        $qb
            ->select(
                'DISTINCT CONCAT(vocabulary.prefix, ":", resource_class.local_name) AS term',
                'resource_class.id AS id'
            )
            ->from('resource_class', 'resource_class')
            ->innerJoin('resource_class', 'vocabulary', 'vocabulary', 'resource_class.vocabulary_id = vocabulary.id');
        $result = $connection->executeQuery($qb)->fetchAllKeyValue();
        $terms['resource_classes'] = array_map('intval', $result);

        return $terms;
    };

    // Update main config.
    $terms = $listTerms();

    $newSlugs = [];
    $slugs = $settings->get('reference_slugs') ?: [];
    // Remove disabled slugs and use terms.
    foreach ($slugs as $slug => $slugData) {
        if (!empty($slugData['active'])
            && isset($terms[$slugData['type']][$slugData['term']])
        ) {
            unset($slugData['active']);
            unset($slugData['type']);
            $newSlugs[$slug] = $slugData;
        }
    }
    $settings->set('reference_slugs', $newSlugs);

    $newOptions = [];
    $removeds = [
        'reference_list_headings' => 'headings',
        'reference_list_skiplinks' => 'skiplinks',
        'reference_total' => 'total',
        'reference_link_to_single' => 'link_to_single',
        'reference_custom_url' => 'custom_url',
    ];
    foreach ($removeds as $removed => $newOption) {
        if ($settings->get($removed)) {
            $newOptions[] = $newOption;
        }
        $settings->delete($removed);
    }
    $settings->set('reference_options', $newOptions);

    // Move advanced site search improvements to module Advanced Search Plus.
    $settings->delete('reference_search_list_values');

    // Move main config to each site.
    $resourceName = $settings->get('reference_resource_name', 'items') ?: 'items';

    $siteIds = $api->search('sites', [], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
    /** @var \Omeka\Settings\SiteSettings $siteSettings */
    $siteSettings = $services->get('Omeka\Settings\Site');
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $siteSettings->set('reference_resource_name', $resourceName);
        $siteSettings->set('reference_options', $newOptions);
        $siteSettings->set('reference_slugs', $newSlugs);
    }

    $settings->delete('reference_resource_name');
    $settings->delete('reference_options');
    $settings->delete('reference_slugs');

    // Final flush.
    $entityManager->flush();
}

if (version_compare($oldVersion, '3.4.24.3', '<')) {
    $message = new PsrMessage(
        'It is possible now to limit the list of references, for example only the of subjects starting with "a" with argument "filters[begin]=a".' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'It is possible now to list not only references, but resources by reference, for example all documents of an author or all items with each subject.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.32.3', '<')) {
    $message = new PsrMessage(
        'It is possible now to aggregate properties (api and helper).' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.33.3', '<')) {
    $message = new PsrMessage(
        'It is possible now to filter references by data types.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.34.3', '<')) {
    $this->execSqlFromFile($this->modulePath() . '/data/install/schema.sql');

    $message = new PsrMessage(
        'It is possible now to get translated linked resource.' // @translate
    );
    // Job is not available during upgrade.
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'Translated linked resource metadata should be indexed in main settings.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.35.3', '<')) {
    $repository = $entityManager->getRepository(\Omeka\Entity\SitePageBlock::class);
    /** @var \Omeka\Entity\SitePageBlock[] $blocks */
    $blocks = $repository->findBy(['layout' => 'reference']);
    foreach ($blocks as $block) {
        $data = $block->getData();
        $data['args']['fields'] = [$data['args']['term']];
        // The term is kept for compatibility with old themes, at least until edition of the page.
        $block->setData($data);
        $entityManager->persist($block);
    }
    $entityManager->flush();

    $blocks = $repository->findBy(['layout' => 'referenceIndex']);
    foreach ($blocks as $block) {
        $data = $block->getData();
        $data['args']['fields'] = $data['args']['terms'];
        // The term is kept for compatibility with old themes, at least until edition of the page.
        $block->setData($data);
        $entityManager->persist($block);
    }
    $entityManager->flush();

    $blocks = $repository->findBy(['layout' => 'referenceTree']);
    foreach ($blocks as $block) {
        $data = $block->getData();
        $data['fields'] = [
            $data['term'] ?? (empty($data['fields']) ? 'dcterms:subject' : reset($data['fields'])),
        ];
        // The term is kept for compatibility with old themes, at least until edition of the page.
        $block->setData($data);
        $entityManager->persist($block);
    }
    $entityManager->flush();

    $message = new PsrMessage(
        'It is possible now to aggregate properties in references, for example to get list of people from properties dcterms:creator and dcterms:contributor.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'Warning: the name of the source properties or classes "term" has been replace by "fields" in pages, so check your theme templates if you updated the default ones of the module.' // @translate
    );
    $messenger->addWarning($message);
    $message = new PsrMessage(
        'It is possible now to get a specific number of initials, for example to get the list of years from standard dates.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.43', '<')) {
    $message = new PsrMessage(
        'Many improvements were done to output references and facets a lot more quickly, in particular for big bases.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.47', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `reference_metadata` ADD INDEX `idx_is_public` (`is_public`);
SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Index exists.
    }
}

if (version_compare($oldVersion, '3.4.48', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `reference_metadata`
CHANGE `lang` `lang` varchar(190) NOT NULL DEFAULT '' AFTER `field`;
SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.4.49', '<')) {
    // Check for themes with templates to move.
    $globPath = 'themes/*/view/common/block-layout/reference*';
    $globPath = OMEKA_PATH . '/' . $globPath;
    $paths = glob($globPath);
    if ($paths) {
        $start = mb_strlen(OMEKA_PATH . '/');
        $result = [];
        foreach ($paths as $filepath) {
            // Check exception for reference and reference-tree.
            $filename = basename($filepath);
            if (in_array($filename, ['reference.phtml', 'reference-tree.phtml'])) {
                continue;
            }
            $result[] = mb_substr($filepath, $start);
        }

        if ($result) {
            $message = new PsrMessage(
                'Because of the integration of block templates in Omeka S v4.1, you should move custom templates before upgrading the module: move following files from directory "view/common/block-layout/" to "view/common/block-template/" of each theme, except the default files "reference.phtml" and "reference-tree.phtml". Then, you should add all templates from the directory "view/common/block-template/" at the bottom of the file "config/theme.ini" of each theme, for example `block_templates.reference.reference-index = "Reference index custom"`. This process can be done automatically via a task of the module Easy Admin before upgrading the module (important: backup your themes first). Matching files: {json}', // @translate
                ['json' => json_encode($result, 448)]
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
        }
    }

    /** @see /BlockPlus/data/scripts/upgrade.php */

    $pageRepository = $entityManager->getRepository(\Omeka\Entity\SitePage::class);
    $blocksRepository = $entityManager->getRepository(\Omeka\Entity\SitePageBlock::class);

    $logger = $services->get('Omeka\Logger');

    /**
     * Replace filled settings "template" by the new layout data for reference.
     *
     * @var \Omeka\Entity\SitePageBlock $block
     */

    $pagesUpdated = [];
    foreach ($blocksRepository->findBy(['layout' => 'referenceIndex']) as $block) {
        $data = $block->getData();
        $block->setLayout('reference');
        $template = $data['options']['template'] ?? null;
        if ($template && $template !== 'common/block-layout/reference') {
            $layoutData = $block->getLayoutData();
            $layoutData['template_name'] = pathinfo($template, PATHINFO_FILENAME);
            $block->setLayoutData($layoutData);
            $pagesUpdated[$block->getPage()->getSite()->getSlug()][$block->getPage()->getSlug()] = $block->getPage()->getSlug();
        }
        unset($data['options']['template']);
        $block->setData($data);
    }

    $entityManager->flush();

    if ($pagesUpdated) {
        $result = array_map('array_values', $pagesUpdated);
        $message = new PsrMessage(
            'The block Reference Index was replaced by a template layout of block Reference. You may check pages: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $messenger->addWarning($message);
        $logger->warn($message->getMessage(), $message->getContext());
    }

    /**
     * Added a warning for default style.
     */
    $pagesUpdated = [];
    foreach ($blocksRepository->findBy(['layout' => 'reference']) as $block) {
        $pagesUpdated[$block->getPage()->getSite()->getSlug()][$block->getPage()->getSlug()] = $block->getPage()->getSlug();
    }
    if ($pagesUpdated) {
        $result = array_map('array_values', $pagesUpdated);
        $message = new PsrMessage(
            'The default heading is now <h2>. Check your theme if you customized it. You may check pages: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $messenger->addWarning($message);
        $logger->warn($message->getMessage(), $message->getContext());
    }
}

if (version_compare($oldVersion, '3.4.50', '<')) {
    /**
     * Migrate blocks of this module to new blocks of Omeka S v4.1.
     *
     * Migrate templates.
     * Replace filled settting "heading" by a specific block "Heading" or "Html".
     * Update settings of block Reference to make it flat.
     *
     * @var \Laminas\Log\Logger $logger
     *
     * @see \Omeka\Db\Migrations\MigrateBlockLayoutData
     */

    // It is not possible to search for templates that use heading, because it
    // is used in many places. So only in doc block.

    // Check themes that use "$heading" in block
    $strings = [
        'themes/*/view/common/block-layout/reference*' => [
            '* @var string $heading',
            'if ($options[\'heading\'])',
        ],
        'themes/*/view/common/block-template/reference*' => [
            '* @var string $heading',
            'if ($options[\'heading\'])',
        ],
        'themes/*/view/common/reference*' => [
            '* @var string $heading',
            'if ($options[\'heading\'])',
        ],
    ];
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $results = [];
    foreach ($strings as $path => $strings) {
        $result = $manageModuleAndResources->checkStringsInFiles($strings, $path);
        if ($result) {
            $results[] = $result;
        }
    }
    if ($results) {
        $message = new PsrMessage(
            'The option "heading" was removed from block Reference and replaced by a block Heading (if module Block Plus is present) or Html. Remove it in the following files before upgrade and automatic conversion: {json}', // @translate
            ['json' => json_encode($results, 448)]
        );
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
    }

    $logger = $services->get('Omeka\Logger');
    $pageRepository = $entityManager->getRepository(\Omeka\Entity\SitePage::class);
    $blocksRepository = $entityManager->getRepository(\Omeka\Entity\SitePageBlock::class);

    $viewHelpers = $services->get('ViewHelperManager');
    $escape = $viewHelpers->get('escapeHtml');
    $hasBlockPlus = $this->isModuleActive('BlockPlus');

    $pagesUpdated = [];
    $pagesUpdated2 = [];
    foreach ($pageRepository->findAll() as $page) {
        $pageSlug = $page->getSlug();
        $siteSlug = $page->getSite()->getSlug();
        $position = 0;
        foreach ($page->getBlocks() as $block) {
            $block->setPosition(++$position);
            $layout = $block->getLayout();
            if ($layout !== 'reference' && $layout !== 'referenceTree') {
                continue;
            }
            $data = $block->getData() ?: [];
            $layoutData = $block->getLayoutData() ?? [];

            // Migrate template.
            $template = $data['template'] ?? '';
            $layoutData = $block->getLayoutData() ?? [];
            $existingTemplateName = $layoutData['template_name'] ?? null;
            $templateName = pathinfo($template, PATHINFO_FILENAME);
            $templateCheck = $layout === 'referenceTree' ? 'reference-tree' : 'reference';
            if ($templateName
                && $templateName !== $templateCheck
                && (!$existingTemplateName || $existingTemplateName === $templateCheck)
            ) {
                $layoutData['template_name'] = $templateName;
                $pagesUpdated[$siteSlug][$pageSlug] = $pageSlug;
            }
            unset($data['template']);

            // Make config of block reference flat.
            if ($layout === 'reference' && isset($data['args'])) {
                $heading = $data['options']['heading'] ?? '';
                $order = empty($data['args']['order']) ? ['alphabetic' => 'ASC'] : $data['args']['order'];
                $sortBy = key($order) === 'alphabetic' ? 'alphabetic' : 'total';
                $sortOrder = reset($order);
                $data = [
                    'fields' => $data['args']['fields'] ?? [],
                    'type' => $data['args']['type'] ?? 'properties',
                    'resource_name' => $data['args']['resource_name'] ?? 'items',
                    'query' => $data['args']['query'] ?? [],
                    'languages' => $data['args']['languages'] ?? [],
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                    'by_initial' => !empty($data['options']['by_initial']),
                    'link_to_single' => !empty($data['options']['link_to_single']),
                    'custom_url' => !empty($data['options']['custom_url']),
                    'skiplinks' => !empty($data['options']['skiplinks']),
                    'headings' => !empty($data['options']['headings']),
                    'total' => !empty($data['options']['total']),
                    'list_by_max' => empty($data['options']['list_by_max']) ? 0 : (int) $data['options']['list_by_max'],
                    'subject_property' => $data['options']['subject_property'] ?? null,
                ];
            } else {
                $heading = $data['options']['heading'] ?? $data['heading'] ?? '';
            }

            // Replace setting "heading".
            if (strlen($heading)) {
                $b = new \Omeka\Entity\SitePageBlock();
                $b->setPage($page);
                $b->setPosition(++$position);
                if ($hasBlockPlus) {
                    $b->setLayout('heading');
                    $b->setData([
                        'text' => $heading,
                        'level' => 2,
                    ]);
                } else {
                    $b->setLayout('html');
                    $b->setData([
                        'html' => '<h2>' . $escape($heading) . '</h2>',
                    ]);
                }
                $entityManager->persist($b);
                $block->setPosition(++$position);
                $pagesUpdated2[$siteSlug][$pageSlug] = $pageSlug;
            }
            unset($data['heading']);

            $block->setData($data);
            $block->setLayoutData($layoutData);
        }
    }

    $entityManager->flush();

    if ($pagesUpdated) {
        $result = array_map('array_values', $pagesUpdated);
        $message = new PsrMessage(
            'The setting "template" was moved to the new block layout settings available since Omeka S v4.1. You may check pages for styles: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $messenger->addWarning($message);
        $logger->warn($message->getMessage(), $message->getContext());
    }

    if ($pagesUpdated2) {
        $result = array_map('array_values', $pagesUpdated2);
        $message = new PsrMessage(
            'The option "heading" was removed from block Reference. New block "Heading" (if module Block Plus id present) or "Html" was prepended to all blocks that had a filled heading. You may check pages for styles: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $messenger->addWarning($message);
        $logger->warn($message->getMessage(), $message->getContext());
    }
}
