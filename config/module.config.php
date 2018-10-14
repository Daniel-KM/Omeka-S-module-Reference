<?php
namespace Reference;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'reference' => Service\ViewHelper\ReferenceFactory::class,
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
            Form\ReferenceIndexBlockForm::class => Form\ReferenceIndexBlockForm::class,
        ],
        'factories' => [
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
            Form\ReferenceBlockForm::class => Service\Form\ReferenceBlockFormFactory::class,
            Form\ReferenceTreeBlockForm::class => Service\Form\ReferenceTreeBlockFormFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Site\ReferenceController::class => Controller\Site\ReferenceController::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'reference' => Service\ControllerPlugin\ReferenceFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'reference' => [
                        'type' => \Zend\Router\Http\Literal::class,
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
                                'type' => \Zend\Router\Http\Segment::class,
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
                                        'type' => \Zend\Router\Http\Segment::class,
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
                        'type' => \Zend\Router\Http\Literal::class,
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
                ],
                'options' => [
                    'link_to_single' => true,
                    'heading' => 'Subjects', // @translate
                    'skiplinks' => true,
                    'headings' => true,
                    'total' => true,
                ],
            ],
            'referenceIndex' => [
                'args' => [
                    'terms' => ['dcterms:subject'],
                    'type' => 'properties',
                    'resource_name' => 'items',
                    'order' => ['alphabetic' => 'ASC'],
                    'query' => '',
                ],
                'options' => [
                    'heading' => 'Reference index', // @translate
                    'total' => true,
                ],
            ],
            'referenceTree' => [
                'args' => [
                    'term' => 'dcterms:subject',
                    'tree' => [],
                    'resource_name' => 'items',
                    'query' => '',
                ],
                'options' => [
                    'query_type' => 'eq',
                    'link_to_single' => true,
                    'heading' => 'Tree of subjects', // @translate
                    'total' => true,
                    'branch' => false,
                    'expanded' => true,
                ],
            ],
        ],
    ],
];
