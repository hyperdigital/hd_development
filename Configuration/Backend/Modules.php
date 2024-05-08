<?php

return [
    'hd_development_templates' => [
        'parent' => 'file',
        'position' => ['after' => 'filelist'],
        'access' => 'group',
        'iconIdentifier' => 'hd_development',
        'navigationComponentId' => '',
        'inheritNavigationComponentFromMainModule' => false,
        'labels' => 'LLL:EXT:hd_development/Resources/Private/Language/locallang_module_templates.xlf',
        'extensionName' => 'HdDevelopment',
        'path' => '/module/web/HdDevelopmentTemplates',
        'controllerActions' => [
            \Hyperdigital\HdDevelopment\Controller\Be\TemplatesController::class => [
                'index'
            ]
        ],
    ],
];