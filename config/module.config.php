<?php declare(strict_types=1);

namespace Reference;

return [
    'entity_manager' => [
        'functions' => [
            'string' => [
                'any_value' => \DoctrineExtensions\Query\Mysql\AnyValue::class,
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            // Override theme factory to inject module pages and block templates.
            // Copied in BlockPlus, Reference, Timeline.
            'Omeka\Site\ThemeManager' => Service\ThemeManagerFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'references' => Service\ViewHelper\ReferencesFactory::class,
        ],
    ],
    'page_templates' => [
    ],
    'block_templates' => [
        'reference' => [
            'reference-grid' => 'Reference grid', // @translate
            'reference-index' => 'Reference (index)', // @translate
        ],
    ],
    'block_layouts' => [
        'factories' => [
            'reference' => Service\BlockLayout\ReferenceFactory::class,
            'referenceTree' => Service\BlockLayout\ReferenceTreeFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\Element\DoubleArrayTextarea::class => Form\Element\DoubleArrayTextarea::class,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
        ],
        'factories' => [
            Form\ReferenceFieldset::class => Service\Form\ReferenceFieldsetFactory::class,
            Form\ReferenceTreeFieldset::class => Service\Form\ReferenceFieldsetFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Site\ReferenceController::class => Controller\Site\ReferenceController::class,
        ],
        'factories' => [
            Controller\ApiController::class => Service\Controller\ApiControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'references' => Service\ControllerPlugin\ReferencesFactory::class,
            'referenceTree' => Service\ControllerPlugin\ReferenceTreeFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'reference' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/reference',
                            'defaults' => [
                                '__NAMESPACE__' => 'Reference\Controller\Site',
                                'controller' => Controller\Site\ReferenceController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'list' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:slug',
                                    'constraints' => [
                                        'slug' => '[^.]+',
                                    ],
                                    'defaults' => [
                                        'action' => 'list',
                                    ],
                                ],
                                'may_terminate' => true,
                                'child_routes' => [
                                    'output' => [
                                        'type' => \Laminas\Router\Http\Segment::class,
                                        'options' => [
                                            'route' => '.:output',
                                            'constraints' => [
                                                'output' => 'json',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'api' => [
                'child_routes' => [
                    'reference' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/references[/:resource]',
                            'constraints' => [
                                'resource' => 'items|item_sets|media|annotations',
                            ],
                            'defaults' => [
                                'controller' => Controller\ApiController::class,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'reference' => [
        'site_settings' => [
            'reference_page_title' => '',
            'reference_resource_name' => 'items',
            'reference_options' => [
                'headings',
                'skiplinks',
                'total',
                'link_to_single',
                // 'custom_url',
            ],
            // Pages ("properties" or "resource_classes") to provide, by slug.
            'reference_slugs' => [
                'dcterms-subject' => [
                    'term' => 'dcterms:subject',
                    'label' => 'Subject',
                ],
            ],
        ],
        // Default for blocks.
        'block_settings' => [
            'reference' => [
                'fields' => [
                    'dcterms:subject',
                ],
                'type' => 'properties',
                'resource_name' => 'items',
                'query' => [],
                'languages' => [],
                'sort_by' => 'alphabetic',
                'sort_order' => 'asc',
                'by_initial' => false,
                'search_config' => '',
                'link_to_single' => true,
                'custom_url' => false,
                'skiplinks' => true,
                'headings' => true,
                'total' => true,
                'url_argument_reference' => false,
                'thumbnail' => false,
                'list_by_max' => 0,
                'subject_property' => null,
            ],
            'referenceTree' => [
                'fields' => [
                    'dcterms:subject',
                ],
                'tree' => [],
                'resource_name' => 'items',
                'query' => [],
                'query_type' => 'eq',
                'search_config' => '',
                'link_to_single' => true,
                'custom_url' => false,
                'total' => true,
                'url_argument_reference' => false,
                'thumbnail' => false,
                'branch' => false,
                'expanded' => true,
            ],
        ],
    ],
];
