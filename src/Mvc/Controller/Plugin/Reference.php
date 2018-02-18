<?php
namespace Reference\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\PropertyRepresentation;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class Reference extends AbstractPlugin
{
    /**
     * @param EntityManager
     */
    protected $entityManager;

    /**
     * @param ApiManager
     */
    protected $api;

    /**
     * @param EntityManager $entityManager
     * @param ApiManager $api
     */
    public function __construct(EntityManager $entityManager, ApiManager $api)
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

         $references = $this->getReferencesList($propertyId, $entityClass, $perPage, $page);
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

        if ($slugs[$slug]['type'] === 'properties') {
            $references = $this->getReferencesList($slugs[$slug]['id'], $entityClass);
        } else {
            $references = null;
        }
        return $references;
     }

     /**
      * Get the list of subjects.
      *
      * @return array.
      */
     public function getTree()
     {
         if (!$this->setting('reference_tree_enabled')) {
             return [];
         }
         $subjects = $this->getSubjectsTree();
         return $subjects;
     }

     /**
      * Count the total of distinct element texts for a slug.
      *
      * @param string $slug
      * @return int
      */
     public function count($slug)
     {
         $slugs = $this->getView()->setting('reference_slugs') ?: [];
         if (empty($slug) || empty($slugs) || empty($slugs[$slug]['active'])) {
             return;
         }

         return $this->countReferences($slugs[$slug]);
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

         $entityClass = \Omeka\Entity\Item::class;

         if ($slugs[$slug]['type'] === 'properties') {
             $references = $this->getReferencesList($slugs[$slug]['id'], $entityClass, null, null, 'withFirst');
         } else {
             return;
         }

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
             $subjects = array_map('strip_formatting', $subjects);
         }

         $html = $this->getView()->partial('common/reference-tree.php', [
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
         $mode = isset($options['mode']) && $options['mode'] == 'tree' ? 'tree' : 'list';

         $cleanedOptions = [
             'mode' => $mode,
             'raw' => isset($options['raw']) && $options['raw'],
             'strip' => isset($options['strip']) ? (boolean) $options['strip'] : true,
         ];

         switch ($mode) {
             case 'list':
                 $settings = $this->getController()->settings();
                 $cleanedOptions['headings'] = (boolean) (isset($options['headings'])
                     ? $options['headings']
                     : $settings->get('reference_list_headings'));
                 $cleanedOptions['skiplinks'] = (boolean) (isset($options['skiplinks'])
                     ? $options['skiplinks']
                     : $settings->get('reference_list_skiplinks'));
                 $cleanedOptions['slug'] = empty($options['slug'])
                     ? $this->DC_Subject_id
                     : $options['slug'];
                 $cleanedOptions['query_type'] = isset($options['query_type'])
                     ? ($options['query_type'] == 'in' ? 'in' : 'eq')
                     : $settings->get('reference_query_type', 'eq');
                 $cleanedOptions['link_single'] = (boolean) (isset($options['link_single'])
                     ? $options['link_single']
                     : $settings->get('reference_link_to_single'));
                 break;

             case 'tree':
                 $cleanedOptions['expanded'] = (boolean) (isset($options['expanded'])
                 ? $options['expanded']
                 : $this->getController()->settings()->get('reference_tree_expanded'));
                 break;
         }

         return $cleanedOptions;
     }

     /**
      * Get the list of references, the total for each one and the first item.
      *
      * When the type is not an element, a filter is added and the list of titles
      * are returned.
      *
      * @param int $propertyId
      * @param string $entityClass
      * @param int $perPage
      * @param int $page One-based page number.
      * @param string $output May be "associative" (default), "list" or "withFirst".
      * @return array Associative list of references, with the total and the
      * first record.
      */
     protected function getReferencesList($propertyId, $entityClass, $perPage = null, $page = null, $output = null)
     {
         $entityManager = $this->entityManager;

         $valuesRepository = $entityManager->getRepository(\Omeka\Entity\Value::class);
         $values = $valuesRepository->findBy([
             'property' => $propertyId,
             'type' => 'literal',
         ]);

         $qb = $entityManager->createQueryBuilder();
         if ($output === 'withFirst') {
             $qb
                 ->select([
                     'value.value',
                     $qb->expr()->countDistinct('resource.id') . ' AS total',
                     'resource.id as first_id',
                 ]);
         } else {
             $qb
                 ->select([
                     'value.value',
                     // "Distinct" avoids to count duplicate values in properties in
                     // a resource: we count resources, not properties.
                     $qb->expr()->countDistinct('resource.id') . ' AS total',
                 ]);
         }
         $qb
             ->from(\Omeka\Entity\Value::class, 'value')
             // This join allow to check visibility automatically too.
             ->innerJoin($entityClass, 'resource', 'WITH', 'value.resource = resource')
             ->andWhere($qb->expr()->eq('value.property', ':property'))
             ->setParameter('property', $propertyId)
             ->groupBy('value.value')
             ->orderBy('value.value', 'ASC')
             ->addOrderBy('resource.id', 'ASC')
         ;

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
                 $result = array_column($result, 'total', 'value');
                 return array_map('intval', $result);
         }
     }

     /**
      * Get the dafault tree of subjects.
      */
     protected function getSubjectsTree()
     {
         $subjects = get_option('reference_tree_hierarchy');
         $subjects = array_filter(explode(PHP_EOL, $subjects));
         return $subjects;
     }

     /**
      * Count the references for a slug.
      *
      * When the type is not an element, a filter is added and the list of titles
      * are returned.
      *
      * @param array $slugData
      * @return int
      */
     protected function countReferences($slugData)
     {
         $elementId = $slugData['type'] == 'Element' ? $slugData['id'] : $this->DC_Title_id;

         $db = get_db();
         $elementTextsTable = $db->getTable('ElementText');
         $elementTextsAlias = $elementTextsTable->getTableAlias();
         $select = $elementTextsTable->getSelect()
             ->reset(Zend_Db_Select::COLUMNS)
             ->from([], [$elementTextsAlias . '.text'])
             ->joinInner(['items' => $db->Item], $elementTextsAlias . ".record_type = 'Item' AND items.id = $elementTextsAlias.record_id", [])
             ->where($elementTextsAlias . ".record_type = 'Item'")
             ->where($elementTextsAlias . '.element_id = ' . (integer) $elementId)
             ->group($elementTextsAlias . '.text');

         if ($slugData['type'] == 'ItemType') {
             $select->where('items.item_type_id = ' . (integer) $slugData['id']);
         }

         $permissions = new Omeka_Db_Select_PublicPermissions('Items');
         $permissions->apply($select, 'items');

         $totalRecords = $db->query($select . " COLLATE 'utf8_unicode_ci'")->rowCount();
         return $totalRecords;
     }

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

         $result = $this->api->search('properties', [
             'vocabulary_prefix' => strtok($property, ':'),
             'local_name' => strtok(':'),
         ])->getContent();
         if (empty($result)) {
             return;
         }
         return $result[0]->id();
     }

     protected function mapResourceNameToEntity($resourceName)
     {
         $resourceEntityMap = [
             null => \Omeka\Entity\Resource::class,
             'resources' => \Omeka\Entity\Resource::class,
             'item_sets' => \Omeka\Entity\ItemSet::class,
             'items' => \Omeka\Entity\Item::class,
             'media' => \Omeka\Entity\Media::class,
             \Omeka\Entity\Resource::class => \Omeka\Entity\Resource::class,
             \Omeka\Entity\ItemSet::class => \Omeka\Entity\ItemSet::class,
             \Omeka\Entity\Item::class => \Omeka\Entity\Item::class,
             \Omeka\Entity\Media::class => \Omeka\Entity\Media::class,
             'o:Resource' => \Omeka\Entity\Resource::class,
             'o:ItemSet' => \Omeka\Entity\ItemSet::class,
             'o:Item' => \Omeka\Entity\Item::class,
             'o:Media' => \Omeka\Entity\Media::class,
         ];
         if (isset($resourceEntityMap[$resourceName])) {
             return $resourceEntityMap[$resourceName];
         }
     }
}
