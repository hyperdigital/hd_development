<?php
declare(strict_types=1);

namespace Hyperdigital\HdDevelopment\Controller;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class ContentElementController extends ActionController
{
    protected $contentObj;

    public function __construct(
        protected readonly FileRepository $fileRepository,
        protected readonly ResourceFactory $resourceFactory,
        protected readonly ViewFactoryInterface $viewFactory,
    ) {
    }

    public function showAction()
    {
        $this->contentObj = $this->request->getAttribute('currentContentObject');

        $recordUid = (int)($this->contentObj->data['_LOCALIZED_UID'] ?? $this->contentObj->data['uid']);
        $template = $this->fileRepository->findByRelation('tt_content', 'settings.templateFile', $recordUid)[0] ?? false;

        if ($template) {
            $content = $template->getOriginalFile()->getStorage()->getFileContents($template);
            if ($content) {
                $paths = [];

                $typoscriptSettings = $this->configurationManager->getConfiguration(
                    \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
                );
                if (!empty($typoscriptSettings['lib.']['contentElement.']['partialRootPaths.'])) {
                    foreach ($typoscriptSettings['lib.']['contentElement.']['partialRootPaths.'] as $tempPath) {
                        $paths[] = $tempPath;
                    }
                }

                if (!empty($this->settings['additionalPartialPaths'])) {
                    foreach (\TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->settings['additionalPartialPaths']) as $tempPath) {
                        $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($tempPath);
                        $readablePath = $folder->getReadablePath();
                        $storage = $folder->getStorage();
                        $basePath = $storage->getConfiguration()['basePath'];
                        $paths[] = $basePath . ltrim($readablePath, '/');
                    }
                }

                $storageConfiguration = $template->getOriginalFile()->getStorage()->getStorageRecord()['configuration'];
                if (($storageConfiguration['pathType'] ?? '') === 'relative') {
                    $paths[] = Environment::getPublicPath() . '/' . $storageConfiguration['basePath'];
                } elseif (($storageConfiguration['pathType'] ?? '') === 'absolute') {
                    $paths[] = $storageConfiguration['basePath'];
                }

                $viewFactoryData = new ViewFactoryData(
                    partialRootPaths: $paths,
                    request: $this->request,
                );
                $standaloneView = $this->viewFactory->create($viewFactoryData);
                $standaloneView->getRenderingContext()->getTemplatePaths()->setTemplateSource($content);

                if (!empty($this->settings['variables'])) {
                    $standaloneView->assignMultiple($this->settings['variables']);
                }

                $this->view->assign('content', $standaloneView->render());
            }
        }

        return $this->htmlResponse();
    }
}
