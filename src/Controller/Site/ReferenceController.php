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

        $view = new ViewModel();
        $view->setVariable('references', $slugs);
        $view->setVariable('types', array_keys($types));
        $view->setVariable('resourceName', $resourceName);
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

        $resourceName = $settings->get('reference_resource_name', 'resources');
        $references = $this->reference()->getList($slugData['term'], $slugData['type'], $resourceName);

        $output = $this->params()->fromQuery('output');
        if ($output === 'json') {
            $view = new JsonModel($references);
            return $view;
        }

        $view = new ViewModel();
        $view->setVariable('slug', $slug);
        $view->setVariable('references', $references);
        $view->setVariable('label', $slugData['label']);
        $view->setVariable('args', [
            'term' => $slugData['term'],
            'type' => $slugData['type'],
            'resource_name' => $resourceName,
        ]);
        $view->setVariable('options', [
            'query_type' => $settings->get('reference_query_type', 'eq'),
            'link_to_single' => $settings->get('reference_link_to_single', true),
            'total' => $settings->get('reference_total', true),
            'skiplinks' => $settings->get('reference_list_skiplinks', true),
            'headings' => $settings->get('reference_list_headings', true),
        ]);
        return $view;
    }

    public function treeAction()
    {
        $settings = $this->settings();
        if (!$settings->get('reference_tree_enabled')) {
            $this->notFoundAction();
            return;
        }

        // TODO Currently, the arguments are forced.
        $term = 'dcterms:subject';
        $type = 'properties';
        $resourceName = $settings->get('reference_resource_name', 'resources');

        $references = $this->reference()->getTree();

        $view = new ViewModel();
        $view->setVariable('references', $references);
        $view->setVariable('args', [
            'term' => $term,
            'type' => $type,
            'resource_name' => $resourceName,
        ]);
        $view->setVariable('options', [
            'query_type' => $settings->get('reference_query_type', 'eq'),
            'link_to_single' => $settings->get('reference_link_to_single', true),
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
