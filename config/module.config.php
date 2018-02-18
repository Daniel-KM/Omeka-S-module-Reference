<?php
namespace Reference;

return [
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'reference' => Service\ViewHelper\ReferenceFactory::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
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
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/references',
                            'defaults' => [
                                '__NAMESPACE__' => 'Reference\Controller\Site',
                                'controller' => Controller\Site\ReferenceController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'list' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:slug',
                                    'defaults' => [
                                        'action' => 'list',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'reference_tree' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/subjects/tree',
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
                'base_dir' => __DIR__ . '/../language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'reference' => [
        'settings' => [
            'reference_slugs' => [
                // 3 is the property id of Dublin Core Terms Subject, forced during install.
                'subject' => [
                    'id' => 3,
                    'type' => 'properties',
                    'label' => 'Subject',
                    'active' => true,
                ],
            ],
            'reference_list_skiplinks' => true,
            'reference_list_headings' => true,
            'reference_link_to_single' => true,
            'reference_tree_enabled' => false,
            'reference_tree_expanded' => true,
            'reference_tree_hierarchy' => '',
            'reference_query_type' => 'eq',
        ],
    ],
];
