<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 'HD Development',
    'description' => 'HD Development - enable FE developers to work inside TYPO3 without special knowledge',
    'category' => 'fe',
    'author' => 'Martin Pribyl',
    'author_email' => 'developer@hyperdigital.de',
    'author_company' => 'Hyperdigital',
    'shy' => '',
    'priority' => '',
    'module' => '',
    'state' => 'alpha',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '1.0.3',
    'constraints' => [
        'depends' => [
            'typo3' => '10.0.0-10.99.99 || 11.0.0-11.99.99 || 12.0.0-12.99.99 || 13.0.0-13.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
);
