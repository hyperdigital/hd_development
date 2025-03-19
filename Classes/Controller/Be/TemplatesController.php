<?php
declare(strict_types=1);

namespace Hyperdigital\HdDevelopment\Controller\Be;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TemplatesController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * @var ConnectionPool
     */
    protected $connectionPool;

    public function indexAction()
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable('tt_content');
        $whereClause = [];
        $whereClause[] = $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('list', Connection::PARAM_STR));
        $whereClause[] = $queryBuilder->expr()->eq('list_type', $queryBuilder->createNamedParameter('hddevelopment_contentelement', Connection::PARAM_STR));
        

        $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(...$whereClause);

        $result = $queryBuilder->executeQuery();

        while ($row = $result->fetchAssociative()) {
            var_dump($row);
        }
        die('hello world');
    }
}