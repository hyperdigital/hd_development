<?php
declare(strict_types=1);

namespace Hyperdigital\HdDevelopment\Controller\Be;

use Doctrine\DBAL\Types\Types;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class TemplatesController extends ActionController
{
    public function indexAction()
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable('tt_content');

        $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('list', Types::STRING)),
                $queryBuilder->expr()->eq('list_type', $queryBuilder->createNamedParameter('hddevelopment_contentelement', Types::STRING))
            );

        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

        $this->view->assign('rows', $rows);
        return $this->htmlResponse();
    }
}