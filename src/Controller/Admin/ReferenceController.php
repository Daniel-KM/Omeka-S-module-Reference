<?php declare(strict_types=1);

namespace Reference\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class ReferenceController extends AbstractActionController
{
    public function browseAction()
    {
        $services = $this->getEvent()->getApplication()->getServiceManager();
        $easyMeta = $this->easyMeta();
        $conn = $services->get('Omeka\Connection');

        // Get all used property terms => ids and labels.
        $propertyIdsUsed = $easyMeta->propertyIdsUsed();
        $propertyLabels = $easyMeta->propertyLabels();

        // Get all used resource class terms => ids and labels.
        $classIdsUsed = $easyMeta->resourceClassIdsUsed();
        $classLabels = $easyMeta->resourceClassLabels();

        // Count distinct values per property.
        $propertyCounts = [];
        if ($propertyIdsUsed) {
            $ids = array_values($propertyIdsUsed);
            $sql = 'SELECT property_id, COUNT(DISTINCT value) AS total FROM value WHERE property_id IN (' . implode(',', $ids) . ') GROUP BY property_id';
            $stmt = $conn->executeQuery($sql);
            $rows = $stmt->fetchAllAssociative();
            foreach ($rows as $row) {
                $propertyCounts[(int) $row['property_id']] = (int) $row['total'];
            }
        }

        // Count resources per class.
        $classCounts = [];
        if ($classIdsUsed) {
            $ids = array_values($classIdsUsed);
            $sql = 'SELECT resource_class_id, COUNT(id) AS total FROM resource WHERE resource_class_id IN (' . implode(',', $ids) . ') GROUP BY resource_class_id';
            $stmt = $conn->executeQuery($sql);
            $rows = $stmt->fetchAllAssociative();
            foreach ($rows as $row) {
                $classCounts[(int) $row['resource_class_id']] = (int) $row['total'];
            }
        }

        // Build references list.
        $references = [];
        foreach ($propertyIdsUsed as $term => $id) {
            $references[] = [
                'term' => $term,
                'label' => $propertyLabels[$term] ?? $term,
                'type' => 'properties',
                'count' => $propertyCounts[$id] ?? 0,
            ];
        }
        foreach ($classIdsUsed as $term => $id) {
            $references[] = [
                'term' => $term,
                'label' => $classLabels[$term] ?? $term,
                'type' => 'resource_classes',
                'count' => $classCounts[$id] ?? 0,
            ];
        }

        // Filter by type.
        $filterType = $this->params()->fromQuery('filter_type', '');
        if ($filterType === 'properties') {
            $references = array_filter($references, fn($r) => $r['type'] === 'properties');
        } elseif ($filterType === 'resource_classes') {
            $references = array_filter($references, fn($r) => $r['type'] === 'resource_classes');
        }

        // Sort.
        $sortBy = $this->params()->fromQuery('sort_by', 'term');
        $sortOrder = $this->params()->fromQuery('sort_order', 'asc');
        $sortDir = strtolower($sortOrder) === 'desc' ? -1 : 1;
        usort($references, function ($a, $b) use ($sortBy, $sortDir) {
            $va = $a[$sortBy] ?? '';
            $vb = $b[$sortBy] ?? '';
            if ($sortBy === 'count') {
                return ($va - $vb) * $sortDir;
            }
            return strcasecmp((string) $va, (string) $vb) * $sortDir;
        });

        // Simple pagination.
        $page = (int) $this->params()->fromQuery('page', 1);
        $perPage = 25;
        $totalResults = count($references);
        $references = array_slice($references, ($page - 1) * $perPage, $perPage);

        $this->paginator($totalResults, $page, $perPage);

        return new ViewModel([
            'references' => $references,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'filterType' => $filterType,
        ]);
    }

    public function showAction()
    {
        $prefix = $this->params('prefix');
        $local = $this->params('local');
        $term = $prefix && $local ? $prefix . ':' . $local : $this->params('term');

        if (!$term) {
            return $this->notFoundAction();
        }

        $easyMeta = $this->easyMeta();

        // Detect type by first character of local name.
        [, $localName] = explode(':', $term);
        $first = mb_substr($localName, 0, 1);
        $type = ucfirst($first) === $first ? 'resource_classes' : 'properties';

        // Get label.
        if ($type === 'properties') {
            $labels = $easyMeta->propertyLabels($term);
        } else {
            $labels = $easyMeta->resourceClassLabels($term);
        }
        $label = $labels[$term] ?? $term;

        // Get the property or class id for building admin browse queries.
        if ($type === 'properties') {
            $fieldId = $easyMeta->propertyId($term);
        } else {
            $fieldId = $easyMeta->resourceClassId($term);
        }

        // Fetch all references with initials for headings.
        $referencesList = $this->references(['fields' => $term], [], [
            'resource_name' => 'resources',
            'sort_by' => 'alphabetic',
            'sort_order' => 'ASC',
            'initial' => true,
            'per_page' => 0,
            'page' => 1,
        ])->list();

        $first = reset($referencesList);
        $references = $first['o:references'] ?? [];
        $total = count($references);

        return new ViewModel([
            'term' => $term,
            'label' => $label,
            'type' => $type,
            'total' => $total,
            'fieldId' => $fieldId,
            'references' => $references,
        ]);
    }

    public function valuesAction()
    {
        $prefix = $this->params('prefix');
        $local = $this->params('local');
        $term = $prefix . ':' . $local;

        if (!$term || $term === ':') {
            return $this->notFoundAction();
        }

        $easyMeta = $this->easyMeta();

        [, $localName] = explode(':', $term);
        $first = mb_substr($localName, 0, 1);
        $type = ucfirst($first) === $first ? 'resource_classes' : 'properties';

        if ($type === 'properties') {
            $labels = $easyMeta->propertyLabels($term);
        } else {
            $labels = $easyMeta->resourceClassLabels($term);
        }
        $label = $labels[$term] ?? $term;

        $referencesList = $this->references(['fields' => $term], [], [
            'resource_name' => 'resources',
            'sort_by' => 'total',
            'sort_order' => 'DESC',
            'per_page' => 10000,
            'page' => 1,
        ])->list();

        // The key result depends on the internal logic, so use the first result.
        $first = reset($referencesList);
        $values = $first['o:references'] ?? [];

        $view = new ViewModel([
            'term' => $term,
            'label' => $label,
            'type' => $type,
            'values' => $values,
        ]);
        $view->setTerminal(true);
        return $view;
    }
}
