<?php declare(strict_types=1);

namespace Reference\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Mvc\Controller\Plugin\Api;
use Reference\Mvc\Controller\Plugin\References as ReferencesPlugin;

class ReferenceTree extends AbstractPlugin
{
    /**
     * @param Api
     */
    protected $api;

    /**
     * @param ReferencesPlugin
     */
    protected $references;

    /**
     * @param Api $api
     * @param ReferencesPlugin $references
     */
    public function __construct(
        Api $api,
        ReferencesPlugin $references
    ) {
        $this->api = $api;
        $this->references = $references;
    }

    /**
     * Get the ReferenceTree object.
     *
     * @return self
     */
    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Convert a tree from string format to an array of texts with level.
     *
     * Example of a dash tree:
     *
     * Europe
     * - France
     * -- Paris
     * - United Kingdom
     * -- England
     * --- London
     * -- Scotland
     * Asia
     * - Japan
     *
     * Converted into:
     *
     * [
     *     0 => [Europe => 0],
     *     1 => [France => 1]
     *     2 => [Paris => 2]
     *     3 => United Kingdom => 1]
     *     4 => [England => 2]
     *     5 => [London => 3]
     *     6 => [Scotland => 2]
     *     7 => [Asia => 0]
     *     8 => [Japan => 1]
     * ]
     *
     * @param string $dashTree A tree with levels represented with dashes.
     * @return array Array with an associative array as value, containing text
     * as key and level as value (0 based).
     */
    public function convertTreeToLevels(string $dashTree): array
    {
        // The str_replace() allows to fix Apple copy/paste.
        $values = array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], $dashTree))));
        return array_reduce($values, function ($result, $item) {
            $first = substr($item, 0, 1);
            $space = strpos($item, ' ');
            $level = ($first !== '-' || $space === false) ? 0 : $space;
            $value = trim($level == 0 ? $item : substr($item, $space));
            $result[] = [$value => $level];
            return $result;
        }, []);
    }

    /**
     * Convert a tree from array format to string format.
     *
     * @param array $levels Array of arrays with text as key and level as value.
     * @return string
     */
    public function convertLevelsToTree(array $levels): string
    {
        $tree = array_map(function ($v) {
            $level = reset($v);
            $term = trim((string) key($v));
            return $level ? str_repeat('-', $level) . ' ' . $term : $term;
        }, $levels);
        return implode("\n", $tree);
    }

    /**
     * Convert a tree from flat array format to string format
     *
     * @param array $levels A flat array with text as key and level as value.
     * @return string
     */
    public function convertFlatLevelsToTree(array $levels): string
    {
        $tree = array_map(function ($v, $k) {
            return $v ? str_repeat('-', $v) . ' ' . trim($k) : trim($k);
        }, $levels, array_keys($levels));
        return implode("\n", $tree);
    }

    /**
     * Get a prepared tree of values.
     *
     * @uses http://www.jqueryscript.net/other/jQuery-Flat-Folder-Tree-Plugin-simplefolders.html
     *
     * Note: Sql searches are case insensitive, so the all the values must be
     * case-insisitively unique.
     *
     * @param array|string $referenceLevels References and levels to show as
     * array or dash tree.
     * @param array $query An Omeka search query to limit results.
     * @param array $options Options to display the references.
     * - term (string): Term or id to search (dcterms:subject by default).
     * - type (string): "properties" (default), "resource_classes", "item_sets"
     *   "resource_templates".
     * - resource_name: items (default), "item_sets", "media", "resources".
     * - branch: Managed terms are branches (path separated with " :: ")
     * - first (bool): Get the first value.
     * @return array.
     */
    public function getTree($referenceLevels, array $query = null, array $options = null): array
    {
        if (!is_array($referenceLevels)) {
            $referenceLevels = $this->convertTreeToLevels($referenceLevels);
        }

        if (empty($referenceLevels)) {
            return [];
        }

        $default = [
            'term' => 'dcterms:subject',
            'type' => 'properties',
            'resource_name' => 'items',
            'branch' => false,
            'first' => false,
        ];
        $options = $options ? $options + $default : $default;
        $options['initial'] = false;

        // Sql searches are case insensitive, so a convert should be done.
        $isBranch = $options['branch'];
        if ($isBranch) {
            $branches = [];
            $lowerBranches = [];
            $levels = [];
            foreach ($referenceLevels as $referenceLevel) {
                $level = reset($referenceLevel);
                $reference = key($referenceLevel);
                $levels[$level] = $reference;
                $branch = '';
                for ($i = 0; $i < $level; ++$i) {
                    $branch .= $levels[$i] . ' :: ';
                }
                $branch .= $reference;
                $branches[] = $branch;
                $lowerBranches[] = mb_strtolower($branch);
            }
            $options['values'] = $lowerBranches;
        }
        // Simple tree.
        else {
            $lowerReferences = array_map(function ($v) {
                return mb_strtolower((string) key($v));
            }, $referenceLevels);
            $options['values'] = $lowerReferences;
        }
        $ref = $this->references;
        $totals = $ref([$options['term']], $query, $options)->list();
        $totals = isset($totals[$options['term']]) ? $totals[$options['term']]['o:references'] : [];

        $lowerValues = [];
        foreach ($totals as $value) {
            $key = mb_strtolower((string) $value['val']);
            unset($value['val']);
            $lowerValues[$key] = $value;
        }

        // Merge of the two references arrays.
        $result = [];
        $lowers = $isBranch ? $lowerBranches : $lowerReferences;
        foreach ($referenceLevels as $key => $referenceLevel) {
            $level = reset($referenceLevel);
            $reference = key($referenceLevel);
            $lower = $lowers[$key];
            if (isset($lowerValues[$lower])) {
                $referenceData = [
                    'total' => $lowerValues[$lower]['total'],
                    'first' => $options['link_to_single'] ? $lowerValues[$lower]['first'] : null,
                ];
            } else {
                $referenceData = [
                    'total' => 0,
                    'first' => null,
                ];
            }
            $referenceData['val'] = $reference;
            $referenceData['level'] = $level;
            if ($isBranch) {
                $referenceData['branch'] = $branches[$key];
            }
            $result[] = $referenceData;
        }

        return $result;
    }
}
