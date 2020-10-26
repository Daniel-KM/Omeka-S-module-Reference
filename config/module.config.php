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
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'reference' => Service\ViewHelper\ReferenceFactory::class,
            'references' => Service\ViewHelper\ReferencesFactory::class,
        ],
    ],
    'block_layouts' => [
        'factories' => [
            'reference' => Service\BlockLayout\ReferenceFactory::class,
            'referenceIndex' => Service\BlockLayout\ReferenceIndexFactory::class,
            'referenceTree' => Service\BlockLayout\ReferenceTreeFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ReferenceFieldset::class => Form\ReferenceFieldset::class,
            Form\ReferenceIndexFieldset::class => Form\ReferenceIndexFieldset::class,
            Form\ReferenceTreeFieldset::class => Form\ReferenceTreeFieldset::class,
        ],
        'factories' => [
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
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
            'reference' => Service\ControllerPlugin\ReferenceFactory::class,
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
                    'reference_tree' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/reference-tree',
                            'defaults' => [
                                '__NAMESPACE__' => 'Reference\Controller\Site',
                                'controller' => Controller\Site\ReferenceController::class,
                                'action' => 'tree',
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
        'config' => [
            'reference_resource_name' => 'items',
            'reference_link_to_single' => true,
            'reference_custom_url' => false,
            'reference_total' => true,
            'reference_search_list_values' => false,
            // Pages ("properties" or "resource_classes") to provide, by slug.
            'reference_slugs' => [
                // 3 is the property id of Dublin Core Terms Subject, forced during install.
                'dcterms:subject' => [
                    'type' => 'properties',
                    'term' => 3,
                    'label' => 'Subject',
                    'active' => true,
                ],
            ],
            'reference_list_skiplinks' => true,
            'reference_list_headings' => true,
            'reference_tree_enabled' => false,
            'reference_tree_term' => 'dcterms:subject',
            'reference_tree_hierarchy' => [],
            'reference_tree_branch' => false,
            'reference_tree_query_type' => 'eq',
            'reference_tree_expanded' => true,
        ],
        // Default for blocks.
        'block_settings' => [
            'reference' => [
                'args' => [
                    'term' => 'dcterms:subject',
                    'type' => 'properties',
                    'resource_name' => 'items',
                    'order' => ['alphabetic' => 'ASC'],
                    'query' => '',
                    'languages' => [],
                ],
                'options' => [
                    'link_to_single' => true,
                    'custom_url' => false,
                    'heading' => 'Subjects', // @translate
                    'skiplinks' => true,
                    'headings' => true,
                    'total' => true,
                    'subject_property' => null,
                    'template' => '',
                ],
            ],
            'referenceIndex' => [
                'args' => [
                    'terms' => ['dcterms:subject'],
                    'type' => 'properties',
                    'resource_name' => 'items',
                    'order' => ['alphabetic' => 'ASC'],
                    'query' => '',
                    'languages' => [],
                ],
                'options' => [
                    'heading' => 'Reference index', // @translate
                    'total' => true,
                    'template' => '',
                ],
            ],
            'referenceTree' => [
                'heading' => 'Tree of subjects', // @translate
                'term' => 'dcterms:subject',
                'tree' => [],
                'resource_name' => 'items',
                'query' => '',
                'query_type' => 'eq',
                'link_to_single' => true,
                'custom_url' => false,
                'total' => true,
                'branch' => false,
                'expanded' => true,
                'template' => '',
            ],
        ],
    ],
];
