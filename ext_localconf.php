<?php
declare(strict_types=1);

defined('TYPO3') or die();

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'HdDevelopment',
    'ContentElement',
    [\Hyperdigital\HdDevelopment\Controller\ContentElementController::class => 'show'],
    [\Hyperdigital\HdDevelopment\Controller\ContentElementController::class => 'show'],
);
