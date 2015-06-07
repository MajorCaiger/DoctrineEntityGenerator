<?php

return array(
    'console' => array(
        'router' => array(
            'routes' => array(
                'generate' => array(
                    'options' => array(
                        'route'    => 'generate',
                        'defaults' => array(
                            'controller' => 'DoctrineEntityGenerator\Generate',
                            'action'     => 'generate'
                        )
                    )
                )
            )
        )
    ),
    'controller' => array(
        'invokables' => array(
            'DoctrineEntityGenerator\Generate' => 'MajorCaiger\DoctrineEntityGenerator\GenerateController',
        )
    ),
    'doctrine_entity_generator' => array(
        'namespace' => null, // Define a base namespace for the entities
        'directory' => null, // Define a base directory for the entities
        'mapping_files' => __DIR__ . '/../data/mapping_files/',
        // A list of entity overrides
        'entity_overrides' => array(
            'table_name' => array(
                'column_name' => array(
                    // List of overrides
                )
            )
        ),
        // Mapping config
        'mapping_config' => array(

        )
    ),
    'service_manager' => array(
        'invokables' => array(
            'DoctrineEntityGenerator' => 'MajorCaiger\DoctrineEntityGenerator\Service\Generator',
        ),
    ),
    'view_manager' => array(
        'template_path_stack' => array(

        ),
    ),
);
