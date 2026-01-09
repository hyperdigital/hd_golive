<?php

declare(strict_types=1);

namespace Hyperdigital\HdGolive\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Backend\Module\ModuleData;
use Hyperdigital\HdGolive\Service\PageModuleDataProvider;

final class PageModuleGoLiveHeader
{
    public function __construct(
        private readonly PageModuleDataProvider $pageModuleDataProvider,
    ) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $request = $event->getRequest();
        $pageId = (int)($request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? 0);
        if ($pageId <= 0) {
            return;
        }

        $sessionData = $this->pageModuleDataProvider->resolveSessionForPage($pageId);
        if ($sessionData === null) {
            return;
        }

        $view = $this->buildView($request);
        $view->assignMultiple([
            'pageId' => $pageId,
            'selectedLanguage' => $this->getSelectedLanguage($request),
        ]);

        $event->addHeaderContent($view->render());
    }

    private function buildView(ServerRequestInterface $request): StandaloneView
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setFormat('html');
        $templatePath = GeneralUtility::getFileAbsFileName('EXT:hd_golive/Resources/Private/Templates/GoLive/PageModuleHeader.html');
        if ($templatePath) {
            $view->setTemplatePathAndFilename($templatePath);
        }
        $view->setRequest($request);
        return $view;
    }

    private function getSelectedLanguage(ServerRequestInterface $request): int
    {
        $moduleData = $request->getAttribute('moduleData');
        if ($moduleData instanceof ModuleData) {
            return (int)$moduleData->get('language');
        }
        return 0;
    }
}
