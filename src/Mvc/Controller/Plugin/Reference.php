<?php
namespace Reference\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Representation\PropertyRepresentation;
use Omeka\Mvc\Controller\Plugin\Api;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class Reference extends AbstractPlugin
{
    /**
     * @var int
     */
    protected $DC_Title_id = 1;

    /**
     * @var int
     */
    protected $DC_Subject_id = 3;

    /**
     * @param EntityManager
     */
    protected $entityManager;

    /**
     * @param Api
     */
    protected $api;

    /**
     * @param EntityManager $entityManager
     * @param Api $api
     */
    public function __construct(EntityManager $entityManager, Api $api)
    {
        $this->entityManager = $entityManager;
        $this->api = $api;
    }

    /**
     * Get the reference view object.
     *
     * @param int|string|PropertyRepresentation $property
     * @param string $resourceName All resources types if empty.
     * @param int $perPage
     * @param int $page One-based page number.
     * @return Reference|array|null The result or null if called directly, else
     * this view helper.
     */
     public function __invoke($property = null, $resourceName = null, $perPage = null, $page = null)
     {
         if (empty($property)) {
             return $this;
         }
         return $this->asList($property, $resourceName, $perPage, $page);
     }

     /**
      * Get the list of categories as an associative array with totals.
      *
      * @param int|string|PropertyRepresentation $property
      * @param string $resourceName All resources types if empty.
      * @param int $perPage
      * @param int $page One-based page number.
      * @return array|null
      */
     public function asList($property = null, $resourceName = null, $perPage = null, $page = null)
     {
         $propertyId = $this->getPropertyId($property);
         if (empty($propertyId)) {
             return;
         }

         $entityClass = $this->mapResourceNameToEntity($resourceName);
         if (empty($entityClass)) {
             return;
         }

         $references = $this->getReferencesList($propertyId, $entityClass, 'properties', $perPage, $page);
         return $references;
     }

     /**
      * Get the list of references of the slug.
      *
      * @param string $slug
      * @param string $resourceName
      * @return array Associative array with total and first record ids.
      */
     public function getList($slug, $resourceName = null)
     {
         $settings = $this->getController()->settings();
         $slugs = $settings->get('reference_slugs');
         if (empty($slug) || empty($slugs) || empty($slugs[$slug]['active'])) {
             return;
         }

        $entityClass = $this->mapResourceNameToEntity($resourceName);
        if (empty($entityClass)) {
            return;
        }

        $references = $this->getReferencesList($slugs[$slug]['id'], $entityClass, $slugs[$slug]['type']);
        return $references;
     }

     /**
      * Get the list of subjects.
      *
      * @return array.
      */
     public function getTree()
     {
         $settings = $this->getController()->settings();
         $slugs = $settings->get('reference_tree_enabled');
         if (!$settings->get('reference_tree_enabled')) {
             return [];
         }
         $subjects = $this->getSubjectsTree();
         return $subjects;
     }

     /**
      * Count the total of distinct element texts for a slug.
      *
      * @todo Manage multiple resource names (items, item sets, medias) at once.
      *
      * @param string $slug
      * @param string $resourceName
      * @return int The number of references if only one resource name is set.
      */
     public function count($slug, $resourceName = null)
     {
         $controller = $this->getController();
         $settings = $controller->settings();
         $slugs = $settings->get('reference_slugs') ?: [];
         if (empty($slug) || empty($slugs) || empty($slugs[$slug]['active'])) {
             return;
         }

         $entityClass = $this->mapResourceNameToEntity($resourceName);
         if (empty($entityClass)) {
             return;
         }

         return $this->countReferences($slugs[$slug]['id'], $entityClass, $slugs[$slug]['type']);
     }

     /**
      * Display the list of references via a partial view.
      *
      * @param array $references Array of references elements to show.
      * @param array $options Options to display references. Values are booleans:
      * - raw: Show references as raw text, not links (default to false)
      * - strip: Remove html tags (default to true)
      * - skiplinks: Add the list of letters at top and bottom of the page
      * - headings: Add each letter as headers
      * @return string Html list.
      */
     public function displayList($references, array $options = [])
     {
         if (empty($references) || empty($options['slug'])) {
             return;
         }

         $options = $this->cleanOptions($options);

         $controller = $this->getController();
         $settings = $controller->settings();
         $slugs = $settings->get('reference_slugs') ?: [];
         $slug = $options['slug'];
         if (empty($slugs) || empty($slugs[$slug]['active'])) {
             return;
         }

         $resourceName = isset($options["resource_name"]) ? $options["resource_name"] : null;
         $entityClass = $this->mapResourceNameToEntity($resourceName);
         if (empty($entityClass)) {
             return;
         }

         $entityClass = \Omeka\Entity\Item::class;

         $references = $this->getReferencesList($slugs[$slug]['id'], $entityClass, $slugs[$slug]['type'], null, null, 'withFirst');

         if ($options['strip']) {
             $total = count($references);
             $referencesList = array_map('strip_tags', array_keys($references));
             // List of subjects may need to be reordered after reformatting. The
             // total may have been changed. In that case, total of each
             // reference is lost.
             if ($total == count($referencesList)) {
                 $references = array_combine($referencesList, $references);
             }
             // Should be done manually.
             else {
                 $referenceList = array_combine($referenceList, array_fill(0, count($referenceList), null));
                 foreach ($referenceList as $referenceText => &$value) {
                     foreach ($references as $reference => $referenceData) {
                         if (is_null($value)) {
                             $value = $referenceData;
                         }
                         // Keep the first record id.
                         else {
                             $value['total'] += $referenceData['total'];
                         }
                     }
                 }
                 $references = $referencesList;
             }

             // Reorder stripped data.
             ksort($references, SORT_STRING | SORT_FLAG_CASE);
         }

         $partial = $controller->viewHelpers()->get('partial');
         $html = $partial('common/reference-list', [
             'references' => $references,
             'slug' => $slug,
             'slugData' => $slugs[$slug],
             'options' => $options,
         ]);

         return $html;
     }

     /**
      * Display the tree of subjects via a partial view.
      *
      * @uses http://www.jqueryscript.net/other/jQuery-Flat-Folder-Tree-Plugin-simplefolders.html
      *
      *  Example for the mode "tree":
      * @example
      * $references = "
      * Europe
      * - France
      * - Germany
      * - United Kingdom
      * -- England
      * -- Scotland
      * -- Wales
      * Asia
      * - Japan
      * ";
      *
      * $hierarchy = "
      * <ul class="tree">
      *     <li>Europe
      *         <div class="expander"></div>
      *         <ul>
      *             <li>France</li>
      *             <li>Germany</li>
      *             <li>United Kingdom
      *                 <div class="expander"></div>
      *                 <ul>
      *                     <li>England</li>
      *                     <li>Scotland</li>
      *                     <li>Wales</li>
      *                 </ul>
      *             </li>
      *         </ul>
      *     </li>
      *     <li>Asia
      *         <div class="expander"></div>
      *         <ul>
      *             <li>Japan</li>
      *         </ul>
      *     </li>
      * </ul>
      * ";
      *
      * @param array $references Array of subjects elements to show.
      * @param array $options Options to display the references. Values are booleans:
      * - raw: Show subjects as raw text, not links (default to false)
      * - strip: Remove html tags (default to true)
      * - expanded: Show tree as expanded (defaul to config)
      * @return string Html list.
      */
     public function displayTree($subjects, array $options = [])
     {
         if (empty($subjects)) {
             return;
         }

         $options = $this->cleanOptions($options);

         if ($options['strip']) {
             $subjects = array_map('strip_tags', $subjects);
         }

         $controller = $this->getController();
         $partial = $controller->viewHelpers()->get('partial');
         $html = $partial('common/reference-tree', [
             'subjects' => $subjects,
             'options' => $options,
         ]);

         return $html;
     }

     /**
      * Get list of options.
      */
     protected function cleanOptions($options)
     {
         $settings = $this->getController()->settings();

         $mode = isset($options['mode']) && $options['mode'] == 'tree' ? 'tree' : 'list';

         $cleanedOptions = [
             'mode' => $mode,
             'raw' => isset($options['raw']) && $options['raw'],
             'strip' => isset($options['strip']) ? (bool) $options['strip'] : true,
         ];

         switch ($mode) {
             case 'list':
                 $cleanedOptions['headings'] = (bool) (isset($options['headings'])
                     ? $options['headings']
                     : $settings->get('reference_list_headings'));
                 $cleanedOptions['skiplinks'] = (bool) (isset($options['skiplinks'])
                     ? $options['skiplinks']
                     : $settings->get('reference_list_skiplinks'));
                 $cleanedOptions['slug'] = empty($options['slug'])
                     ? $this->DC_Subject_id
                     : $options['slug'];
                 $cleanedOptions['query_type'] = isset($options['query_type'])
                     ? ($options['query_type'] == 'in' ? 'in' : 'eq')
                     : $settings->get('reference_query_type', 'eq');
                 $cleanedOptions['link_single'] = (bool) (isset($options['link_single'])
                     ? $options['link_single']
                     : $settings->get('reference_link_to_single'));
                 break;

             case 'tree':
                 $cleanedOptions['expanded'] = (bool) (isset($options['expanded'])
                     ? $options['expanded']
                     : $settings->get('reference_tree_expanded', false));
                 break;
         }

         return $cleanedOptions;
     }

     /**
      * Get the list of references, the total for each one and the first item.
      *
      * When the type is not a property, a filter is added and the list of
      * titles is returned. If there are multiple title, they are returned all.
      *
      * @param int $propertyId May be the resource class id.
      * @param string $entityClass
      * @param string $type "properties" (default) or "resource_classes".
      * @param int $perPage
      * @param int $page One-based page number.
      * @param string $output May be "associative" (default), "list" or "withFirst".
      * @return array Associative list of references, with the total and the
      * first record.
      */
     protected function getReferencesList(
         $propertyId,
         $entityClass,
         $type = 'properties',
         $perPage = null,
         $page = null,
         $output = null
     ) {
         $entityManager = $this->entityManager;
         $qb = $entityManager->createQueryBuilder();

         switch ($type) {
             case 'resource_classes':
                 $resourceClassId = $propertyId;
                 $propertyId = $this->DC_Title_id;

                 $qb
                     ->select([
                         'DISTINCT value.value',
                         // "Distinct" avoids to count duplicate values in properties in
                         // a resource: we count resources, not properties.
                         $qb->expr()->countDistinct('resource.id') . ' AS total',
                     ])
                     // This checks visibility automatically.
                     ->from(\Omeka\Entity\Resource::class, 'resource')
                     ->leftJoin(
                         \Omeka\Entity\Value::class,
                         'value',
                         'WITH',
                         'value.resource = resource AND value.property = :property_id'
                     )
                     ->setParameter('property_id', $propertyId)
                     ->where($qb->expr()->eq('resource.resourceClass', ':resource_class'))
                     ->setParameter('resource_class', (int) $resourceClassId)
                     ->groupBy('value.value')
                     ->orderBy('value.value', 'ASC')
                     ->addOrderBy('resource.id', 'ASC');

                 if ($entityClass !== \Omeka\Entity\Resource::class) {
                     $qb
                         ->innerJoin($entityClass, 'res', 'WITH', 'resource.id = res.id');
                 }
                 break;

             case 'properties':
             default:
                 $qb
                     ->select([
                         'value.value',
                         // "Distinct" avoids to count duplicate values in properties in
                         // a resource: we count resources, not properties.
                         $qb->expr()->countDistinct('resource.id') . ' AS total',
                     ])
                     ->from(\Omeka\Entity\Value::class, 'value')
                     // This join allow to check visibility automatically too.
                     ->innerJoin($entityClass, 'resource', 'WITH', 'value.resource = resource')
                     ->groupBy('value.value')
                     ->addGroupBy('resource.id')
                     ->orderBy('value.value', 'ASC')
                     ->addOrderBy('resource.id', 'ASC')
                     ->andWhere($qb->expr()->eq('value.property', ':property'))
                     ->setParameter('property', $propertyId)
                     // Only literal values.
                     ->andWhere($qb->expr()->isNotNull('value.value'));
                 break;
         }

         if ($output === 'withFirst') {
             $qb
                 ->addSelect([
                     'resource.id AS first_id',
                 ]);
         }

         if ($perPage) {
             $qb->setMaxResults($perPage);
             if ($page > 1) {
                 $offset = ($page - 1) * $perPage;
                 $qb->setFirstResult($offset);
             }
         }

         switch ($output) {
             case 'list':
             case 'withFirst':
                 $result = $qb->getQuery()->getScalarResult();
                 $result = array_map(function ($v) {
                     $v['total'] = (int) $v['total'];
                     return $v;
                 }, $result);
                 $result = array_combine(array_column($result, 'value'), $result);
                 return $result;
             case 'associative':
             default:
                 $result = $qb->getQuery()->getScalarResult();
                // Array column cannot be used in one step, because the null
                // value (no title) should be converted to "", not to "0".
                // $result = array_column($result, 'total', 'value');
                $result = array_combine(
                    array_column($result, 'value'),
                    array_column($result, 'total')
                );
                return array_map('intval', $result);
         }
     }

     /**
      * Get the dafault tree of subjects.
      */
     protected function getSubjectsTree()
     {
         $settings = $this->getController()->settings();
         $subjects = $settings->get('reference_tree_hierarchy');
         $subjects = array_filter(explode(PHP_EOL, $subjects));
         return $subjects;
     }

     /**
      * Count the references for a slug.
      *
      * When the type is not a property, a filter is added and the list of
      * titles is returned.
      *
      * @todo Manage multiple entity classes (items, item sets, medias) at once.
      *
     * @param int $propertyId May be the resource class id.
     * @param string $entityClass
     * @param string $type "properties" or "resource_classes".
      * @return int The number of references if only one entity class is set.
      */
     protected function countReferences($propertyId, $entityClass, $type)
     {
         $entityManager = $this->entityManager;
         $qb = $entityManager->createQueryBuilder();

         switch ($type) {
             case 'resource_classes':
                 $qb
                     ->select([
                         $qb->expr()->countDistinct('resource.id'),
                     ])
                     ->from(\Omeka\Entity\Resource::class, 'resource')
                     ->andWhere($qb->expr()->eq('resource.resourceClass', ':resource_class'))
                     ->setParameter('resource_class', (int) $propertyId);
                 break;

             case 'properties':
             default:
                 $qb
                     ->select([
                         // Here, this is the count of references, not resources.
                         $qb->expr()->countDistinct('value.value'),
                     ])
                     ->from(\Omeka\Entity\Value::class, 'value')
                     // This join allow to check visibility automatically too.
                     ->innerJoin(\Omeka\Entity\Resource::class, 'resource', 'WITH', 'value.resource = resource')
                     ->andWhere($qb->expr()->eq('value.property', ':property'))
                     ->setParameter('property', (int) $propertyId)
                     ->andWhere($qb->expr()->isNotNull('value.value'));
                 break;
         }

         if ($entityClass !== \Omeka\Entity\Resource::class) {
             $qb
                 ->innerJoin($entityClass, 'res', 'WITH', 'resource.id = res.id');
         }

         $totalRecords = $qb->getQuery()->getSingleScalarResult();
         return $totalRecords;
     }

     /**
      * Convert a value into a property id.
      *
      * @param mixed $property May be the property id, the term, or the object.
      * @return int
      */
     protected function getPropertyId($property)
     {
         if (is_numeric($property)) {
             return (int) $property;
         }
         if (is_object($property)) {
             return $property instanceof \Omeka\Api\Representation\PropertyRepresentation
                 ? $property->id()
                 : $property->getId();
         }
         if (!strpos($property, ':')) {
             return;
         }

         $result = $this->api->searchOne('properties', ['term' => $property])->getContent();
         if (empty($result)) {
             return;
         }
         return $result->id();
     }

     /**
      * Normalize the resource name as an entity class.
      *
      * @param string $resourceName
      * @return string
      */
     protected function mapResourceNameToEntity($resourceName)
     {
         $resourceEntityMap = [
             null => \Omeka\Entity\Resource::class,
             'resources' => \Omeka\Entity\Resource::class,
             'item_sets' => \Omeka\Entity\ItemSet::class,
             'items' => \Omeka\Entity\Item::class,
             'media' => \Omeka\Entity\Media::class,
             'o:Resource' => \Omeka\Entity\Resource::class,
             'o:ItemSet' => \Omeka\Entity\ItemSet::class,
             'o:Item' => \Omeka\Entity\Item::class,
             'o:Media' => \Omeka\Entity\Media::class,
             \Omeka\Entity\Resource::class => \Omeka\Entity\Resource::class,
             \Omeka\Entity\ItemSet::class => \Omeka\Entity\ItemSet::class,
             \Omeka\Entity\Item::class => \Omeka\Entity\Item::class,
             \Omeka\Entity\Media::class => \Omeka\Entity\Media::class,
             \Omeka\Api\Representation\AbstractResourceRepresentation::class => \Omeka\Entity\Resource::class,
             \Omeka\Api\Representation\ItemSetRepresentation::class => \Omeka\Entity\ItemSet::class,
             \Omeka\Api\Representation\ItemRepresentation::class => \Omeka\Entity\Item::class,
             \Omeka\Api\Representation\MediaRepresentation::class => \Omeka\Entity\Media::class,
         ];
         if (isset($resourceEntityMap[$resourceName])) {
             return $resourceEntityMap[$resourceName];
         }
     }
}
