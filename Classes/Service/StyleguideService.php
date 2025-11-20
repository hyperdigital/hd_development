<?php
declare(strict_types=1);

namespace Hyperdigital\HdDevelopment\Service;

use Doctrine\DBAL\Types\Types;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class StyleguideService
{
    public function syncStyleguides()
    {
        $source = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('hd_development', 'styleguideSource');
        $targets = GeneralUtility::trimExplode(',', GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('hd_development', 'styleguides'));
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
            }
        }

        if ($targets && $styleguides) {
            $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
            foreach ($targets as $target) {
                $target = (int) $target;
                $parentPage = $pageRepository->getPage($target, true);
                $i = 0;
                foreach ($styleguides as $pageKey => $pageData) {
                    $i++;
                    $page = $this->getTestinPage($target, $pageKey);
                    if (!$page) {
                        $page = $this->createTestingPage($target, $pageKey, $pageData, $parentPage, $i);
                    } else {
                        $page = $this->updateTestingPage($page['uid'], $pageKey, $pageData, $i);
                    }

                    if (!empty($pageData['elements'])) {
                        foreach ($pageData['elements'] as $elementKey => $elementData) {
                            $element = $this->getTestingElement($page['uid'], $elementKey);
                            if (!$element) {
                                $element = $this->createTestingElement($page['uid'], $elementKey, $elementData);
                            } else {
                                $element = $this->updateTestingElement($element['uid'], $elementKey, $elementData);
                            }
                        }
                    }
                }
            }
        }
    }

    protected function getTestinPage($target, $pageKey)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);

        $page = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($target, Types::INTEGER)),
                $queryBuilder->expr()->eq('hd_dev_styleguide', $queryBuilder->createNamedParameter($pageKey, Types::STRING)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Types::INTEGER)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return $page;
    }

    protected function createTestingPage($target, $pageKey, $pageData, $parentPage, $i)
    {
        $values = [
            'pid' => $target,
            'doktype' => 1,
            'hd_dev_styleguide' => $pageKey,
            'hidden' => 1,
            'slug' => $parentPage['slug'].'/'.$pageKey,
            'sorting' => $i
        ];

        foreach ($pageData as $dataKey => $dataValue) {
            if (in_array($dataKey, ['elements'])){
                continue;
            }

            $values[$dataKey] = $dataValue;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');

       $queryBuilder->insert('pages')
           ->values($values)
           ->executeStatement();
        $newUid = $queryBuilder->getConnection()->lastInsertId();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);
        $page = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int) $newUid, Types::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        return $page;
    }

    protected function updateTestingPage($target, $pageKey, $pageData, $i)
    {
        $values = ['sorting' => $i, 'doktype' => 1];

        foreach ($pageData as $dataKey => $dataValue) {
            if (in_array($dataKey, ['elements'])){
                continue;
            }

            $values[$dataKey] = $dataValue;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');

        $query = $queryBuilder->update('pages');
        foreach ($values as $key => $value) {
            $query = $query->set($key, $value);
        }
        $query->where(
            $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($target, Types::INTEGER))
        );
        $query->executeStatement();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);
        $page = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($target, Types::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();
        return $page;
    }

    protected function getTestingElement($pid, $elementKey)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);

        $element = $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Types::INTEGER)),
                $queryBuilder->expr()->eq('hd_dev_styleguide', $queryBuilder->createNamedParameter($elementKey, Types::STRING)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Types::INTEGER)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return $element;
    }

    protected function createTestingElement($target, $pageKey, $pageData)
    {
        $values = [
            'pid' => $target,
            'hd_dev_styleguide' => $pageKey,
        ];

        $images = [];
        $replacements = [];
        
        $relatedTables = [];
        $fileReferences = [];


        foreach ($pageData as $dataKey => $dataValue) {
            if ($dataKey == 'sys_file_references') {
                $images = $dataValue;
                continue;
            } else if ($dataKey == 'sys_file_typolinks') {
                $this->generateReplacementsForFileTypolinks($dataValue, $replacements);
                continue;
            } else if ($dataKey == 'hd_dev_styleguide') {
                continue;
            }

            if ($dataValue === false) {

            } else if (is_array($dataValue)) {
                if (!empty($GLOBALS['TCA']['tt_content']['columns'][$dataKey])) {
                    switch ($GLOBALS['TCA']['tt_content']['columns'][$dataKey]['config']['type']) {
                        case 'inline':
                            $forignTable = $GLOBALS['TCA']['tt_content']['columns'][$dataKey]['config']['foreign_table'];
                            $foreignField = $GLOBALS['TCA']['tt_content']['columns'][$dataKey]['config']['foreign_field'];

                            foreach ($dataValue as $inlineValues) {
                                if (empty($relatedTables[$forignTable])) {
                                    $relatedTables[$forignTable] = [
                                        'foreignField' => $foreignField,
                                        'data' => []
                                    ];
                                }

                                $relatedTables[$forignTable]['data'][] = $inlineValues;
                            }

                            $values[$dataKey] = count($dataValue);
                            break;
                        case 'file':

                            foreach ($dataValue as $inlineValues) {
                                if (empty($relatedTables['sys_file_reference'])) {
                                    $relatedTables['sys_file_reference'] = [
                                        'foreignField' => 'uid_foreign',
                                        'data' => []
                                    ];
                                }

                                /**
                                 * ###IMAGE###
                                 * ###VIDEO###
                                 */
                                $inlineValues['uid_local'] = $this->findFileByType($inlineValues['uid_local']);
                                if (!$inlineValues['uid_local'] ) {
                                    continue;
                                }
                                $relatedTables['sys_file_reference']['data'][] = $inlineValues;
                            }

                            $values[$dataKey] = count($dataValue);
                            break;
                    }
                }
            } else {
                $values[$dataKey] = $dataValue;
            }
        }


        foreach ($replacements as $column => $replacement) {
            foreach ($replacement as $search => $replace) {
                $values[$column] = str_replace($search, $replace, $values[$column]);
            }
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');

        $queryBuilder->insert('tt_content')
            ->values($values)
            ->executeStatement();
        $newUid = $queryBuilder->getConnection()->lastInsertId();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);
        $element = $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int) $newUid, Types::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!empty($images)) {
            $this->generateImages($images, $element);
        }

        if ($relatedTables) {
            $this->generateRelatedTables($relatedTables, $element);
        }

        return $element;
    }

    protected function updateTestingElement($target, $pageKey, $pageData)
    {
        $values = [];
        $images = [];
        $replacements = [];
        $relatedTables = [];

        foreach ($pageData as $dataKey => $dataValue) {
            if ($dataKey == 'sys_file_references') {
                $images = $dataValue;
                continue;
            } else if ($dataKey == 'sys_file_typolinks') {
                $this->generateReplacementsForFileTypolinks($dataValue, $replacements);
                continue;
            }

            if ($dataValue === false) {

            } else if (is_array($dataValue)) {
                if (!empty($GLOBALS['TCA']['tt_content']['columns'][$dataKey])) {
                    switch ($GLOBALS['TCA']['tt_content']['columns'][$dataKey]['config']['type']) {
                        case 'inline':
                            $forignTable = $GLOBALS['TCA']['tt_content']['columns'][$dataKey]['config']['foreign_table'];
                            $foreignField = $GLOBALS['TCA']['tt_content']['columns'][$dataKey]['config']['foreign_field'];

                            foreach ($dataValue as $inlineValues) {
                                if (empty($relatedTables[$forignTable])) {
                                    $relatedTables[$forignTable] = [
                                        'foreignField' => $foreignField,
                                        'data' => []
                                    ];
                                }

                                $relatedTables[$forignTable]['data'][] = $inlineValues;
                            }

                            $values[$dataKey] = count($dataValue);
                            break;
                        case 'file':

                            foreach ($dataValue as $inlineValues) {
                                if (empty($relatedTables['sys_file_reference'])) {
                                    $relatedTables['sys_file_reference'] = [
                                        'foreignField' => 'uid_foreign',
                                        'data' => []
                                    ];
                                }

                                /**
                                 * ###IMAGE###
                                 * ###VIDEO###
                                 */
                                $inlineValues['uid_local'] = $this->findFileByType($inlineValues['uid_local']);
                                if (!$inlineValues['uid_local'] ) {
                                    continue;
                                }
                                $relatedTables['sys_file_reference']['data'][] = $inlineValues;
                            }

                            $values[$dataKey] = count($dataValue);
                            break;
                    }
                }
            } else {
                $values[$dataKey] = $dataValue;
            }
        }


        foreach ($replacements as $column => $replacement) {
            foreach ($replacement as $search => $replace) {
                $values[$column] = str_replace($search, $replace, $values[$column]);
            }
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');

        $query = $queryBuilder->update('tt_content');
        foreach ($values as $key => $value) {
            if ($key == 'hd_dev_styleguide') {
                continue;
            }
            $query = $query->set($key, $value);
        }
        $query->where(
            $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($target, Types::INTEGER))
        );
        $query->executeStatement();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class);
        $element = $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($target, Types::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!empty($images)) {
            $this->generateImages($images, $element);
        }

        if ($relatedTables) {
            $this->generateRelatedTables($relatedTables, $element);
        }

        return $element;
    }

    protected function generateRelatedTables($relatedTables, $parentElement)
    {
        foreach ($relatedTables as $table => $rows) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable($table);

            $existingElements = $queryBuilder
                ->select('*')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->eq($rows['foreignField'], $queryBuilder->createNamedParameter((int)$parentElement['uid'], Types::INTEGER))
                )
                ->executeQuery()
                ->fetchAllAssociative();
            $updates = [];

            foreach ($rows['data'] as $row) {
                $values = [];
                foreach ($row as $key => $value) {
                    if ($key == $rows['foreignField']) {
                        $values[$key] = $parentElement['uid'];
                    } else {
                        $values[$key] = $value;
                    }
                }
                $values['pid'] = $parentElement['pid'];

                if (!$existingElements) {
                    $queryBuilder->insert($table)
                        ->values($values)
                        ->executeStatement();
                } else {
                    $updates[] = $values;
                }
            }

            if ($updates) {
                // TODO: UPdate existing stuff if needed
                
                for ($i = 0; $i < count($existingElements); $i++) {
                    if (empty($updates[$i])) {
                        $queryBuilder->update($table);
                        $query = $query->set('deleted', 1);
                        $query = $query->set('hidden', 1);
                        $query->where(
                            $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($existingElements[$i]['uid'], Types::INTEGER))
                        );
                        $query->executeStatement();
                    } else {
                        $query = $queryBuilder->update($table);
                        foreach ($updates[$i] as $key => $value) {
                            if ($key == 'hd_dev_styleguide') {
                                continue;
                            }
                            $query = $query->set($key, $value);
                        }
                        $query->where(
                            $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($existingElements[$i]['uid'], Types::INTEGER))
                        );
                        $query->executeStatement();

                        unset($updates[$i]);
                    }
                }

                if (!empty($updates)) {
                    foreach ($updates as $values) {
                        $queryBuilder->insert($table)
                            ->values($values)
                            ->executeStatement();
                    }
                }
            }
        }
    }

    /**
     * Available types
     * ###IMAGE###
     * ###VIDEO###
     */
    protected function findFileByType($type)
    {
        switch ($type) {
            case '###IMAGE###':
                $originalPath = 'EXT:hd_development/Resources/Public/Images/Desktop.png';
                break;
            case '###VIDEO###':
                $originalPath = 'EXT:hd_development/Resources/Public/Videos/dummy_video.mp4';
                break;
            default:
                $originalPath = false;
                break;
        }
        if (!$originalPath) {
            return false;
        }
        $absolutePath = GeneralUtility::getFileAbsFileName($originalPath);

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_file');

        $existingFileUid = $connection->select(
            ['uid'],
            'sys_file',
            [
                'hd_dev_styleguide' => $originalPath, // store raw EXT: path here
                'missing' => 0
            ]
        )->fetchOne();

        if ($existingFileUid) {
            $uidLocal = $existingFileUid;
        } else {
            // 2. Index the file into FAL
            $storage = GeneralUtility::makeInstance(ResourceFactory::class)->getDefaultStorage();
            if (!$storage->getRootLevelFolder()->hasFolder('testingImages')) {
                $storage->getRootLevelFolder()->createFolder('testingImages');
            }
            $fileObject = $storage->addFile($absolutePath, $storage->getRootLevelFolder()->getSubfolder('testingImages'), '', DuplicationBehavior::RENAME, false); // true = index if not indexed
            $uidLocal = $fileObject->getUid();

            // 3. Update hd_dev_styleguide field
            $connection->update(
                'sys_file',
                ['hd_dev_styleguide' => $originalPath],
                ['uid' => $uidLocal]
            );
        }

        return $uidLocal;
    }

    protected function generateReplacementsForFileTypolinks($dataValue, &$replacements = [])
    {

        foreach ($dataValue as $column => $items) {
            foreach ($items as $search => $file) {
                $originalPath = $file;
                $absolutePath = GeneralUtility::getFileAbsFileName($originalPath);

                $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getConnectionForTable('sys_file');

                $existingFileUid = $connection->select(
                    ['uid'],
                    'sys_file',
                    [
                        'hd_dev_styleguide' => $originalPath, // store raw EXT: path here
                        'missing' => 0
                    ]
                )->fetchOne();

                if ($existingFileUid) {
                    $uidLocal = $existingFileUid;
                } else {
                    // 2. Index the file into FAL
                    $storage = GeneralUtility::makeInstance(ResourceFactory::class)->getDefaultStorage();
                    if (!$storage->getRootLevelFolder()->hasFolder('testingImages')) {
                        $storage->getRootLevelFolder()->createFolder('testingImages');
                    }
                    $fileObject = $storage->addFile($absolutePath, $storage->getRootLevelFolder()->getSubfolder('testingImages'), '', DuplicationBehavior::RENAME, false); // true = index if not indexed
                    $uidLocal = $fileObject->getUid();

                    // 3. Update hd_dev_styleguide field
                    $connection->update(
                        'sys_file',
                        ['hd_dev_styleguide' => $originalPath],
                        ['uid' => $uidLocal]
                    );
                }

                if ($uidLocal) {
                    $replacements[$column][$search] = 't3://file?uid=' . $uidLocal;
                }
            }
        }
    }

    protected function generateImages($images, $element)
    {

// uid_local => find or create sys_file from path $image['path'] where the path is e.g. EXT:hd_development/Resources/Public/Images/Desktop.png
// uid_foreign => $element['uid']
// tablenames => $image['tablenames']
// fieldname => $image['fieldname']

        foreach ($images as $image) {
// Get the file object
            $originalPath = $image['path'];
            $absolutePath = GeneralUtility::getFileAbsFileName($originalPath);

// 1. Check if the file already exists in sys_file.hd_dev_styleguide
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('sys_file');

            $existingFileUid = $connection->select(
                ['uid'],
                'sys_file',
                [
                    'hd_dev_styleguide' => $originalPath, // store raw EXT: path here
                    'missing' => 0
                ]
            )->fetchOne();

            if ($existingFileUid) {
                $uidLocal = $existingFileUid;
            } else {
                // 2. Index the file into FAL
                $storage = GeneralUtility::makeInstance(ResourceFactory::class)->getDefaultStorage();
                if (!$storage->getRootLevelFolder()->hasFolder('testingImages')) {
                    $storage->getRootLevelFolder()->createFolder('testingImages');
                }
                $fileObject = $storage->addFile($absolutePath, $storage->getRootLevelFolder()->getSubfolder('testingImages'), '', DuplicationBehavior::RENAME, false); // true = index if not indexed
                $uidLocal = $fileObject->getUid();

                // 3. Update hd_dev_styleguide field
                $connection->update(
                    'sys_file',
                    ['hd_dev_styleguide' => $originalPath],
                    ['uid' => $uidLocal]
                );
            }

            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('sys_file_reference');

            $existingReference = $connection->select(
                ['uid'],
                'sys_file_reference',
                [
                    'uid_local' => $uidLocal,
                    'uid_foreign' => $element['uid'],
                    'tablenames' => $image['tablenames'],
                    'fieldname' => $image['fieldname'],
                    'deleted' => 0
                ]
            )->fetchOne();

            if (!$existingReference) {
                try {
                    $connection->insert(
                        'sys_file_reference',
                        [
                            'uid_local' => $uidLocal,
                            'uid_foreign' => $element['uid'],
                            'tablenames' => $image['tablenames'],
                            'fieldname' => $image['fieldname'],
                            'crdate' => time(),
                            'tstamp' => time(),
                            'sorting_foreign' => 0,
                        ]
                    );
                } catch (\Exception $e) {
                    var_dump($e);
                    die('xxxx');
                }
            }
        }
    }
}
