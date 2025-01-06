<?php declare(strict_types=1);

namespace Reference\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Reference\Form\ReferenceFieldset;
use Reference\Form\ReferenceTreeFieldset;

class ReferenceFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /**
         * @see \AdvancedSearch\Service\Form\SearchingFormFieldsetFactory
         * Adapted:
         * @see \BlockPlus\Service\Form\SearchFormFieldsetFactory
         * @see \Reference\Service\Form\ReferenceFieldsetFactory
         */

        $configs = [];

        if ($services->get('Omeka\ApiAdapterManager')->has('search_configs')) {
            $siteSettings = $services->get('Omeka\Settings\Site');
            $availableSearchConfigs = $siteSettings->get('advancedsearch_configs', []);

            /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation[] $searchConfigs */
            $api = $services->get('Omeka\ApiManager');
            $searchConfigs = $api->search('search_configs', ['id' => $availableSearchConfigs])->getContent();

            foreach ($searchConfigs as $searchConfig) {
                $configs[$searchConfig->id()] = sprintf(
                    '%s (/%s)',
                    $searchConfig->name(),
                    // Support of old version of module AdvancedSearch.
                    method_exists($searchConfig, 'path') ? $searchConfig->path() : $searchConfig->slug()
                );
            }

            // Set the main search config first and as default.
            $default = $siteSettings->get('advancedsearch_main_config') ?: reset($availableSearchConfigs);
            if (isset($configs[$default])) {
                $configs = [$default => $configs[$default]] + $configs;
            }
        }

        $classes = [
            'referenceFieldset' => ReferenceFieldset::class,
            'referenceTreeFieldset' => ReferenceTreeFieldset::class,
            ReferenceFieldset::class => ReferenceFieldset::class,
            ReferenceTreeFieldset::class => ReferenceFieldset::class,
        ];

        $form = $classes[$requestedName];
        $form = new $form(null, $options ?? []);
        return $form
            ->setSearchConfigs($configs);
    }
}
