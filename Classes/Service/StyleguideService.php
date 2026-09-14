<?php
declare(strict_types=1);

namespace Hyperdigital\HdDevelopment\Service;

use Doctrine\DBAL\Types\Types;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
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
                        // Uids of the elements created for this page so far, by their definition
                        // key, so a later element can point at an earlier one - see resolveReferences().
                        $elementUids = [];
                        foreach ($pageData['elements'] as $elementKey => $elementData) {
                            // Use sorting from definition if available, otherwise auto-increment
                            if (!isset($elementData['sorting'])) {
                                $elementSorting += 256;
                                $elementData['sorting'] = $elementSorting;
                            }
                            $elementData = $this->resolveReferences($elementData, [
                                '###PAGE###' => (int)$page['uid'],
                                '###FOLDER###' => $target,
                            ], $elementUids);
                            $element = $this->getTestingElement($page['uid'], $elementKey);
                            if (!$element) {
                                $element = $this->createTestingElement($page['uid'], $elementKey, $elementData);
                            } else {
                                $element = $this->updateTestingElement($element['uid'], $elementKey, $elementData);
                            }
                            if (!empty($element['uid'])) {
                                $elementUids[$elementKey] = (int)$element['uid'];
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


    /**
     * Resolves the references a definition cannot know the value of until the sync runs.
     *
     *   ###PAGE###            the styleguide page this element is being created on
     *   ###FOLDER###          the testing folder the pages are deployed into
     *   ###ELEMENT:<key>###   the uid of an element created earlier on the same page
     *
     * Without these a definition can only describe elements that stand alone. A container needs
     * every child to name it (tx_container_parent), and a "sitemap of selected pages" needs a page
     * uid in its "pages" field - neither is knowable when the definition is written, so both were
     * simply absent from the styleguide. An ###ELEMENT### that names a key which has not been
     * created yet is left as it is rather than silently becoming 0: the definition lists elements
     * in order, so a forward reference is a mistake worth seeing.
     *
     * @param array<string, mixed> $elementData
     * @param array<string, int> $constants
     * @param array<string, int> $elementUids uid per element key, for this page, so far
     * @return array<string, mixed>
     */
    protected function resolveReferences(array $elementData, array $constants, array $elementUids): array
    {
        foreach ($elementData as $field => $value) {
            if (is_array($value)) {
                $elementData[$field] = $this->resolveReferences($value, $constants, $elementUids);
                continue;
            }
            if (!is_string($value) || !str_contains($value, '###')) {
                continue;
            }

            foreach ($constants as $token => $uid) {
                $value = str_replace($token, (string)$uid, $value);
            }

            $value = preg_replace_callback(
                '/###ELEMENT:([A-Za-z0-9_\-]+)###/',
                static function (array $match) use ($elementUids): string {
                    return isset($elementUids[$match[1]])
                        ? (string)$elementUids[$match[1]]
                        : $match[0];
                },
                $value
            );

            $elementData[$field] = $value;
        }

        return $elementData;
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

    /**
     * Maximum nesting of collections inside collections (e.g. quiz question -> answers)
     */
    protected const MAX_COLLECTION_DEPTH = 5;

    protected function generateRelatedTables($relatedTables, $parentElement, $parentTable = 'tt_content', int $depth = 0)
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
                // Inline collections of this row (e.g. the answers of a quiz question), created once the row has a uid
                $nestedTables = [];

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
                            $fieldConfig = $GLOBALS['TCA'][$table]['columns'][$key]['config'] ?? [];
                            $fieldType = $fieldConfig['type'] ?? '';
                            if ($fieldType === 'inline' || $fieldType === 'file') {
                                // Set count for inline/file fields
                                $values[$key] = count($value);
                            }
                            if ($fieldType === 'inline' && !empty($fieldConfig['foreign_table']) && !empty($fieldConfig['foreign_field'])
                                && $depth + 1 < self::MAX_COLLECTION_DEPTH
                            ) {
                                $nestedTables[$fieldConfig['foreign_table']] = [
                                    'foreignField' => $fieldConfig['foreign_field'],
                                    'data' => array_values(array_filter($value, 'is_array')),
                                ];
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
                    $this->generateNestedTables($nestedTables, (int)$newInlineUid, $parentElement, $table, $depth);
                } else {
                    $updates[] = ['values' => $values, 'fileReferences' => $nestedFileReferences, 'nestedTables' => $nestedTables];
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
                        $this->generateNestedTables($updateData['nestedTables'] ?? [], (int)$existingElements[$i]['uid'], $parentElement, $table, $depth);

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
                        $this->generateNestedTables($updateData['nestedTables'] ?? [], (int)$newInlineUid, $parentElement, $table, $depth);
                    }
                }
            }
        }
    }

    /**
     * Creates or updates the collections of an inline record (collection inside a collection). The records get the
     * pid of the content element, like the inline record itself.
     */
    protected function generateNestedTables(array $nestedTables, int $recordUid, array $parentElement, string $table, int $depth): void
    {
        if ($nestedTables === [] || $recordUid <= 0) {
            return;
        }
        $this->generateRelatedTables($nestedTables, ['uid' => $recordUid, 'pid' => $parentElement['pid']], $table, $depth + 1);
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

            // Check if reference already exists (include sorting_foreign to allow multiple references to same file)
            $existingReference = $connection->select(
                ['uid'],
                'sys_file_reference',
                [
                    'uid_local' => $uidLocal,
                    'uid_foreign' => $uidForeign,
                    'tablenames' => $tablenames,
                    'fieldname' => $fieldname,
                    'sorting_foreign' => $fileData['sorting_foreign'] ?? 0,
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

                // Everything else the definition carries, as long as the column is really there.
                $insertData += $this->pickExistingColumns('sys_file_reference', $fileData, $insertData);

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

            // Check if reference already exists (include sorting_foreign to allow multiple references to same file)
            $existingReference = $connection->select(
                ['uid'],
                'sys_file_reference',
                [
                    'uid_local' => $uidLocal,
                    'uid_foreign' => $parentElement['uid'],
                    'tablenames' => $tablenames,
                    'fieldname' => $fieldname,
                    'sorting_foreign' => $fileData['sorting_foreign'] ?? 0,
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

                // Everything else the definition carries, as long as the column is really there.
                $insertData += $this->pickExistingColumns('sys_file_reference', $fileData, $insertData);

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
            $storage = GeneralUtility::makeInstance(StorageRepository::class)->getDefaultStorage();
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
                    $storage = GeneralUtility::makeInstance(StorageRepository::class)->getDefaultStorage();
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

        $sortingCounter = 0;
        foreach ($images as $image) {
            $sortingCounter++;
            $sortingForeign = $image['sorting_foreign'] ?? $sortingCounter;

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
                $storage = GeneralUtility::makeInstance(StorageRepository::class)->getDefaultStorage();
                if (!$storage->getRootLevelFolder()->hasFolder('testingImages')) {
                    $storage->getRootLevelFolder()->createFolder('testingImages');
                }
                $fileObject = $storage->addFile($absolutePath, $storage->getRootLevelFolder()->getSubfolder('testingImages'), '', DuplicationBehavior::RENAME, false);
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

            // Check if reference already exists (include sorting_foreign to allow multiple references to same file)
            $existingReference = $connection->select(
                ['uid'],
                'sys_file_reference',
                [
                    'uid_local' => $uidLocal,
                    'uid_foreign' => $element['uid'],
                    'tablenames' => $image['tablenames'],
                    'fieldname' => $image['fieldname'],
                    'sorting_foreign' => $sortingForeign,
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
                            'sorting_foreign' => $sortingForeign,
                        ]
                    );
                } catch (\Exception $e) {
                    // Log error to file
                    error_log('StyleguideService: Failed to insert sys_file_reference: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Keeps only the values whose column actually exists in $table.
     *
     * The lists this replaced were hardcoded and had drifted apart, and they named fields that
     * other extensions add - "showinpreview" comes with EXT:news. On an instance without that
     * extension the INSERT failed on an unknown column and the whole file reference was dropped
     * (the exception is caught and only reaches error_log), so one stray field cost a demo its
     * image. Asking the schema means a definition can carry any field the instance really has.
     *
     * @param array<string, mixed> $data    values from the styleguide definition
     * @param array<string, mixed> $already keys that are set explicitly and must not be overwritten
     * @return array<string, mixed>
     */
    protected function pickExistingColumns(string $table, array $data, array $already = []): array
    {
        static $columns = [];

        if (!isset($columns[$table])) {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
            $columns[$table] = array_change_key_case(
                $connection->createSchemaManager()->listTableColumns($table)
            );
        }

        $values = [];
        foreach ($data as $field => $value) {
            if ($value === null || is_array($value) || isset($already[$field])) {
                continue;
            }
            if (isset($columns[$table][strtolower($field)])) {
                $values[$field] = $value;
            }
        }

        return $values;
    }
}
