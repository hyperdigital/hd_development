<?php
declare(strict_types=1);

namespace Hyperdigital\HdDevelopment\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class ContentElementController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    protected $contentObj;

    public function __construct(
        protected readonly FileRepository $fileRepository,
    ) {

    }

    public function showAction()
    {
        $this->contentObj = $this->configurationManager->getContentObject();

        $template = $this->fileRepository->findByRelation('tt_content', 'settings.templateFile', $this->contentObj->data['uid'])[0] ?? false;

        if ($template) {
            $content = $template->getOriginalFile()->getStorage()->getFileContents($template);
            if ($content) {
                $view = GeneralUtility::makeInstance(StandaloneView::class);
                $view->setTemplateSource($content);
                $view->assignMultiple($this->settings['variables']);


                $this->view->assign('content', $view->render());
            }
        }

        return $this->htmlResponse();
    }
}
