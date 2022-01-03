<?php declare(strict_types=1);

namespace Reference;

return [
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
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
            'references' => Service\ViewHelper\ReferencesFactory::class,
        ],
        'aliases' => [
            /** @deprecated Since release for Omeka 3. */
            'reference' => 'references',
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
            Form\Element\DoubleArrayTextarea::class => Form\Element\DoubleArrayTextarea::class,
            Form\Element\OptionalMultiCheckbox::class => Form\Element\OptionalMultiCheckbox::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
            Form\ReferenceFieldset::class => Form\ReferenceFieldset::class,
            Form\ReferenceIndexFieldset::class => Form\ReferenceIndexFieldset::class,
            Form\ReferenceTreeFieldset::class => Form\ReferenceTreeFieldset::class,
        ],
        'aliases' => [
            'DoubleArrayTextarea' => Form\Element\DoubleArrayTextarea::class,
            'OptionalMultiCheckbox' => Form\Element\OptionalMultiCheckbox::class,
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
            'currentReferenceMetadata' => Service\ControllerPlugin\CurrentReferenceMetadataFactory::class,
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
                'args' => [
                    'fields' => [
                        'dcterms:subject',
                    ],
                    'type' => 'properties',
                    'resource_name' => 'items',
                    'order' => ['alphabetic' => 'ASC'],
                    'query' => '',
                    'languages' => [],
                ],
                'options' => [
                    'heading' => 'Subjects', // @translate
                    'by_initial' => false,
                    'link_to_single' => true,
                    'custom_url' => false,
                    'skiplinks' => true,
                    'headings' => true,
                    'total' => true,
                    'list_by_max' => 0,
                    'subject_property' => null,
                    'template' => '',
                ],
            ],
            'referenceIndex' => [
                'args' => [
                    'fields' => [
                        'dcterms:subject',
                    ],
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
                'fields' => [
                    'dcterms:subject',
                ],
                'tree' => [],
                'resource_name' => 'items',
                'query' => [],
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
