<?php
declare(strict_types=1);

namespace Hyperdigital\HdDevelopment\Controller\Be;

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
        $documentationKey = $documentation;

        $uriBuilder = $this->uriBuilder->setRequest($this->request);
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);

        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $returnButton = $buttonBar->makeLinkButton()
            ->setHref($uriBuilder->reset()->uriFor('index'))
            ->setIcon($iconFactory->getIcon('actions-arrow-down-left', Icon::SIZE_SMALL))
            ->setShowLabelText(true)
            ->setTitle('Return');
        $buttonBar->addButton($returnButton, ButtonBar::BUTTON_POSITION_LEFT, 1);


        $documentation = $GLOBALS['TYPO3_CONF_VARS']['documentation'][$documentationKey];

        if ($documentation && !empty($documentation['path'])) {
            $path = $documentation['path'];
            if (substr($path, 0, 4) == 'EXT:') {
                $path = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($path);
            } else {
                $path = Environment::getProjectPath() . '/'. $path;
            }

            if (file_exists($path)) {
                $parsedown = new \Parsedown();
                $markdown = file_get_contents($path);
                $parsedTexts = $parsedown->text($markdown);
                $this->fixRelativeLinks($parsedTexts, dirname($path), $documentationKey.''); // maybe would need separated parts
                $this->view->assign('content',  $parsedTexts);
            } else {
                die('todo: Missing documentation');
            }
        } else {
            die('todo: Missing documentation');
        }

        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());;
    }

    protected function fixRelativeLinks(&$content, $basePath, $filenamePrefix)
    {

        $content = preg_replace_callback(
            '/(<img\s+[^>]*src=["\'])(?!https?:\/\/|\/\/|\/)([^"\']+)(["\'])/is',
            function ($matches) use ($basePath, $filenamePrefix) {
                $relativeUrl = trim($matches[2]); // Trim spaces if any

                // Generate processed image URL
                $processedUrl = $this->processImage($relativeUrl, $basePath .'/', $filenamePrefix.'-'.str_replace('/', '_', $relativeUrl));
                return $matches[1] . $processedUrl . $matches[3];
            },
            $content
        );
    }

    protected function processImage($relativeUrl, $basePath, $processedFileName)
    {
        try {
            // Resolve absolute file path
            $absoluteFilePath = GeneralUtility::getFileAbsFileName($basePath . $relativeUrl);

            // Ensure file exists
            if (!file_exists($absoluteFilePath)) {
                throw new \Exception('File not found: ' . $absoluteFilePath);
            }

            /** @var ResourceFactory $resourceFactory */
            $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

            /** @var StorageRepository $storageRepository */
            $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);

            // Get default storage (usually ID 1, adjust if needed)
            $storage = $storageRepository->findByUid(1);

            if (!$storage) {
                throw new \Exception('Default storage not found.');
            }

            // Get the processing folder (_processed_ inside typo3temp/assets/)
            /** @var Folder $processingFolder */
            $processingFolder = $storage->getProcessingFolder();

            // Define new file name inside the processing folder
            $targetFileName = $processedFileName ?: basename($absoluteFilePath);

            // Get the absolute path of the processing folder
            $processingFolderPath = $processingFolder->getPublicUrl();
            rtrim($processingFolderPath, '/');
            $relativePath = trim($processingFolderPath, '/') . '/' . $targetFileName;
            $targetFilePath = GeneralUtility::getFileAbsFileName($relativePath);

            // Check if file already exists in processing folder
            if (!file_exists($targetFilePath)) {
                GeneralUtility::mkdir_deep(dirname($targetFilePath));
                // Copy the file to the processing folder
                if (!copy($absoluteFilePath, $targetFilePath)) {
                    throw new \Exception('Failed to copy file to processing folder.');
                }
            } else {

            }

            return '/'.$relativePath;
        } catch (
            \Exception $e
        ) {
            return $basePath . $relativeUrl; // Fallback URL
        }
    }
}
