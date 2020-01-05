<?php
namespace Reference\Controller\Site;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class ReferenceController extends AbstractActionController
{
    public function browseAction()
    {
        $settings = $this->settings();
        $slugs = $settings->get('reference_slugs') ?: [];
        $types = [];

        // Remove disabled slugs and prepare types.
        foreach ($slugs as $slug => $slugData) {
            if (empty($slugData['active'])) {
                unset($slugs[$slug]);
            } else {
                $types[$slugData['type']] = true;
            }
        }

        if (empty($slugs)) {
            return $this->notFoundAction();
        }

        $resourceName = $settings->get('reference_resource_name', 'items');

        $query = ['site_id' => $this->currentSite()->id()];

        $view = new ViewModel();
        return $view
            ->setVariable('site', $this->currentSite())
            ->setVariable('slugs', $slugs)
            ->setVariable('types', array_keys($types))
            ->setVariable('resourceName', $resourceName)
            ->setVariable('query', $query)
        ;
    }

    public function listAction()
    {
        $settings = $this->settings();
        $slugs = $settings->get('reference_slugs') ?: [];
        if (empty($slugs)) {
            return $this->forwardToItemBrowse();
        }

        $slug = $this->params('slug');
        if (!isset($slugs[$slug]) || empty($slugs[$slug]['active'])) {
            return $this->notFoundAction();
        }
        $slugData = $slugs[$slug];

        $term = $slugData['term'];
        $resourceName = $settings->get('reference_resource_name', 'items');

        $query = ['site_id' => $this->currentSite()->id()];

        $total = $this->references([$term], $query, ['resource_name' => $resourceName])->count();
        $total = reset($total);

        $view = new ViewModel();
        return $view
            ->setVariable('total', $total)
            ->setVariable('label', $slugData['label'])
            ->setVariable('term', $term)
            ->setVariable('query', $query)
            ->setVariable('options', [
                'resource_name' => $resourceName,
                'per_page' => 0,
                'page' => 1,
                'sort_by' => 'alphabetic',
                'sort_order' => 'ASC',
                'link_to_single' => (bool) $settings->get('reference_link_to_single', true),
                'total' => (bool) $settings->get('reference_total', true),
                'skiplinks' => (bool) $settings->get('reference_list_skiplinks', true),
                'headings' => (bool) $settings->get('reference_list_headings', true),
            ])
            ->setVariable('slug', $slug);
    }

    public function treeAction()
    {
        $settings = $this->settings();
        if (!$settings->get('reference_tree_enabled')) {
            $this->notFoundAction();
            return;
        }

        $term = $settings->get('reference_tree_term', 'dcterms:subject');
        $type = 'properties';
        $resourceName = $settings->get('reference_resource_name', 'resources');
        $query = ['site_id' => $this->currentSite()->id()];

        $references = $settings->get('reference_tree_hierarchy', []);

        $view = new ViewModel();
        return $view
            ->setVariable('references', $references)
            ->setVariable('args', [
                'term' => $term,
                'type' => $type,
                'resource_name' => $resourceName,
                'query' => $query,
            ])
            ->setVariable('options', [
                'query_type' => $settings->get('reference_tree_query_type', 'eq'),
                'link_to_single' => $settings->get('reference_link_to_single', true),
                'branch' => $settings->get('reference_tree_branch', false),
                'total' => $settings->get('reference_total', true),
                'expanded' => $settings->get('reference_tree_expanded', true),
            ]);
    }

    protected function forwardToItemBrowse()
    {
        return $this->forward()->dispatch('Omeka\Controller\Site\Item', [
            '__NAMESPACE__' => 'Omeka\Controller\Site',
            '__SITE__' => true,
            'controller' => 'Omeka\Controller\Site\Item',
            'action' => 'browse',
            'site-slug' => $this->params('site-slug'),
        ]);
    }
}
