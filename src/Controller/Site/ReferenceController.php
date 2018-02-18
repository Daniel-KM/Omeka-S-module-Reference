<?php
namespace Reference\Controller\Site;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

class ReferenceController extends AbstractActionController
{
    public function browseAction()
    {
        $slugs = $this->settings()->get('reference_slugs') ?: [];

        // Remove disabled slugs.
        foreach ($slugs as $slug => $slugData) {
            if (empty($slugData['active'])) {
                unset($slugs[$slug]);
            }
        }
        if (empty($slugs)) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        $types = [];
        foreach ($slugs as $slug => $slugData) {
            $types[$slugData['type']] = true;
        }

        $view = new ViewModel();
        $view->setVariable('references', $slugs);
        $view->setVariable('types', $types);
        return $view;
    }

    public function listAction()
    {
        $slugs = $this->settings()->get('reference_slugs') ?: [];
        if (empty($slugs)) {
            return $this->forwardToItemBrowse();
        }

        $slug = $this->params('slug');
        if (!isset($slugs[$slug]) || empty($slugs[$slug]['active'])) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        $references = $this->reference()->getList($slug);

        $output = $this->params()->fromQuery('output');;

        if ($output === 'json') {
            $view = new JsonModel($references);
            return $view;
        }

        $view = new ViewModel();
        $view->setVariable('slug', $slug);
        $view->setVariable('slugData', $slugs[$slug]);
        $view->setVariable('references', $references);
        return $view;
    }

    public function treeAction()
    {
        if (!$this->settings()->get('reference_tree_enabled')) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        $subjects = $this->reference()->getTree();

        $view = new ViewModel();
        $view->setVariable('subjects', $slug);
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
