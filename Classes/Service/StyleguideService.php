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

class StyleguideService
{
    public function syncStyleguides()
    {
        $source = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('hd_development', 'styleguideSource');
        $targets = GeneralUtility::trimExplode(',', GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('hd_development', 'styleguides'));
        $styleguides = [];
        if (!empty($source)) {
            $path = GeneralUtility::getFileAbsFileName($source);
            if (file_exists($path)) {
                if (is_file($path)) {
                    $styleguides = include($path);
                } else {
                    foreach(scandir($path) as $filepath) {
                        if (is_file($path . DIRECTORY_SEPARATOR . $filepath)) {
                            $styleguides = array_merge_recursive($styleguides, include($path . DIRECTORY_SEPARATOR . $filepath));
                        }
                    }
                }
            }
        }

        if ($targets && !empty($styleguides)) {
            $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
            foreach ($targets as $target) {
                $target = (int) $target;
                $parentPage = $pageRepository->getPage($target, true);
                $pageSorting = 0;
                foreach ($styleguides as $pageKey => $pageData) {
                    $pageSorting += 256;
                    $page = $this->getTestingPage($target, $pageKey);
                    if (!$page) {
                        $page = $this->createTestingPage($target, $pageKey, $pageData, $parentPage, $pageSorting);
                    } else {
                        $page = $this->updateTestingPage($page['uid'], $pageKey, $pageData, $pageSorting);
                    }

                    if (!empty($pageData['elements'])) {
                        $elementSorting = 0;
                        foreach ($pageData['elements'] as $elementKey => $elementData) {
                            // Use sorting from definition if available, otherwise auto-increment
                            if (!isset($elementData['sorting'])) {
                                $elementSorting += 256;
                                $elementData['sorting'] = $elementSorting;
                            }
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

    protected function getTestingPage($target, $pageKey)
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

    protected function createTestingPage($target, $pageKey, $pageData, $parentPage, $sorting)
    {
        $values = [
            'pid' => $target,
            'doktype' => 1,
            'hd_dev_styleguide' => $pageKey,
            'hidden' => 1,
            'slug' => $parentPage['slug'].'/'.$pageKey,
            'sorting' => $sorting
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

    protected function updateTestingPage($target, $pageKey, $pageData, $sorting)
    {
        $values = ['sorting' => $sorting, 'doktype' => 1];

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
                            $foreignTable = $GLOBALS['TCA']['tt_content']['columns'][$dataKey]['config']['foreign_table'];
                            $foreignField = $GLOBALS['TCA']['tt_content']['columns'][$dataKey]['config']['foreign_field'];

                            foreach ($dataValue as $inlineValues) {
                                if (empty($relatedTables[$foreignTable])) {
                                    $relatedTables[$foreignTable] = [
                                        'foreignField' => $foreignField,
                                        'data' => []
                                    ];
                                }

                                $relatedTables[$foreignTable]['data'][] = $inlineValues;
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
                                if (!$inlineValues['uid_local']) {
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

        $insertQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');

        $insertQueryBuilder->insert('tt_content')
            ->values($values)
            ->executeStatement();
        $newUid = $insertQueryBuilder->getConnection()->lastInsertId();

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
                            $foreignTable = $GLOBALS['TCA']['tt_content']['columns'][$dataKey]['config']['foreign_table'];
                            $foreignField = $GLOBALS['TCA']['tt_content']['columns'][$dataKey]['config']['foreign_field'];

                            foreach ($dataValue as $inlineValues) {
                                if (empty($relatedTables[$foreignTable])) {
                                    $relatedTables[$foreignTable] = [
                                        'foreignField' => $foreignField,
                                        'data' => []
                                    ];
                                }

                                $relatedTables[$foreignTable]['data'][] = $inlineValues;
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
                                if (!$inlineValues['uid_local']) {
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

    protected function generateRelatedTables($relatedTables, $parentElement, $parentTable = 'tt_content')
    {
        foreach ($relatedTables as $table => $rows) {
            // Handle sys_file_reference separately - create file references for the parent element
            if ($table === 'sys_file_reference') {
                $this->createFileReferencesForParentElement($rows['data'], $parentElement, $parentTable);
                continue;
            }

            $selectQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable($table);

            $existingElements = $selectQueryBuilder
                ->select('*')
                ->from($table)
                ->where(
                    $selectQueryBuilder->expr()->eq($rows['foreignField'], $selectQueryBuilder->createNamedParameter((int)$parentElement['uid'], Types::INTEGER))
                )
                ->executeQuery()
                ->fetchAllAssociative();
            $updates = [];

            foreach ($rows['data'] as $rowIndex => $row) {
                $values = [];
                $nestedFileReferences = [];

                foreach ($row as $key => $value) {
                    if ($key == $rows['foreignField']) {
                        $values[$key] = $parentElement['uid'];
                    } else if (is_array($value)) {
                        // Check if this looks like a file reference array (has uid_local with placeholder)
                        $isFileReferenceArray = $this->isFileReferenceArray($value);

                        if ($isFileReferenceArray) {
                            // Process file references - store for later creation
                            foreach ($value as $fileData) {
                                if (!is_array($fileData) || empty($fileData['uid_local'])) {
                                    continue;
                                }
                                $fileData['uid_local'] = $this->findFileByType($fileData['uid_local']);
                                if (!$fileData['uid_local']) {
                                    continue;
                                }
                                $fileData['fieldname'] = $key;
                                $fileData['tablenames'] = $table;
                                $nestedFileReferences[] = $fileData;
                            }
                            $values[$key] = count($value);
                        } else {
                            // Check TCA for inline type
                            $fieldType = $GLOBALS['TCA'][$table]['columns'][$key]['config']['type'] ?? '';
                            if ($fieldType === 'inline' || $fieldType === 'file') {
                                // Set count for inline/file fields
                                $values[$key] = count($value);
                            }
                            // Skip other array types
                        }
                    } else {
                        $values[$key] = $value;
                    }
                }
                $values['pid'] = $parentElement['pid'];

                if (!$existingElements) {
                    $insertQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getQueryBuilderForTable($table);
                    $insertQueryBuilder->insert($table)
                        ->values($values)
                        ->executeStatement();
                    $newInlineUid = $insertQueryBuilder->getConnection()->lastInsertId();

                    // Create file references for the newly inserted inline record
                    if (!empty($nestedFileReferences)) {
                        $this->createFileReferencesForInlineRecord($nestedFileReferences, (int)$newInlineUid, $parentElement['pid'], $table);
                    }
                } else {
                    $updates[] = ['values' => $values, 'fileReferences' => $nestedFileReferences];
                }
            }

            if ($updates) {
                for ($i = 0; $i < count($existingElements); $i++) {
                    $updateQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getQueryBuilderForTable($table);

                    if (empty($updates[$i])) {
                        $query = $updateQueryBuilder->update($table);
                        $query = $query->set('deleted', 1);
                        $query = $query->set('hidden', 1);
                        $query->where(
                            $updateQueryBuilder->expr()->eq('uid', $updateQueryBuilder->createNamedParameter($existingElements[$i]['uid'], Types::INTEGER))
                        );
                        $query->executeStatement();
                    } else {
                        $updateData = $updates[$i];
                        $values = $updateData['values'] ?? $updateData;
                        $fileReferences = $updateData['fileReferences'] ?? [];

                        $query = $updateQueryBuilder->update($table);
                        foreach ($values as $key => $value) {
                            if ($key == 'hd_dev_styleguide') {
                                continue;
                            }
                            $query = $query->set($key, $value);
                        }
                        $query->where(
                            $updateQueryBuilder->expr()->eq('uid', $updateQueryBuilder->createNamedParameter($existingElements[$i]['uid'], Types::INTEGER))
                        );
                        $query->executeStatement();

                        // Update file references for existing inline record
                        if (!empty($fileReferences)) {
                            $this->createFileReferencesForInlineRecord($fileReferences, (int)$existingElements[$i]['uid'], $parentElement['pid'], $table);
                        }

                        unset($updates[$i]);
                    }
                }

                if (!empty($updates)) {
                    foreach ($updates as $updateData) {
                        $values = $updateData['values'] ?? $updateData;
                        $fileReferences = $updateData['fileReferences'] ?? [];

                        $insertQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                            ->getQueryBuilderForTable($table);
                        $insertQueryBuilder->insert($table)
                            ->values($values)
                            ->executeStatement();
                        $newInlineUid = $insertQueryBuilder->getConnection()->lastInsertId();

                        // Create file references for the newly inserted inline record
                        if (!empty($fileReferences)) {
                            $this->createFileReferencesForInlineRecord($fileReferences, (int)$newInlineUid, $parentElement['pid'], $table);
                        }
                    }
                }
            }
        }
    }

    /**
     * Create file references for an inline record
     */
    protected function createFileReferencesForInlineRecord(array $fileReferences, int $uidForeign, int $pid, string $tablenames): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_file_reference');

        foreach ($fileReferences as $fileData) {
            $fieldname = $fileData['fieldname'];
            $uidLocal = $fileData['uid_local'];

            // Check if reference already exists
            $existingReference = $connection->select(
                ['uid'],
                'sys_file_reference',
                [
                    'uid_local' => $uidLocal,
                    'uid_foreign' => $uidForeign,
                    'tablenames' => $tablenames,
                    'fieldname' => $fieldname,
                    'deleted' => 0
                ]
            )->fetchOne();

            if (!$existingReference) {
                $insertData = [
                    'pid' => $pid,
                    'uid_local' => $uidLocal,
                    'uid_foreign' => $uidForeign,
                    'tablenames' => $tablenames,
                    'fieldname' => $fieldname,
                    'crdate' => time(),
                    'tstamp' => time(),
                    'sorting_foreign' => $fileData['sorting_foreign'] ?? 0,
                ];

                // Add optional fields from the file data
                $optionalFields = [
                    'title', 'alternative', 'description', 'link', 'crop', 'autoplay',
                    'hd_loading', 'hd_background_fit', 'hd_background_repeat', 'hd_background_attachment',
                    'hd_background_loading', 'hd_background_overlay', 'hd_mask', 'hd_video_poster',
                    'hd_video_autoplay', 'hd_video_muted', 'hd_video_loop', 'hd_video_playsinline',
                    'hd_video_controls', 'hd_video_subtitle_language_label', 'hd_video_subtitle_language',
                    'hd_video_subtitles', 'hd_parallax_speed', 'hd_background_position'
                ];

                foreach ($optionalFields as $field) {
                    if (array_key_exists($field, $fileData) && $fileData[$field] !== null) {
                        $insertData[$field] = $fileData[$field];
                    }
                }

                try {
                    $connection->insert('sys_file_reference', $insertData);
                } catch (\Exception $e) {
                    error_log('StyleguideService: Failed to insert sys_file_reference for inline record: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Create file references for a parent element (like tt_content)
     * This handles TCA 'file' type fields directly on the parent table
     */
    protected function createFileReferencesForParentElement(array $fileReferences, array $parentElement, string $parentTable = 'tt_content'): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_file_reference');

        foreach ($fileReferences as $fileData) {
            $uidLocal = $fileData['uid_local'];
            $fieldname = $fileData['fieldname'] ?? '';
            $tablenames = $fileData['tablenames'] ?? $parentTable;

            if (!$uidLocal || !$fieldname) {
                continue;
            }

            // Check if reference already exists
            $existingReference = $connection->select(
                ['uid'],
                'sys_file_reference',
                [
                    'uid_local' => $uidLocal,
                    'uid_foreign' => $parentElement['uid'],
                    'tablenames' => $tablenames,
                    'fieldname' => $fieldname,
                    'deleted' => 0
                ]
            )->fetchOne();

            if (!$existingReference) {
                $insertData = [
                    'pid' => $parentElement['pid'],
                    'uid_local' => $uidLocal,
                    'uid_foreign' => $parentElement['uid'],
                    'tablenames' => $tablenames,
                    'fieldname' => $fieldname,
                    'crdate' => time(),
                    'tstamp' => time(),
                    'sorting_foreign' => $fileData['sorting_foreign'] ?? 0,
                ];

                // Add optional fields from the file data
                $optionalFields = [
                    'title', 'alternative', 'description', 'link', 'crop', 'autoplay',
                    'hd_loading', 'hd_background_fit', 'hd_background_repeat', 'hd_background_attachment',
                    'hd_background_loading', 'hd_background_overlay', 'hd_mask', 'hd_video_poster',
                    'hd_video_autoplay', 'hd_video_muted', 'hd_video_loop', 'hd_video_playsinline',
                    'hd_video_controls', 'hd_video_subtitle_language_label', 'hd_video_subtitle_language',
                    'hd_video_subtitles', 'hd_parallax_speed', 'hd_background_position',
                    'hd_parallax_zoom', 'hd_video_orientation', 'hd_video_play_on_hover', 'showinpreview'
                ];

                foreach ($optionalFields as $field) {
                    if (array_key_exists($field, $fileData) && $fileData[$field] !== null) {
                        $insertData[$field] = $fileData[$field];
                    }
                }

                try {
                    $connection->insert('sys_file_reference', $insertData);
                } catch (\Exception $e) {
                    error_log('StyleguideService: Failed to insert sys_file_reference for parent element: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Check if an array looks like a file reference array
     * (contains items with uid_local that has ###IMAGE###, ###VIDEO###, or is numeric)
     */
    protected function isFileReferenceArray(array $value): bool
    {
        if (empty($value)) {
            return false;
        }

        // Check the first item to determine if this is a file reference array
        $firstItem = reset($value);
        if (!is_array($firstItem)) {
            return false;
        }

        // Must have uid_local field
        if (!isset($firstItem['uid_local'])) {
            return false;
        }

        $uidLocal = $firstItem['uid_local'];

        // Check if uid_local is a valid file placeholder or numeric UID
        return $uidLocal === '###IMAGE###'
            || $uidLocal === '###VIDEO###'
            || is_numeric($uidLocal);
    }

    /**
     * Available types
     * ###IMAGE###
     * ###VIDEO###
     * or numeric file UID
     */
    protected function findFileByType($type)
    {
        // If it's a numeric UID, return it directly
        if (is_numeric($type)) {
            return (int)$type;
        }

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
                    // Log error to file
                    error_log('StyleguideService: Failed to insert sys_file_reference: ' . $e->getMessage());
                }
            }
        }
    }
}
