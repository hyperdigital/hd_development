<?php
declare(strict_types=1);

namespace Hyperdigital\HdDevelopment\Controller\Be;

use Doctrine\DBAL\Types\Types;
use Hyperdigital\HdDevelopment\Service\StyleguideService;
use Smalot\PdfParser\Page;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Extensionmanager\Utility\ListUtility;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\Exception\FileExistsException;
use TYPO3\CMS\Core\Resource\Exception\FileOperationException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFileWritePermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileNameException;

class StyleguideController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    protected $pageRepository;
    protected $moduleTemplateFactory;
    protected $moduleTemplate;

    protected $ignoreTtContentFields = [
        'rowDescription', 'pid', 'tstamp', 'crdate', 'cruser_id', 'deleted', 'hidden', 'starttime', 'endtime',
        'fe_group', 'editlock', 'sys_language_uid', 'l18n_parent', 'l10n_source', 'l10n_state',
        't3_origuid', 'l18n_diffsource', 't3ver_oid', 't3ver_id', 'l10n_state', 't3ver_label', 't3ver_wsid',
        't3ver_state', 't3ver_stage', 't3ver_count', 't3ver_tstamp', 't3ver_move_id', 'l10nmgr_language_restriction',
        'l10n_cfg','hd_dev_styleguide', 'l10n_parent', 'l10n_diffsource'
    ];

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory
    )
    {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->uriBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder::class);
    }

    public function initializeAction():void
    {
        parent::initializeAction();

        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
    }

    public function indexAction()
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        $source = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('hd_development', 'styleguideSource');
        if (!empty($source)) {
            $path = GeneralUtility::getFileAbsFileName($source);
            if (file_exists($path)) {
                if (is_file($path)) {
                    $styleguides = include($path);
                } else {
                    $styleguides = [];
                    foreach(scandir($path) as $filepath) {
                        if (is_file($path . DIRECTORY_SEPARATOR . $filepath)) {
                            $styleguides = array_merge_recursive($styleguides, include($path . DIRECTORY_SEPARATOR . $filepath));
                        }
                    }
                }

                $moduleTemplate->assign('styleguides', $styleguides);
            }
        }

        return $moduleTemplate->renderResponse('Be/Styleguide/Index');
    }

    public function runSyncAction()
    {
        $styleguideService = GeneralUtility::makeInstance(StyleguideService::class);
        $styleguideService->syncStyleguides();

        return $this->redirect('index');
    }

    public function getSettingsIndexAction()
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        return $moduleTemplate->renderResponse('Be/Styleguide/SettingsIndex');
    }

    public function getSettingsPageAction()
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $elements = '';
        if ($this->request->hasArgument('source')) {
            $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
            $page = $pageRepository->getPage((int) $this->request->getArgument('source'), true);
            if ($page) {
                if (!empty($page['hd_dev_styleguide'])) {
                    $page['slug'] = $page['hd_dev_styleguide'];
                } else {
                    $page['slug'] = str_replace('/', '-', trim($page['slug'], '/'));
                }

                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('tt_content');

                $rows = $queryBuilder
                    ->select('*')
                    ->from('tt_content')
                    ->where(
                        $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($page['uid'], Types::INTEGER)),
                        $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Types::INTEGER)),
                        $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, Types::INTEGER))
                    )
                    ->orderBy('sorting')
                    ->executeQuery()
                    ->fetchAllAssociative();

                $elements = [];
                foreach($rows as $row) {
                    $newRow = [];
                    foreach ($row as $key => $value) {
                        if (in_array($key, $this->ignoreTtContentFields)) {
                            continue;
                        }
                        
                        if(!empty($GLOBALS['TCA']['tt_content']['columns'][$key])) {
                            $newRow[$key] = $this->getRecursiveValue('tt_content', $key, $value, $row);
                        } else if (in_array($key, ['sorting'])) {
                            $newRow[$key] = $value;
                        }
                    }

                    $elements[$page['slug'] .'_' .$row['uid']] = $newRow;
                }
                $elements = $this->pretty_var([
                    $page['slug'] => [
                        'title' => $page['title'],
                        'elements' => $elements
                    ]
                ]);

            }

        }

        $moduleTemplate->assign('elements', $elements);

        return $moduleTemplate->renderResponse('Be/Styleguide/SettingsPage');
    }

    protected function pretty_var($myArray){

        return str_replace(array("\n"," "),array("<br>","&nbsp;"), htmlentities(var_export($myArray,true)));

    }
    
    protected function getRecursiveValue($tablename, $fieldname, $value, $row)
    {
        $type = $GLOBALS['TCA'][$tablename]['columns'][$fieldname]['config']['type'];

        switch ($type) {
            case 'inline':
                $config = $GLOBALS['TCA'][$tablename]['columns'][$fieldname]['config'];
                return $this->getInlineTableData($row, $config);
                break;
            default:
                return $value;
        }
    }

    protected function getInlineTableData($parentRow, $tcaConfig)
    {
        $forignTable = $tcaConfig['foreign_table'];
        $foreignField = $tcaConfig['foreign_field'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($forignTable);
        $rows = $queryBuilder
            ->select('*')
            ->from($forignTable)
            ->where(
                $queryBuilder->expr()->eq($foreignField, $queryBuilder->createNamedParameter($parentRow['uid'], Types::INTEGER)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Types::INTEGER)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, Types::INTEGER))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        if (!$rows) {
            return false;
        }

        $newRows = [];
        foreach ($rows as $row) {
            $newRow = [];
            foreach ($row as $key => $value) {
                if (in_array($key, $this->ignoreTtContentFields)) {
                    continue;
                }
                if ($key == $foreignField) {
                    $newRow[$key] = '###UID###';
                } else if (!in_array($key, ['uid', 'pid'])) {
                    if(!empty($GLOBALS['TCA'][$forignTable]['columns'][$key])) {
                        $newRow[$key] = $this->getRecursiveValue($forignTable, $key, $value, $row);
                    }
                }
            }
            if (!empty($newRow)){
                $newRows[] = $newRow;
            }
        }

        return $newRows;
    }


}
