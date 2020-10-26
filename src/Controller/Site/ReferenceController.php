<?php declare(strict_types=1);

namespace Reference\Controller\Site;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class ReferenceController extends AbstractActionController
{
    public function browseAction()
    {
        $settings = $this->siteSettings();
        $slugs = $settings->get('reference_slugs') ?: [];
        if (empty($slugs)) {
            return $this->notFoundAction();
        }

        // Get used types (resource classes or properties) to simplify display.
        $types = [];
        foreach ($slugs as &$slugData) {
            list(, $local) = explode(':', $slugData['term']);
            $first = mb_substr($local, 0, 1);
            $type = ucfirst($first) === $first ? 'resource_classes' : 'properties';
            $slugData['type'] = $type;
            $types[$type] = true;
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
        $settings = $this->siteSettings();
        $slugs = $settings->get('reference_slugs') ?: [];
        if (empty($slugs)) {
            return $this->forwardToItemBrowse();
        }

        $slug = $this->params('slug');
        if (empty($slugs[$slug])) {
            return $this->notFoundAction();
        }
        $slugData = $slugs[$slug];

        $term = $slugData['term'];
        $resourceName = $settings->get('reference_resource_name', 'items');

        $query = ['site_id' => $this->currentSite()->id()];

        $total = $this->references([$term], $query, ['resource_name' => $resourceName])->count();
        $total = reset($total);

        $options = $settings->get('reference_options') ?: [];
        $options = array_fill_keys($options, true) + [
            'headings' => false,
            'skiplinks' => false,
            'total' => false,
            'link_to_single' => false,
            'custom_url' => false,
            'resource_name' => $resourceName,
            'per_page' => 0,
            'page' => 1,
            'sort_by' => 'alphabetic',
            'sort_order' => 'ASC',
        ];

        return new ViewModel([
            'site' => $this->currentSite(),
            'slug' => $slug,
            'total' => $total,
            'label' => $slugData['label'],
            'term' => $term,
            'query' => $query,
            'options' => $options,
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
