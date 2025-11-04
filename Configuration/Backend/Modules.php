<?php

return [
    'hd_development_documentations' => [
        'parent' => 'file',
        'position' => ['after' => 'filelist'],
        'access' => 'group',
        'iconIdentifier' => 'hd_development_documentation',
        'navigationComponentId' => '',
        'inheritNavigationComponentFromMainModule' => false,
        'labels' => 'LLL:EXT:hd_development/Resources/Private/Language/locallang_module_documentation.xlf',
        'extensionName' => 'HdDevelopment',
        'path' => '/module/web/HdDevelopmentDocumentations',
        'controllerActions' => [
            \Hyperdigital\HdDevelopment\Controller\Be\DocumentationController::class => [
                'index','documentation'
            ]
        ],
    ],
    'hd_development_styleguide' => [
        'parent' => 'file',
        'position' => ['after' => 'filelist'],
        'access' => 'group',
        'iconIdentifier' => 'hd_development_styleguide',
        'navigationComponentId' => '',
        'inheritNavigationComponentFromMainModule' => false,
        'labels' => 'LLL:EXT:hd_development/Resources/Private/Language/locallang_module_styleguide.xlf',
        'extensionName' => 'HdDevelopment',
        'path' => '/module/web/HdDevelopmentStyleguide',
        'controllerActions' => [
            \Hyperdigital\HdDevelopment\Controller\Be\StyleguideController::class => [
                'index', 'getSettingsIndex', 'getSettingsPage', 'runSync'
            ]
        ],
    ],
];
