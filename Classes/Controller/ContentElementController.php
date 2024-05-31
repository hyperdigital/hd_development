<?php
declare(strict_types=1);

namespace Hyperdigital\HdDevelopment\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\ConfigurationManager;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class ContentElementController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    protected $contentObj;

    public function __construct(
        protected readonly FileRepository $fileRepository,
        protected readonly ResourceFactory $resourceFactory,
    ) {

    }

    public function showAction()
    {
        $version = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Information\Typo3Version::class);

        if ($version->getMajorVersion() >= 12) {
            $this->contentObj = $this->request->getAttribute('currentContentObject');
        } else {
            $this->contentObj = $this->configurationManager->getContentObject();
        }

        $template = $this->fileRepository->findByRelation('tt_content', 'settings.templateFile', $this->contentObj->data['uid'])[0] ?? false;

        if ($template) {
            $content = $template->getOriginalFile()->getStorage()->getFileContents($template);
            if ($content) {
                $view = GeneralUtility::makeInstance(StandaloneView::class);
                $view->setTemplateSource($content);
                if ($this->settings['variables']) {
                    $view->assignMultiple($this->settings['variables']);
                }

                $configurationManager = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Configuration\ConfigurationManager::class);
                $typoscriptSettings = $configurationManager->getConfiguration(
                    ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
                );
                if (!empty($typoscriptSettings['lib.']['contentElement.']['partialRootPaths.'])) {
                    $paths = [];
                    foreach ($typoscriptSettings['lib.']['contentElement.']['partialRootPaths.'] as $tempPath) {
                        $paths[] = $tempPath;
                    }

                    if (!empty($this->settings['additionalPartialPaths'])) {
                        foreach (GeneralUtility::trimExplode(',', $this->settings['additionalPartialPaths']) as $tempPath) {
                            $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($tempPath);
                            $readablePath = $folder->getReadablePath();
                            $storage = $folder->getStorage();
                            $basePath = $storage->getConfiguration()['basePath'];
                            $newTempPath = $basePath . ltrim( $readablePath, '/' );
                            $paths[] = $newTempPath;
                        }
                    }

                    $storageConfiguration = $template->getOriginalFile()->getStorage()->getStorageRecord()['configuration'];
                    if ($storageConfiguration['pathType'] == 'relative') {
                        $path = Environment::getPublicPath() . '/' . $storageConfiguration['basePath'];
                    } else if ($storageConfiguration['pathType'] == 'absolute') {
                        $path = $storageConfiguration['basePath'];
                    }

                    if ($path) {
                        $paths[] = $path;
                    }

                    $view->setPartialRootPaths($paths);
                }


                $this->view->assign('content', $view->render());
            }
        }

        return $this->htmlResponse();
    }
}
