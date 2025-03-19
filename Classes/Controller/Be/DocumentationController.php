<?php
declare(strict_types=1);

namespace Hyperdigital\HdDevelopment\Controller\Be;

use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Extensionmanager\Utility\ListUtility;

class DocumentationController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    protected $pageRepository;
    protected $moduleTemplateFactory;
    protected $moduleTemplate;
    protected $uriBuilder;

    public function __construct(
        ModuleTemplateFactory  $moduleTemplateFactory,
        UriBuilder $uriBuilder
    )
    {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->uriBuilder = $uriBuilder;
    }

    public function initializeAction()
    {
        parent::initializeAction();

        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
    }

    public function indexAction()
    {
        $documentations = $GLOBALS['TYPO3_CONF_VARS']['documentation'];
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['documentation'])) {
            $this->view->assign('documentations', $documentations);
        } else {
            die('todo: Missing paths');
        }

        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());;
    }

    public function documentationAction(string $documentation)
    {
        $documentation = $GLOBALS['TYPO3_CONF_VARS']['documentation'][$documentation];

        if ($documentation && !empty($documentation['path'])) {
            // todo correct paths
            $path = Environment::getProjectPath() . '/'. $documentation['path'];
            if (file_exists($path)) {
                $parsedown = new \Parsedown();
                $markdown = file_get_contents($path);

                $this->view->assign('content',  $parsedown->text($markdown));
            } else {
                die('todo: Missing documentation');
            }
        } else {
            die('todo: Missing documentation');
        }

        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());;
    }
}
