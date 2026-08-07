<?php

defined('TYPO3') or die();

(static function (): void {
    $signature = \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
        'HdDevelopment',
        'ContentElement',
        'Development: Content Element',
        '',
        'plugins',
        '',
        'FILE:EXT:hd_development/Configuration/FlexForms/contentelement.xml'
    );

    // Only modify showitem - preserve columnsOverrides set by registerPlugin() for FlexForm
    $GLOBALS['TCA']['tt_content']['types'][$signature]['showitem'] = '
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
            --palette--;;general,
            --palette--;;headers,
            pi_flexform,
        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.appearance,
            --palette--;;frames,
            --palette--;;appearanceLinks,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
            --palette--;;language,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
            --palette--;;hidden,
            --palette--;;access,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:categories,
            categories,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes,
            rowDescription,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended,
    ';
})();
