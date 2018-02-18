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

        $view = new ViewModel();
        $view->setVariable('references', $slugs);
        $view->setVariable('types', array_keys($types));
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
            $this->notFoundAction();
            return;
        }
        $slugData = $slugs[$slug];

        // TODO Currently, the "items" are forced.
        $resourceName = 'items';
        $references = $this->reference()->getList($slugData['id'], $slugData['type'], $resourceName);
        $output = $this->params()->fromQuery('output');;

        if ($output === 'json') {
            $view = new JsonModel($references);
            return $view;
        }

        $view = new ViewModel();
        $view->setVariable('slug', $slug);
        $view->setVariable('property', $slugData['id']);
        $view->setVariable('type', $slugData['type']);
        $view->setVariable('label', $slugData['label']);
        $view->setVariable('resourceName', $resourceName);
        $view->setVariable('references', $references);
        return $view;
    }

    public function treeAction()
    {
        if (!$this->settings()->get('reference_tree_enabled')) {
            $this->notFoundAction();
            return;
        }

        $subjects = $this->reference()->getTree();

        $view = new ViewModel();
        $view->setVariable('subjects', $subjects);
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
