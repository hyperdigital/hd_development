<?php

defined('TYPO3') or die();

(static function (): void {
    $signatureV13 = \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
        'HdDevelopment',
        'ContentElement',
        'Development: Content Element',
    );

    $version = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Information\Typo3Version::class);

    if ($version->getMajorVersion() >= 13) {
        $signature = $signatureV13;
    } else {
        $signature = 'hddevelopment_contentelement';
    }

    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$signature] = 'pi_flexform';

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
        $signature,
        'FILE:EXT:hd_development/Configuration/FlexForms/contentelement.xml'
    );
})();
