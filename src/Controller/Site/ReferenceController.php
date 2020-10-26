<?php declare(strict_types=1);

namespace Reference\Controller\Site;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

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

        return new ViewModel([
            'site' => $this->currentSite(),
            'slugs' => $slugs,
            'types' => array_keys($types),
            'resourceName' => $resourceName,
            'query' => $query,
        ]);
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

        return new ViewModel([
            'total' => $total,
            'label' => $slugData['label'],
            'term' => $term,
            'query' => $query,
            'options' => [
                'resource_name' => $resourceName,
                'per_page' => 0,
                'page' => 1,
                'sort_by' => 'alphabetic',
                'sort_order' => 'ASC',
                'link_to_single' => (bool) $settings->get('reference_link_to_single', true),
                'total' => (bool) $settings->get('reference_total', true),
                'skiplinks' => (bool) $settings->get('reference_list_skiplinks', true),
                'headings' => (bool) $settings->get('reference_list_headings', true),
            ],
            'slug' => $slug,
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
