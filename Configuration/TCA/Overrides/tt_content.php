<?php

defined('TYPO3') or die();

(static function (): void {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
        'HdDevelopment',
        'ContentElement',
        'Development: Content Element',
    );

    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['hddevelopment_contentelement'] = 'pi_flexform';

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
        'hddevelopment_contentelement',
        'FILE:EXT:hd_development/Configuration/FlexForms/contentelement.xml'
    );
})();
