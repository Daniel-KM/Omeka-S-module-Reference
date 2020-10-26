<?php declare(strict_types=1);
namespace Reference;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $oldVersion
 * @var string $newVersion
 */
$services = $serviceLocator;

/**
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var array $config
 */
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$config = require dirname(__DIR__, 2) . '/config/module.config.php';

// The reference plugin is not available during upgrade.
include_once dirname(__DIR__, 2) . '/src/Mvc/Controller/Plugin/References.php';
include_once dirname(__DIR__, 2) . '/src/Mvc/Controller/Plugin/ReferenceTree.php';
$entityManager = $services->get('Omeka\EntityManager');
$controllerPluginManager = $services->get('ControllerPluginManager');
$api = $controllerPluginManager->get('api');
$referencesPlugin = new Mvc\Controller\Plugin\References($entityManager, $services->get('Omeka\ApiAdapterManager'), $api, $controllerPluginManager->get('translate'), [], [], [], false);
$referenceTreePlugin = new Mvc\Controller\Plugin\ReferenceTree($api, $referencesPlugin);

if (version_compare($oldVersion, '3.4.5', '<')) {
    $referenceSlugs = $settings->get('reference_slugs');
    foreach ($referenceSlugs as &$slugData) {
        $slugData['term'] = $slugData['id'];
        unset($slugData['id']);
    }
    $settings->set('reference_slugs', $referenceSlugs);

    $tree = $settings->get('reference_tree_hierarchy', '');
    $settings->set(
        'reference_tree_hierarchy',
        $referenceTreePlugin->convertTreeToLevels($tree)
    );

    $defaultConfig = $config[strtolower(__NAMESPACE__)]['config'];
    $settings->set(
        'reference_resource_name',
        $defaultConfig['reference_resource_name']
    );
    $settings->set(
        'reference_total',
        $defaultConfig['reference_total']
    );
}

if (version_compare($oldVersion, '3.4.7', '<')) {
    $tree = $settings->get('reference_tree_hierarchy', '');
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
    $connection->exec($sql);
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
                $data = $config['reference']['block_settings']['args'];
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
    $settings->set('reference_slugs', $referenceSlugs);
}
