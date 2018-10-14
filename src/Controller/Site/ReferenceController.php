<?php
namespace Reference\Controller\Site;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
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
            $this->notFoundAction();
            return;
        }

        $resourceName = $settings->get('reference_resource_name', 'resources');

        $query = ['site_id' => $this->currentSite()->id()];

        $view = new ViewModel();
        $view->setVariable('references', $slugs);
        $view->setVariable('types', array_keys($types));
        $view->setVariable('resourceName', $resourceName);
        $view->setVariable('query', $query);
        $view->setVariable('site', $this->currentSite());
        return $view;
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
            $this->notFoundAction();
            return;
        }
        $slugData = $slugs[$slug];

        $term = $slugData['term'];
        $type = $slugData['type'];
        $resourceName = $settings->get('reference_resource_name', 'resources');
        $order = ['value.value' => 'ASC'];
        $query = ['site_id' => $this->currentSite()->id()];

        // @deprecated Use format ".json" instead of query "?output=json". Will be reomoved in 3.4.12.
        $output = $this->params()->fromRoute('output') ?: $this->params()->fromQuery('output');
        switch ($output) {
            case 'json':
                $references = $this->reference()->getList($term, $type, $resourceName, $order, $query);
                $view = new JsonModel($references);
                return $view;
        }

        $total = $this->reference()->count($term, $type, $resourceName, $query);

        $view = new ViewModel();
        $view->setVariable('total', $total);
        $view->setVariable('label', $slugData['label']);
        $view->setVariable('term', $term);
        $view->setVariable('args', [
            'type' => $type,
            'resource_name' => $resourceName,
            'order' => $order,
            'query' => $query,
        ]);
        $view->setVariable('options', [
            'link_to_single' => $settings->get('reference_link_to_single', true),
            'total' => $settings->get('reference_total', true),
            'skiplinks' => $settings->get('reference_list_skiplinks', true),
            'headings' => $settings->get('reference_list_headings', true),
        ]);
        $view->setVariable('slug', $slug);
        return $view;
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
        $view->setVariable('references', $references);
        $view->setVariable('args', [
            'term' => $term,
            'type' => $type,
            'resource_name' => $resourceName,
            'query' => $query,
        ]);
        $view->setVariable('options', [
            'query_type' => $settings->get('reference_tree_query_type', 'eq'),
            'link_to_single' => $settings->get('reference_link_to_single', true),
            'branch' => $settings->get('reference_tree_branch', false),
            'total' => $settings->get('reference_total', true),
            'expanded' => $settings->get('reference_tree_expanded', true),
        ]);
        return $view;
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
