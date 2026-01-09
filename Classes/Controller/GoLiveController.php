<?php

declare(strict_types=1);

namespace Hyperdigital\HdGolive\Controller;

use Hyperdigital\HdGolive\Service\PageTreeService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use Hyperdigital\HdGolive\Domain\GoLiveStatus;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;

class GoLiveController extends ActionController
{
    use AllowedMethodsTrait;

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
        private readonly PageTreeService $pageTreeService,
        private readonly IconFactory $iconFactory,
    ) {}

    public function listAction(?string $site = null, int $session = 0): ResponseInterface
    {
        $queryParams = $this->request->getQueryParams();
        $pageId = (int)($queryParams['id'] ?? 0);
        $canViewChecklist = $this->canViewChecklist();

        $sites = $this->siteFinder->getAllSites();
        $siteOptions = [];
        foreach ($sites as $siteObject) {
            $siteOptions[$siteObject->getIdentifier()] = sprintf(
                '%s (ID %d)',
                $siteObject->getIdentifier(),
                $siteObject->getRootPageId()
            );
        }

        $selectedSite = $site;
        if ($selectedSite === null || $selectedSite === '') {
            if ($pageId > 0) {
                try {
                    $selectedSite = $this->siteFinder->getSiteByPageId($pageId)->getIdentifier();
                } catch (\Throwable) {
                    $selectedSite = null;
                }
            }
            if (($selectedSite === null || $selectedSite === '') && $siteOptions !== [] && $pageId > 0) {
                $selectedSite = array_key_first($siteOptions);
            }
        }

        $sessions = [];
        $sessionOptions = [];
        $sharedSessionId = 0;
        $backendUserId = (int)($this->getBackendUser()->user['uid'] ?? 0);
        $sharedLabel = LocalizationUtility::translate(
            'LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:session.sharedLabel',
            'hd_golive'
        ) ?? 'shared';
        $siteSessions = [];
        $showOverview = $pageId === 0 && ($selectedSite === null || $selectedSite === '');
        if ($showOverview) {
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            foreach ($sites as $siteObject) {
                $siteIdentifier = $siteObject->getIdentifier();
                $sessionsForSite = $this->fetchSessionsForSite($siteIdentifier, $backendUserId);
                $sessionLinks = [];
                foreach ($sessionsForSite as $sessionRow) {
                    $sessionLinks[] = [
                        'uid' => (int)$sessionRow['uid'],
                        'label' => $this->formatSessionLabel($sessionRow, $sharedLabel),
                        'href' => (string)$uriBuilder->buildUriFromRoute('web_hdgolive', [
                            'site' => $siteIdentifier,
                            'session' => (int)$sessionRow['uid'],
                        ]),
                    ];
                }
                $siteSessions[] = [
                    'identifier' => $siteIdentifier,
                    'label' => $siteOptions[$siteIdentifier] ?? $siteIdentifier,
                    'sessions' => $sessionLinks,
                ];
            }
        }
        if ($selectedSite !== null && $selectedSite !== '') {
            $sessions = $this->fetchSessionsForSite($selectedSite, $backendUserId);
            foreach ($sessions as $sessionRow) {
                $isClosed = (int)$sessionRow['closed'] === 1;
                $suffixes = [];
                if ($isClosed) {
                    $suffixes[] = 'closed';
                }
                if ((int)($sessionRow['shared'] ?? 0) === 1) {
                    $suffixes[] = $sharedLabel;
                    if (!$isClosed && $sharedSessionId === 0) {
                        $sharedSessionId = (int)$sessionRow['uid'];
                    }
                }
                $labelSuffix = $suffixes !== [] ? ' (' . implode(', ', $suffixes) . ')' : '';
                $sessionOptions[(int)$sessionRow['uid']] = sprintf(
                    '%s%s (%s)',
                    $sessionRow['title'],
                    $labelSuffix,
                    date('Y-m-d H:i', (int)$sessionRow['crdate'])
                );
            }
        }

        $activeSessionId = $session;
        if ($activeSessionId === 0 && $sessions !== []) {
            $activeSessionId = (int)$sessions[0]['uid'];
        }

        $activeSession = null;
        foreach ($sessions as $sessionRow) {
            if ((int)$sessionRow['uid'] === $activeSessionId) {
                $activeSession = $sessionRow;
                break;
            }
        }
        $activeSessionOwnerId = $activeSession !== null ? (int)($activeSession['cruser_id'] ?? 0) : 0;

        $pages = [];
        $statusByPage = [];
        $rootPageId = null;
        $siteTitle = null;
        $languageOptions = [];
        $languageFlags = [];
        $languageLabels = [];
        $languageCodes = [];
        $siteObject = null;
        if ($selectedSite !== null && $selectedSite !== '') {
            try {
                $siteObject = $this->siteFinder->getSiteByIdentifier($selectedSite);
                $rootPageId = $siteObject->getRootPageId();
                try {
                    $siteTitle = (string)$siteObject->getAttribute('websiteTitle');
                } catch (\Throwable) {
                    $siteTitle = '';
                }
                if (trim($siteTitle) === '') {
                    $rootRecord = BackendUtility::getRecord('pages', $rootPageId, 'title');
                    $siteTitle = (string)($rootRecord['title'] ?? $siteObject->getIdentifier());
                }
                foreach ($siteObject->getLanguages() as $language) {
                    $languageOptions[(string)$language->getLanguageId()] = $language->getTitle();
                }
                $pages = $this->pageTreeService->fetchTree($siteObject->getRootPageId());
                $pages = $this->sortPagesWithTranslations($pages);
            } catch (\Throwable) {
                $pages = [];
            }
        }

        if ($siteObject !== null) {
            foreach ($siteObject->getAllLanguages() as $languageId => $language) {
                $flagIdentifier = $language->getFlagIdentifier();
                if ($flagIdentifier !== '') {
                    $languageFlags[(string)$languageId] = $this->iconFactory
                        ->getIcon($flagIdentifier, \TYPO3\CMS\Core\Imaging\IconSize::SMALL)
                        ->render();
                }
                $languageLabels[(string)$languageId] = $this->getLanguageLabel($language);
                $languageCodes[(string)$languageId] = $this->getLanguageCode($language);
            }
        }

        $pageIds = array_map(static fn(array $page): int => (int)$page['uid'], $pages);
        if ($activeSessionId > 0 && $pageIds !== []) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_pagecheck');
            $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $rows = $queryBuilder
                ->select('uid', 'page', 'status', 'checked', 'checked_by', 'checked_time')
                ->from('tx_hdgolive_pagecheck')
                ->where(
                    $queryBuilder->expr()->eq('session', $queryBuilder->createNamedParameter($activeSessionId, ParameterType::INTEGER)),
                    $queryBuilder->expr()->in(
                        'page',
                        $queryBuilder->createNamedParameter($pageIds, ArrayParameterType::INTEGER)
                    )
                )
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($rows as $row) {
                $status = GoLiveStatus::normalize((int)($row['status'] ?? 0));
                if ($status === GoLiveStatus::PENDING && (int)($row['checked'] ?? 0) === 1) {
                    $status = GoLiveStatus::PASS;
                }
                $statusByPage[(int)$row['page']] = [
                    'status' => $status,
                    'checkedById' => (int)$row['checked_by'],
                    'checkedTime' => (int)$row['checked_time'],
                    'pagecheckUid' => (int)$row['uid'],
                ];
            }
        }

        $passCount = 0;
        $failCount = 0;
        $userIds = [];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        foreach ($pages as &$page) {
            $pageCheck = $statusByPage[(int)$page['uid']] ?? [
                'status' => GoLiveStatus::PENDING,
                'checkedById' => 0,
                'checkedTime' => 0,
                'pagecheckUid' => 0,
            ];
            $page['status'] = $pageCheck['status'];
            $page['checkedById'] = $pageCheck['checkedById'];
            $page['checkedTime'] = $pageCheck['checkedTime'];
            $page['pagecheckUid'] = $pageCheck['pagecheckUid'];
            $page['statusClass'] = match ($page['status']) {
                GoLiveStatus::PASS => 'table-success',
                GoLiveStatus::FAILED => 'table-danger',
                default => '',
            };
            $languageKey = (string)($page['sys_language_uid'] ?? 0);
            $page['languageFlag'] = $languageFlags[$languageKey] ?? '';
            $page['layoutUrl'] = (string)$uriBuilder->buildUriFromRoute('web_layout', [
                'id' => (int)$page['uid'],
            ]);
            if ($page['status'] === GoLiveStatus::PASS) {
                $passCount++;
            } elseif ($page['status'] === GoLiveStatus::FAILED) {
                $failCount++;
            }
            if ($page['checkedById'] > 0) {
                $userIds[] = $page['checkedById'];
            }
        }
        unset($page);

        $totalCount = count($pages);
        $pendingCount = max(0, $totalCount - $passCount - $failCount);
        $passPercent = 0;
        $failPercent = 0;
        $pendingPercent = 0;
        if ($totalCount > 0) {
            $passPercent = (int)round(($passCount / $totalCount) * 100);
            $failPercent = (int)round(($failCount / $totalCount) * 100);
            $pendingPercent = max(0, 100 - $passPercent - $failPercent);
        }
        $checkItems = [];
        if ($selectedSite !== null && $selectedSite !== '' && $activeSessionId > 0 && $canViewChecklist) {
            $checkItems = $this->getCheckItems($selectedSite, $activeSessionId);
        }

        $notesByItemcheck = [];
        $notesByPagecheck = [];
        if ($checkItems !== []) {
            $itemcheckUids = array_values(array_filter(array_map(
                static fn(array $item): int => (int)($item['itemcheckUid'] ?? 0),
                $checkItems
            )));
            if ($itemcheckUids !== []) {
                $notesByItemcheck = $this->getNotesPreview('tx_hdgolive_note', 'itemcheck', $itemcheckUids);
            }
        }
        if ($pages !== []) {
            $pagecheckUids = array_values(array_filter(array_map(
                static fn(array $page): int => (int)($page['pagecheckUid'] ?? 0),
                $pages
            )));
            if ($pagecheckUids !== []) {
                $notesByPagecheck = $this->getNotesPreview('tx_hdgolive_pagenote', 'pagecheck', $pagecheckUids);
            }
        }

        foreach ($checkItems as $item) {
            if (!empty($item['checkedById'])) {
                $userIds[] = (int)$item['checkedById'];
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds)));
        if ($activeSessionOwnerId > 0) {
            $userIds[] = $activeSessionOwnerId;
        }
        $userIds = array_values(array_unique($userIds));
        $userNames = $this->getBackendUserNames($userIds);
        if ($activeSessionOwnerId > 0) {
            $activeSessionOwnerName = $userNames[$activeSessionOwnerId] ?? '';
        }

        foreach ($pages as &$page) {
            if ($page['status'] === GoLiveStatus::PENDING) {
                $page['checkedBy'] = '';
                $page['checkedTime'] = 0;
            } else {
                $page['checkedBy'] = $userNames[$page['checkedById']] ?? '';
            }
        }
        unset($page);

        foreach ($checkItems as &$item) {
            if ($item['status'] === GoLiveStatus::PENDING) {
                $item['checkedBy'] = '';
                $item['checkedTime'] = 0;
            } else {
                $item['checkedBy'] = $userNames[$item['checkedById']] ?? '';
            }
            $item['statusClass'] = match ($item['status']) {
                GoLiveStatus::PASS => 'table-success',
                GoLiveStatus::FAILED => 'table-danger',
                default => '',
            };
        }
        unset($item);

        if ($activeSessionId > 0 && $selectedSite !== null && $selectedSite !== '' && $canViewChecklist) {
            $returnParams = [
                'site' => $selectedSite,
                'session' => $activeSessionId,
            ];
            if ($pageId > 0) {
                $returnParams['id'] = $pageId;
            }
            $returnUrl = (string)$uriBuilder->buildUriFromRoute('web_hdgolive', $returnParams);
            foreach ($checkItems as &$item) {
                $itemcheckUid = (int)($item['itemcheckUid'] ?? 0);
                $detailParams = [
                    'site' => $selectedSite,
                    'session' => $activeSessionId,
                    'action' => 'itemDetail',
                    'itemKey' => $item['key'] ?? '',
                ];
                if ($pageId > 0) {
                    $detailParams['id'] = $pageId;
                }
                $item['detailUrl'] = (string)$uriBuilder->buildUriFromRoute('web_hdgolive', $detailParams);
                if ($itemcheckUid > 0) {
                    $editParams = [
                        'edit' => [
                            'tx_hdgolive_itemcheck' => [
                                $itemcheckUid => 'edit',
                            ],
                        ],
                        'overrideVals' => [
                            'tx_hdgolive_itemcheck' => [
                                'session' => $activeSessionId,
                            ],
                        ],
                        'returnUrl' => $returnUrl,
                    ];
                } else {
                    $editParams = [
                        'edit' => [
                            'tx_hdgolive_itemcheck' => [
                                0 => 'new',
                            ],
                        ],
                        'defVals' => [
                            'tx_hdgolive_itemcheck' => [
                                'session' => $activeSessionId,
                                'item_key' => $item['key'] ?? '',
                                'pid' => 0,
                                'checked_by' => (int)($GLOBALS['BE_USER']->user['uid'] ?? 0),
                            ],
                        ],
                        'returnUrl' => $returnUrl,
                    ];
                }
                $item['editUrl'] = (string)$uriBuilder->buildUriFromRoute('record_edit', $editParams);
                $item['notesPreview'] = $notesByItemcheck[$itemcheckUid] ?? [];
            }
            unset($item);

            foreach ($pages as &$page) {
                $pagecheckUid = (int)($page['pagecheckUid'] ?? 0);
                if ($pagecheckUid > 0) {
                    $editParams = [
                        'edit' => [
                            'tx_hdgolive_pagecheck' => [
                                $pagecheckUid => 'edit',
                            ],
                        ],
                        'overrideVals' => [
                            'tx_hdgolive_pagecheck' => [
                                'session' => $activeSessionId,
                            ],
                        ],
                        'returnUrl' => $returnUrl,
                    ];
                } else {
                    $editParams = [
                        'edit' => [
                            'tx_hdgolive_pagecheck' => [
                                0 => 'new',
                            ],
                        ],
                        'defVals' => [
                            'tx_hdgolive_pagecheck' => [
                                'session' => $activeSessionId,
                                'page' => (int)($page['uid'] ?? 0),
                                'pid' => 0,
                                'checked_by' => (int)($GLOBALS['BE_USER']->user['uid'] ?? 0),
                            ],
                        ],
                        'returnUrl' => $returnUrl,
                    ];
                }
                $page['editUrl'] = (string)$uriBuilder->buildUriFromRoute('record_edit', $editParams);
                $page['notesPreview'] = $notesByPagecheck[$pagecheckUid] ?? [];
            }
            unset($page);
        }

        $activeSessionClosed = $activeSession !== null && (int)($activeSession['closed'] ?? 0) === 1;
        $currentUserId = (int)($this->getBackendUser()->user['uid'] ?? 0);
        $activeSessionOwnerName = '';
        if ($canViewChecklist && $selectedSite !== null && $selectedSite !== '' && $activeSessionId > 0 && $rootPageId !== null && !$activeSessionClosed) {
            $this->setActiveSessionState($selectedSite, $activeSessionId, $rootPageId);
        } else {
            $this->clearActiveSessionState();
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        if ($selectedSite !== null && $selectedSite !== '' && $sessionOptions !== []) {
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $menu = $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
            $menu->setIdentifier('hdgolive-session');
            $menu->setLabel('LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:session.select');
            foreach ($sessionOptions as $sessionId => $label) {
                $parameters = [
                    'site' => $selectedSite,
                    'session' => $sessionId,
                ];
                if ($pageId > 0) {
                    $parameters['id'] = $pageId;
                }
                $menuItem = $menu->makeMenuItem()
                    ->setTitle($label)
                    ->setHref((string)$uriBuilder->buildUriFromRoute('web_hdgolive', $parameters))
                    ->setActive((int)$sessionId === $activeSessionId);
                $menu->addMenuItem($menuItem);
            }
            $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
        }

        if ($selectedSite !== null && $selectedSite !== '' && $activeSessionId > 0) {
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $parameters = [
                'site' => $selectedSite,
                'session' => $activeSessionId,
                'action' => 'exportWizard',
            ];
            if ($pageId > 0) {
                $parameters['id'] = $pageId;
            }
            $exportUrl = (string)$uriBuilder->buildUriFromRoute('web_hdgolive', $parameters);
            $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
            $exportButton = $buttonBar->makeLinkButton()
                ->setHref($exportUrl)
                ->setTitle(LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:export.button', 'hd_golive') ?? 'Export PDF')
                ->setIcon($this->iconFactory->getIcon('actions-download', \TYPO3\CMS\Core\Imaging\IconSize::SMALL))
                ->setShowLabelText(true);
            $buttonBar->addButton($exportButton, ButtonBar::BUTTON_POSITION_LEFT, 20);
        }

        if ($selectedSite !== null && $selectedSite !== '') {
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
            $formProtection = GeneralUtility::makeInstance(FormProtectionFactory::class)->createFromRequest($this->request);
            $routeToken = $formProtection->generateToken('route', 'web_hdgolive');

            $startParams = [
                'site' => $selectedSite,
                'action' => 'startSessionButton',
                'token' => $routeToken,
            ];
            if ($pageId > 0) {
                $startParams['id'] = $pageId;
            }
            $startUrl = (string)$uriBuilder->buildUriFromRoute('web_hdgolive', $startParams);
            $startButton = $buttonBar->makeLinkButton()
                ->setHref($startUrl)
                ->setTitle(LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:session.new', 'hd_golive') ?? 'Start new checklist')
                ->setIcon($this->iconFactory->getIcon('actions-add', \TYPO3\CMS\Core\Imaging\IconSize::SMALL))
                ->setShowLabelText(true);
            $buttonBar->addButton($startButton, ButtonBar::BUTTON_POSITION_LEFT, 30);

            if ($activeSessionId > 0 && $activeSession !== null) {
                if (!$activeSessionClosed) {
                    $sharedActive = (int)($activeSession['shared'] ?? 0) === 1;
                    $shareParams = [
                        'site' => $selectedSite,
                        'session' => $activeSessionId,
                        'action' => $sharedActive ? 'unshareSessionButton' : 'shareSessionButton',
                        'token' => $routeToken,
                    ];
                    if ($pageId > 0) {
                        $shareParams['id'] = $pageId;
                    }
                    $shareUrl = (string)$uriBuilder->buildUriFromRoute('web_hdgolive', $shareParams);
                    $shareButton = $buttonBar->makeLinkButton()
                        ->setHref($shareUrl)
                        ->setTitle(LocalizationUtility::translate(
                            $sharedActive
                                ? 'LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:session.unshare'
                                : 'LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:session.share',
                            'hd_golive'
                        ) ?? ($sharedActive ? 'Unshare session' : 'Share session'))
                        ->setIcon($this->iconFactory->getIcon(
                            $sharedActive ? 'actions-unlink' : 'actions-thumbtack',
                            \TYPO3\CMS\Core\Imaging\IconSize::SMALL
                        ))
                        ->setShowLabelText(true);
                    $buttonBar->addButton($shareButton, ButtonBar::BUTTON_POSITION_LEFT, 36);
                }
                if ($activeSessionClosed) {
                    $reopenParams = [
                        'site' => $selectedSite,
                        'session' => $activeSessionId,
                        'action' => 'reopenSessionButton',
                        'token' => $routeToken,
                    ];
                    if ($pageId > 0) {
                        $reopenParams['id'] = $pageId;
                    }
                    $reopenUrl = (string)$uriBuilder->buildUriFromRoute('web_hdgolive', $reopenParams);
                    $reopenButton = $buttonBar->makeLinkButton()
                        ->setHref($reopenUrl)
                        ->setTitle(LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:session.reopen', 'hd_golive') ?? 'Reopen checklist session')
                        ->setIcon($this->iconFactory->getIcon('actions-refresh', \TYPO3\CMS\Core\Imaging\IconSize::SMALL))
                        ->setShowLabelText(true);
                    $buttonBar->addButton($reopenButton, ButtonBar::BUTTON_POSITION_LEFT, 40);
                } else {
                    $closeParams = [
                        'site' => $selectedSite,
                        'session' => $activeSessionId,
                        'action' => 'closeSessionButton',
                        'token' => $routeToken,
                    ];
                    if ($pageId > 0) {
                        $closeParams['id'] = $pageId;
                    }
                    $closeUrl = (string)$uriBuilder->buildUriFromRoute('web_hdgolive', $closeParams);
                    $closeButton = $buttonBar->makeLinkButton()
                        ->setHref($closeUrl)
                        ->setTitle(LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:session.close', 'hd_golive') ?? 'Close checklist session')
                        ->setIcon($this->iconFactory->getIcon('actions-close', \TYPO3\CMS\Core\Imaging\IconSize::SMALL))
                        ->setShowLabelText(true);
                    $buttonBar->addButton($closeButton, ButtonBar::BUTTON_POSITION_LEFT, 40);

                    $removeParams = [
                        'site' => $selectedSite,
                        'session' => $activeSessionId,
                        'action' => 'removeSessionButton',
                        'token' => $routeToken,
                    ];
                    if ($pageId > 0) {
                        $removeParams['id'] = $pageId;
                    }
                    $removeUrl = (string)$uriBuilder->buildUriFromRoute('web_hdgolive', $removeParams);
                    $removeButton = $buttonBar->makeLinkButton()
                        ->setHref($removeUrl)
                        ->setTitle(LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:session.remove', 'hd_golive') ?? 'Remove session')
                        ->setIcon($this->iconFactory->getIcon('actions-delete', \TYPO3\CMS\Core\Imaging\IconSize::SMALL))
                        ->setShowLabelText(true);
                    $buttonBar->addButton($removeButton, ButtonBar::BUTTON_POSITION_LEFT, 41);
                }
            }
        }

        return $moduleTemplate
            ->setFlashMessageQueue($this->getFlashMessageQueue())
            ->setTitle(LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang_module.xlf:mlang_tabs_tab') ?? 'GO Live')
            ->assignMultiple([
                'siteSessions' => $siteSessions,
                'showOverview' => $showOverview,
                'canViewChecklist' => $canViewChecklist,
                'siteOptions' => $siteOptions,
                'selectedSite' => $selectedSite,
                'siteTitle' => $siteTitle,
                'languageOptions' => $languageOptions,
                'sessions' => $sessions,
                'sessionOptions' => $sessionOptions,
                'activeSession' => $activeSession,
                'activeSessionClosed' => $activeSessionClosed,
                'activeSessionOwnerId' => $activeSessionOwnerId,
                'activeSessionOwnerName' => $activeSessionOwnerName,
                'isSessionOwner' => $activeSessionOwnerId > 0 && $activeSessionOwnerId === $currentUserId,
                'checkItems' => $checkItems,
                'pages' => $pages,
                'passCount' => $passCount,
                'failCount' => $failCount,
                'pendingCount' => $pendingCount,
                'totalCount' => $totalCount,
                'passPercent' => $passPercent,
                'failPercent' => $failPercent,
                'pendingPercent' => $pendingPercent,
            ])
            ->renderResponse('GoLive/List');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSessionsForSite(string $siteIdentifier, int $backendUserId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_session');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('uid', 'title', 'crdate', 'closed', 'closed_time', 'closed_by', 'shared', 'cruser_id')
            ->from('tx_hdgolive_session')
            ->where(
                $queryBuilder->expr()->eq(
                    'site_identifier',
                    $queryBuilder->createNamedParameter($siteIdentifier)
                ),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('shared', $queryBuilder->createNamedParameter(1, ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('cruser_id', $queryBuilder->createNamedParameter($backendUserId, ParameterType::INTEGER))
                )
            )
            ->orderBy('crdate', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function formatSessionLabel(array $sessionRow, string $sharedLabel): string
    {
        $isClosed = (int)($sessionRow['closed'] ?? 0) === 1;
        $suffixes = [];
        if ($isClosed) {
            $suffixes[] = 'closed';
        }
        if ((int)($sessionRow['shared'] ?? 0) === 1) {
            $suffixes[] = $sharedLabel;
        }
        $labelSuffix = $suffixes !== [] ? ' (' . implode(', ', $suffixes) . ')' : '';

        return sprintf(
            '%s%s (%s)',
            $sessionRow['title'],
            $labelSuffix,
            date('Y-m-d H:i', (int)$sessionRow['crdate'])
        );
    }

    public function exportWizardAction(string $site, int $session): ResponseInterface
    {
        $site = trim($site);
        if ($site === '' || $session <= 0) {
            $this->addFlashMessage('Missing site or session.');
            return $this->redirect('list');
        }

        $sessionRow = $this->getSessionRow($session);
        if ($sessionRow === null || (string)$sessionRow['site_identifier'] !== $site) {
            $this->addFlashMessage('Invalid checklist session.');
            return $this->redirect('list', null, null, [
                'site' => $site,
                'session' => $session,
            ]);
        }

        $siteTitle = $site;
        $languageOptions = [];
        try {
            $siteObject = $this->siteFinder->getSiteByIdentifier($site);
            $rootPageId = $siteObject->getRootPageId();
            try {
                $siteTitle = (string)$siteObject->getAttribute('websiteTitle');
            } catch (\Throwable) {
                $siteTitle = '';
            }
            if (trim($siteTitle) === '') {
                $rootRecord = BackendUtility::getRecord('pages', $rootPageId, 'title');
                $siteTitle = (string)($rootRecord['title'] ?? $siteObject->getIdentifier());
            }
            foreach ($siteObject->getLanguages() as $language) {
                $languageOptions[(string)$language->getLanguageId()] = $language->getTitle();
            }
        } catch (\Throwable) {
            $siteTitle = $site;
        }

        return $this->moduleTemplateFactory->create($this->request)
            ->setFlashMessageQueue($this->getFlashMessageQueue())
            ->setTitle(LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:export.title', 'hd_golive') ?? 'GO Live export')
            ->assignMultiple([
                'selectedSite' => $site,
                'siteTitle' => $siteTitle,
                'activeSession' => $sessionRow,
                'statusFilter' => 'all',
                'languageOptions' => $languageOptions,
                'languageFilter' => 'all',
            ])
            ->renderResponse('GoLive/ExportWizard');
    }

    public function itemDetailAction(string $site, int $session, string $itemKey): ResponseInterface
    {
        if (!$this->canViewChecklist()) {
            $this->addFlashMessage('Checklist view not enabled for your user group.');
            return $this->redirect('list');
        }
        $site = trim($site);
        $itemKey = trim($itemKey);
        if ($site === '' || $session <= 0 || $itemKey === '') {
            $this->addFlashMessage('Missing site, session, or item key.');
            return $this->redirect('list');
        }

        $sessionRow = $this->getSessionRow($session);
        if ($sessionRow === null || (string)$sessionRow['site_identifier'] !== $site) {
            $this->addFlashMessage('Invalid checklist session.');
            return $this->redirect('list');
        }

        $item = $this->getCheckItemForSession($site, $session, $itemKey);
        if ($item === null) {
            $this->addFlashMessage('Checklist item not found.');
            return $this->redirect('list', null, null, [
                'site' => $site,
                'session' => $session,
            ]);
        }

        $checkedByName = '';
        if ((int)($item['checkedById'] ?? 0) > 0 && (int)($item['status'] ?? GoLiveStatus::PENDING) !== GoLiveStatus::PENDING) {
            $userNames = $this->getBackendUserNames([(int)$item['checkedById']]);
            $checkedByName = $userNames[(int)$item['checkedById']] ?? '';
        }

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $returnUrl = (string)$uriBuilder->buildUriFromRoute('web_hdgolive', [
            'site' => $site,
            'session' => $session,
            'action' => 'itemDetail',
            'itemKey' => $itemKey,
        ]);
        $editUrl = '';
        if (!empty($item['definitionUid'])) {
            $editUrl = (string)$uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [
                    'tx_hdgolive_checkitem' => [
                        (int)$item['definitionUid'] => 'edit',
                    ],
                ],
                'returnUrl' => $returnUrl,
            ]);
        }
        $listUrl = (string)$uriBuilder->buildUriFromRoute('web_hdgolive', [
            'site' => $site,
            'session' => $session,
        ]);

        return $this->moduleTemplateFactory->create($this->request)
            ->setFlashMessageQueue($this->getFlashMessageQueue())
            ->setTitle(LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:items.detail.title', 'hd_golive') ?? 'Checklist item')
            ->assignMultiple([
                'selectedSite' => $site,
                'activeSession' => $sessionRow,
                'item' => $item,
                'checkedByName' => $checkedByName,
                'statusLabel' => $this->getStatusLabel((int)($item['status'] ?? GoLiveStatus::PENDING)),
                'editUrl' => $editUrl,
                'listUrl' => $listUrl,
            ])
            ->renderResponse('GoLive/Item');
    }

    public function initializeExportPdfAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    public function exportPdfAction(string $site, int $session, bool $includePages = true, bool $includeItems = true, bool $includeNotes = true, string $statusFilter = 'all', string $languageFilter = 'all'): ResponseInterface
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->addFlashMessage('PDF export requires dompdf/dompdf to be installed.');
            return $this->redirect('list', null, null, [
                'site' => $site,
                'session' => $session,
            ]);
        }

        $sessionRow = $this->getSessionRow($session);
        if ($sessionRow === null || (string)$sessionRow['site_identifier'] !== $site) {
            $this->addFlashMessage('Invalid checklist session.');
            return $this->redirect('list', null, null, [
                'site' => $site,
                'session' => $session,
            ]);
        }

        $statusFilterValue = $this->normalizeStatusFilter($statusFilter);
        $languageFilterValue = $this->normalizeLanguageFilter($languageFilter);
        $exportData = $this->buildExportData($site, $session, $includeNotes, $statusFilterValue, $languageFilterValue);
        $templatePath = GeneralUtility::getFileAbsFileName('EXT:hd_golive/Resources/Private/Templates/GoLive/ExportPdf.html');
        if ($templatePath === null || $templatePath === '') {
            $this->addFlashMessage('PDF export template not found.');
            return $this->redirect('list', null, null, [
                'site' => $site,
                'session' => $session,
            ]);
        }
        $view = GeneralUtility::makeInstance(\TYPO3\CMS\Fluid\View\StandaloneView::class);
        $view->setTemplatePathAndFilename($templatePath);
        $view->assignMultiple([
            'siteTitle' => $exportData['siteTitle'],
            'sessionTitle' => $exportData['sessionTitle'],
            'createdAt' => $exportData['createdAt'],
            'includePages' => $includePages,
            'includeItems' => $includeItems,
            'includeNotes' => $includeNotes,
            'pages' => $exportData['pages'],
            'items' => $exportData['items'],
        ]);

        $tempDir = Environment::getVarPath() . '/tmp/hd_golive';
        $fontDir = $tempDir . '/fonts';
        $tempDir = str_replace("\0", '', $tempDir);
        $fontDir = str_replace("\0", '', $fontDir);
        GeneralUtility::mkdir_deep($tempDir);
        GeneralUtility::mkdir_deep($fontDir);
        $options = new \Dompdf\Options([
            'defaultFont' => 'Helvetica',
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'tempDir' => $tempDir,
            'fontDir' => $fontDir,
            'fontCache' => $fontDir,
            'chroot' => Environment::getProjectPath(),
        ]);
        $dompdf = new \Dompdf\Dompdf($options);
        $safeSite = preg_replace('/[^A-Za-z0-9_-]+/', '-', $site) ?? 'site';
        $filename = sprintf('go-live-%s-%s.pdf', $safeSite, date('Y-m-d_Hi'));
        try {
            $html = $view->render();
            $html = str_replace("\0", '', $html);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfOutput = $dompdf->output();
        } catch (\Throwable $error) {
            $paths = [
                'template' => $templatePath,
                'tempDir' => $tempDir,
                'fontDir' => $fontDir,
                'chroot' => Environment::getProjectPath(),
            ];
            $pathInfo = json_encode($paths);
            $this->addFlashMessage(
                'PDF export failed: ' . $error->getMessage() . ' | paths: ' . $pathInfo,
                '',
                \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR
            );
            return $this->redirect('exportWizard', null, null, [
                'site' => $site,
                'session' => $session,
            ]);
        }



        $streamFactory = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Http\StreamFactory::class);
        $stream = $streamFactory->createStream($pdfOutput);
        return new \TYPO3\CMS\Core\Http\Response(
            $stream,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => (string)strlen($pdfOutput),
            ]
        );
    }

    public function initializeStartSessionAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    public function startSessionAction(string $site): ResponseInterface
    {
        $site = trim($site);
        if ($site === '') {
            $this->addFlashMessage('Missing site identifier.');
            return $this->redirect('list');
        }

        try {
            $siteObject = $this->siteFinder->getSiteByIdentifier($site);
        } catch (\Throwable) {
            $this->addFlashMessage('Unknown site identifier.');
            return $this->redirect('list');
        }

        $title = sprintf('GO Live %s', date('Y-m-d H:i'));
        $now = $GLOBALS['EXEC_TIME'] ?? time();

        $connection = $this->connectionPool->getConnectionForTable('tx_hdgolive_session');
        $connection->insert('tx_hdgolive_session', [
            'pid' => 0,
            'tstamp' => $now,
            'crdate' => $now,
            'cruser_id' => (int)($GLOBALS['BE_USER']->user['uid'] ?? 0),
            'title' => $title,
            'site_identifier' => $site,
        ]);

        $sessionId = (int)$connection->lastInsertId('tx_hdgolive_session');
        $this->setActiveSessionState($site, $sessionId, $siteObject->getRootPageId());
        BackendUtility::setUpdateSignal('updatePageTree');

        return $this->redirect('list', null, null, [
            'site' => $site,
            'session' => $sessionId,
        ]);
    }

    public function initializeCloseSessionAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    public function closeSessionAction(string $site, int $session): ResponseInterface
    {
        $now = $GLOBALS['EXEC_TIME'] ?? time();
        $userId = (int)($GLOBALS['BE_USER']->user['uid'] ?? 0);
        $connection = $this->connectionPool->getConnectionForTable('tx_hdgolive_session');
        $connection->update('tx_hdgolive_session', [
            'tstamp' => $now,
            'closed' => 1,
            'closed_time' => $now,
            'closed_by' => $userId,
        ], [
            'uid' => $session,
        ]);
        $this->clearActiveSessionState();
        BackendUtility::setUpdateSignal('updatePageTree');
        return $this->redirect('list', null, null, [
            'site' => $site,
            'session' => $session,
        ]);
    }

    public function initializeReopenSessionAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    public function reopenSessionAction(string $site, int $session): ResponseInterface
    {
        $now = $GLOBALS['EXEC_TIME'] ?? time();
        $connection = $this->connectionPool->getConnectionForTable('tx_hdgolive_session');
        $connection->update('tx_hdgolive_session', [
            'tstamp' => $now,
            'closed' => 0,
            'closed_time' => 0,
            'closed_by' => 0,
        ], [
            'uid' => $session,
        ]);
        BackendUtility::setUpdateSignal('updatePageTree');
        return $this->redirect('list', null, null, [
            'site' => $site,
            'session' => $session,
        ]);
    }

    public function initializeUpdateSessionTitleAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    public function updateSessionTitleAction(string $site, int $session, string $title): ResponseInterface
    {
        $title = trim($title);
        $sessionRow = $this->getSessionRow($session);
        if ($sessionRow !== null && (int)($sessionRow['shared'] ?? 0) === 1) {
            $ownerId = (int)($sessionRow['cruser_id'] ?? 0);
            $currentUserId = (int)($this->getBackendUser()->user['uid'] ?? 0);
            if ($ownerId > 0 && $ownerId !== $currentUserId) {
                $this->addFlashMessage('Only the session owner can update the title.');
                return $this->redirect('list', null, null, [
                    'site' => $site,
                    'session' => $session,
                ]);
            }
        }
        if ($title !== '') {
            $connection = $this->connectionPool->getConnectionForTable('tx_hdgolive_session');
            $connection->update('tx_hdgolive_session', [
                'tstamp' => $GLOBALS['EXEC_TIME'] ?? time(),
                'title' => $title,
            ], [
                'uid' => $session,
            ]);
        }

        return $this->redirect('list', null, null, [
            'site' => $site,
            'session' => $session,
        ]);
    }

    public function initializeRemoveSessionAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    public function removeSessionAction(string $site, int $session): ResponseInterface
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_hdgolive_session');
        $connection->update('tx_hdgolive_session', [
            'deleted' => 1,
            'tstamp' => $GLOBALS['EXEC_TIME'] ?? time(),
            'shared' => 0,
        ], [
            'uid' => $session,
        ]);
        $this->clearActiveSessionState();
        BackendUtility::setUpdateSignal('updatePageTree');

        return $this->redirect('list', null, null, [
            'site' => $site,
            'session' => 0,
        ]);
    }

    public function initializeShareSessionAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    public function shareSessionAction(string $site, int $session): ResponseInterface
    {
        $site = trim($site);
        $sessionRow = $this->getSessionRow($session);
        if ($site === '' || $sessionRow === null || (string)$sessionRow['site_identifier'] !== $site) {
            $this->addFlashMessage('Invalid checklist session.');
            return $this->redirect('list', null, null, [
                'site' => $site,
                'session' => $session,
            ]);
        }
        if ((int)($sessionRow['closed'] ?? 0) === 1) {
            $this->addFlashMessage('Cannot share a closed checklist session.');
            return $this->redirect('list', null, null, [
                'site' => $site,
                'session' => $session,
            ]);
        }

        $connection = $this->connectionPool->getConnectionForTable('tx_hdgolive_session');
        $connection->update('tx_hdgolive_session', [
            'shared' => 0,
            'tstamp' => $GLOBALS['EXEC_TIME'] ?? time(),
        ], [
            'site_identifier' => $site,
        ]);
        $connection->update('tx_hdgolive_session', [
            'shared' => 1,
            'tstamp' => $GLOBALS['EXEC_TIME'] ?? time(),
        ], [
            'uid' => $session,
        ]);

        return $this->redirect('list', null, null, [
            'site' => $site,
            'session' => $session,
        ]);
    }

    public function initializeUnshareSessionAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    public function unshareSessionAction(string $site, int $session): ResponseInterface
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_hdgolive_session');
        $connection->update('tx_hdgolive_session', [
            'shared' => 0,
            'tstamp' => $GLOBALS['EXEC_TIME'] ?? time(),
        ], [
            'uid' => $session,
        ]);

        return $this->redirect('list', null, null, [
            'site' => $site,
            'session' => $session,
        ]);
    }


    public function startSessionButtonAction(string $site): ResponseInterface
    {
        return $this->startSessionAction($site);
    }

    public function closeSessionButtonAction(string $site, int $session): ResponseInterface
    {
        return $this->closeSessionAction($site, $session);
    }

    public function reopenSessionButtonAction(string $site, int $session): ResponseInterface
    {
        return $this->reopenSessionAction($site, $session);
    }

    public function removeSessionButtonAction(string $site, int $session): ResponseInterface
    {
        return $this->removeSessionAction($site, $session);
    }

    public function shareSessionButtonAction(string $site, int $session): ResponseInterface
    {
        return $this->shareSessionAction($site, $session);
    }

    public function unshareSessionButtonAction(string $site, int $session): ResponseInterface
    {
        return $this->unshareSessionAction($site, $session);
    }


    public function initializeTogglePageAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    public function togglePageAction(int $session, int $page, string $site, int $status = 0): ResponseInterface
    {
        if ($this->isSessionClosed($session)) {
            $this->addFlashMessage('Checklist session is closed.');
            return $this->redirect('list', null, null, [
                'site' => $site,
                'session' => $session,
            ]);
        }

        $status = GoLiveStatus::normalize($status);
        $now = $GLOBALS['EXEC_TIME'] ?? time();
        $userId = (int)($GLOBALS['BE_USER']->user['uid'] ?? 0);
        $checked = $status !== GoLiveStatus::PENDING;

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_pagecheck');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $existing = $queryBuilder
            ->select('uid')
            ->from('tx_hdgolive_pagecheck')
            ->where(
                $queryBuilder->expr()->eq('session', $queryBuilder->createNamedParameter($session, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('page', $queryBuilder->createNamedParameter($page, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchOne();

        $connection = $this->connectionPool->getConnectionForTable('tx_hdgolive_pagecheck');
        if ($existing) {
            $connection->update('tx_hdgolive_pagecheck', [
                'tstamp' => $now,
                'status' => $status,
                'checked' => $checked ? 1 : 0,
                'checked_time' => $checked ? $now : 0,
                'checked_by' => $checked ? $userId : 0,
            ], [
                'uid' => (int)$existing,
            ]);
        } else {
            $connection->insert('tx_hdgolive_pagecheck', [
                'pid' => 0,
                'tstamp' => $now,
                'crdate' => $now,
                'cruser_id' => $userId,
                'session' => $session,
                'page' => $page,
                'status' => $status,
                'checked' => $checked ? 1 : 0,
                'checked_time' => $checked ? $now : 0,
                'checked_by' => $checked ? $userId : 0,
            ]);
        }

        BackendUtility::setUpdateSignal('updatePageTree');

        return $this->redirect('list', null, null, [
            'site' => $site,
            'session' => $session,
        ]);
    }

    public function initializeToggleItemAction(): void
    {
        $this->assertAllowedHttpMethod($this->request, 'POST');
    }

    public function toggleItemAction(int $session, string $itemKey, string $site, int $status = 0): ResponseInterface
    {
        $itemKey = trim($itemKey);
        if ($itemKey === '') {
            return $this->redirect('list', null, null, [
                'site' => $site,
                'session' => $session,
            ]);
        }

        if ($this->isSessionClosed($session)) {
            $this->addFlashMessage('Checklist session is closed.');
            return $this->redirect('list', null, null, [
                'site' => $site,
                'session' => $session,
            ]);
        }

        $status = GoLiveStatus::normalize($status);
        $now = $GLOBALS['EXEC_TIME'] ?? time();
        $userId = (int)($GLOBALS['BE_USER']->user['uid'] ?? 0);
        $checked = $status !== GoLiveStatus::PENDING;

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_itemcheck');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $existing = $queryBuilder
            ->select('uid')
            ->from('tx_hdgolive_itemcheck')
            ->where(
                $queryBuilder->expr()->eq('session', $queryBuilder->createNamedParameter($session, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('item_key', $queryBuilder->createNamedParameter($itemKey))
            )
            ->executeQuery()
            ->fetchOne();

        $connection = $this->connectionPool->getConnectionForTable('tx_hdgolive_itemcheck');
        if ($existing) {
            $connection->update('tx_hdgolive_itemcheck', [
                'tstamp' => $now,
                'status' => $status,
                'checked' => $checked ? 1 : 0,
                'checked_time' => $checked ? $now : 0,
                'checked_by' => $checked ? $userId : 0,
            ], [
                'uid' => (int)$existing,
            ]);
        } else {
            $connection->insert('tx_hdgolive_itemcheck', [
                'pid' => 0,
                'tstamp' => $now,
                'crdate' => $now,
                'cruser_id' => $userId,
                'session' => $session,
                'item_key' => $itemKey,
                'status' => $status,
                'checked' => $checked ? 1 : 0,
                'checked_time' => $checked ? $now : 0,
                'checked_by' => $checked ? $userId : 0,
            ]);
        }

        return $this->redirect('list', null, null, [
            'site' => $site,
            'session' => $session,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCheckItems(string $siteIdentifier, int $sessionId): array
    {
        $itemsByKey = [];
        $rootPageId = null;
        try {
            $rootPageId = $this->siteFinder->getSiteByIdentifier($siteIdentifier)->getRootPageId();
        } catch (\Throwable) {
            $rootPageId = null;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_checkitem');
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        $pidConditions = [
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
        ];
        if ($rootPageId !== null) {
            $pidConditions[] = $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($rootPageId, ParameterType::INTEGER)
            );
        }
        $rows = $queryBuilder
            ->select('uid', 'pid', 'title', 'item_key', 'description', 'site_identifier')
            ->from('tx_hdgolive_checkitem')
            ->where(
                $queryBuilder->expr()->or(...$pidConditions)
            )
            ->orderBy('pid', 'ASC')
            ->addOrderBy('sorting', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $key = trim((string)$row['item_key']);
            if ($key === '') {
                continue;
            }
            $itemsByKey[$key] = [
                'key' => $key,
                'title' => (string)$row['title'],
                'description' => trim((string)($row['description'] ?? '')),
                'definitionUid' => (int)($row['uid'] ?? 0),
                'status' => GoLiveStatus::PENDING,
                'checkedById' => 0,
                'checkedTime' => 0,
                'itemcheckUid' => 0,
            ];
        }

        if ($itemsByKey === []) {
            return [];
        }

        $keys = array_keys($itemsByKey);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_itemcheck');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $checks = $queryBuilder
            ->select('uid', 'item_key', 'status', 'checked', 'checked_by', 'checked_time')
            ->from('tx_hdgolive_itemcheck')
            ->where(
                $queryBuilder->expr()->eq('session', $queryBuilder->createNamedParameter($sessionId, ParameterType::INTEGER)),
                $queryBuilder->expr()->in('item_key', $queryBuilder->createNamedParameter($keys, ArrayParameterType::STRING))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($checks as $checkRow) {
            $key = (string)$checkRow['item_key'];
            if (isset($itemsByKey[$key])) {
                $status = GoLiveStatus::normalize((int)($checkRow['status'] ?? 0));
                if ($status === GoLiveStatus::PENDING && (int)($checkRow['checked'] ?? 0) === 1) {
                    $status = GoLiveStatus::PASS;
                }
                $itemsByKey[$key]['status'] = $status;
                $itemsByKey[$key]['checkedById'] = (int)$checkRow['checked_by'];
                $itemsByKey[$key]['checkedTime'] = (int)$checkRow['checked_time'];
                $itemsByKey[$key]['itemcheckUid'] = (int)$checkRow['uid'];
            }
        }

        return array_values($itemsByKey);
    }

    private function getCheckItemForSession(string $siteIdentifier, int $sessionId, string $itemKey): ?array
    {
        foreach ($this->getCheckItems($siteIdentifier, $sessionId) as $item) {
            if ((string)($item['key'] ?? '') === $itemKey) {
                return $item;
            }
        }
        return null;
    }

    /**
     * @param int[] $itemcheckUids
     * @return array<int, array<int, string>>
     */
    private function getNotesPreview(string $table, string $foreignField, array $foreignUids): array
    {
        if ($foreignUids === []) {
            return [];
        }

        $statusLabels = [
            0 => LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note.status.todo', 'hd_golive') ?? 'To be solved',
            1 => LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note.status.solved', 'hd_golive') ?? 'Solved',
            2 => LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note.status.denied', 'hd_golive') ?? 'Denied',
        ];

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $rows = $queryBuilder
            ->select($foreignField, 'note_text', 'note_status', 'crdate')
            ->from($table)
            ->where(
                $queryBuilder->expr()->in(
                    $foreignField,
                    $queryBuilder->createNamedParameter($foreignUids, ArrayParameterType::INTEGER)
                )
            )
            ->orderBy($foreignField, 'ASC')
            ->addOrderBy('crdate', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        $notesByItemcheck = [];
        $counts = [];
        foreach ($rows as $row) {
            $foreignId = (int)$row[$foreignField];
            $counts[$foreignId] = $counts[$foreignId] ?? 0;
            if ($counts[$foreignId] >= 2) {
                continue;
            }
            $status = (int)($row['note_status'] ?? 0);
            $label = $statusLabels[$status] ?? $statusLabels[0];
            $text = trim((string)($row['note_text'] ?? ''));
            $text = preg_replace('/\\s+/', ' ', $text) ?? $text;
            if (strlen($text) > 120) {
                $text = substr($text, 0, 117) . '...';
            }
            $notesByItemcheck[$foreignId][] = sprintf('%s: %s', $label, $text);
            $counts[$foreignId]++;
        }

        return $notesByItemcheck;
    }

    private function setActiveSessionState(string $siteIdentifier, int $sessionId, int $rootPageId): void
    {
        $this->getBackendUser()->setAndSaveSessionData('hd_golive', [
            'site' => $siteIdentifier,
            'session' => $sessionId,
            'rootPageId' => $rootPageId,
        ]);
    }

    private function clearActiveSessionState(): void
    {
        $this->getBackendUser()->setAndSaveSessionData('hd_golive', null);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function canViewChecklist(): bool
    {
        $backendUser = $this->getBackendUser();
        if ($backendUser->isAdmin()) {
            return true;
        }

        if ($backendUser->check('modules', 'web_hdgolive')) {
            return true;
        }

        return $backendUser->check('custom_options', 'tx_hdgolive:checklist_view');
    }


    private function getSessionRow(int $sessionId): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_session');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $row = $queryBuilder
            ->select('uid', 'title', 'crdate', 'site_identifier', 'closed', 'shared', 'cruser_id')
            ->from('tx_hdgolive_session')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($sessionId, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: null;
    }

    private function getSharedSessionRow(string $siteIdentifier): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_session');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $row = $queryBuilder
            ->select('uid', 'title', 'crdate', 'site_identifier', 'closed', 'shared')
            ->from('tx_hdgolive_session')
            ->where(
                $queryBuilder->expr()->eq('site_identifier', $queryBuilder->createNamedParameter($siteIdentifier)),
                $queryBuilder->expr()->eq('shared', $queryBuilder->createNamedParameter(1, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('closed', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: null;
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     * @return array<int, array<string, mixed>>
     */
    private function sortPagesWithTranslations(array $pages): array
    {
        if ($pages === []) {
            return [];
        }

        $basePages = array_values(array_filter(
            $pages,
            static fn(array $page): bool => (int)($page['sys_language_uid'] ?? 0) === 0
        ));
        if ($basePages === []) {
            return $pages;
        }

        $baseIds = array_map(static fn(array $page): int => (int)$page['uid'], $basePages);
        $translationsByParent = $this->getTranslationsForPages($baseIds);

        $sorted = [];
        foreach ($basePages as $page) {
            $sorted[] = $page;
            foreach ($translationsByParent[(int)$page['uid']] ?? [] as $translation) {
                $translation['depth'] = (int)$page['depth'] + 1;
                $translation['indent'] = (int)$page['indent'] + 16;
                $translation['titleDisplay'] = $translation['nav_title'] !== '' ? $translation['nav_title'] : $translation['title'];
                $sorted[] = $translation;
            }
        }

        return $sorted;
    }

    /**
     * @param int[] $pageIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function getTranslationsForPages(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $rows = $queryBuilder
            ->select('uid', 'pid', 'title', 'nav_title', 'slug', 'doktype', 'hidden', 'module', 'content_from_pid', 'sys_language_uid', 'l10n_parent')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in(
                    'l10n_parent',
                    $queryBuilder->createNamedParameter($pageIds, ArrayParameterType::INTEGER)
                ),
                $queryBuilder->expr()->gt('sys_language_uid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER))
            )
            ->orderBy('l10n_parent', 'ASC')
            ->addOrderBy('sys_language_uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        $grouped = [];
        foreach ($rows as $row) {
            $parent = (int)$row['l10n_parent'];
            $grouped[$parent][] = $row;
        }

        return $grouped;
    }

    private function buildExportData(string $siteIdentifier, int $sessionId, bool $includeNotes, ?int $statusFilterValue = null, ?int $languageFilterValue = null): array
    {
        $siteTitle = $siteIdentifier;
        $pages = [];
        $checkItems = [];
        $rootPageId = null;
        $languageFlags = [];
        $siteObject = null;

        try {
            $siteObject = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
            $rootPageId = $siteObject->getRootPageId();
            try {
                $siteTitle = (string)$siteObject->getAttribute('websiteTitle');
            } catch (\Throwable) {
                $siteTitle = '';
            }
            if (trim($siteTitle) === '') {
                $rootRecord = BackendUtility::getRecord('pages', $rootPageId, 'title');
                $siteTitle = (string)($rootRecord['title'] ?? $siteObject->getIdentifier());
            }
            $pages = $this->pageTreeService->fetchTree($siteObject->getRootPageId());
            $pages = $this->sortPagesWithTranslations($pages);
        } catch (\Throwable) {
            $pages = [];
        }

        if ($siteObject !== null) {
            $defaultLanguage = $siteObject->getDefaultLanguage();
            $defaultFlagIdentifier = $defaultLanguage->getFlagIdentifier();
            if ($defaultFlagIdentifier !== '') {
                $languageFlags['0'] = $this->iconFactory
                    ->getIcon($defaultFlagIdentifier, \TYPO3\CMS\Core\Imaging\IconSize::SMALL)
                    ->render();
            }
            foreach ($siteObject->getLanguages() as $language) {
                $flagIdentifier = $language->getFlagIdentifier();
                if ($flagIdentifier === '') {
                    continue;
                }
                $languageFlags[(string)$language->getLanguageId()] = $this->iconFactory
                    ->getIcon($flagIdentifier, \TYPO3\CMS\Core\Imaging\IconSize::SMALL)
                    ->render();
            }
        }

        $statusByPage = [];
        $pageIds = array_map(static fn(array $page): int => (int)$page['uid'], $pages);
        if ($pageIds !== []) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_pagecheck');
            $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $rows = $queryBuilder
                ->select('uid', 'page', 'status', 'checked', 'checked_by', 'checked_time')
                ->from('tx_hdgolive_pagecheck')
                ->where(
                    $queryBuilder->expr()->eq('session', $queryBuilder->createNamedParameter($sessionId, ParameterType::INTEGER)),
                    $queryBuilder->expr()->in(
                        'page',
                        $queryBuilder->createNamedParameter($pageIds, ArrayParameterType::INTEGER)
                    )
                )
                ->executeQuery()
                ->fetchAllAssociative();
            foreach ($rows as $row) {
                $status = GoLiveStatus::normalize((int)($row['status'] ?? 0));
                if ($status === GoLiveStatus::PENDING && (int)($row['checked'] ?? 0) === 1) {
                    $status = GoLiveStatus::PASS;
                }
                $statusByPage[(int)$row['page']] = [
                    'status' => $status,
                    'checkedById' => (int)$row['checked_by'],
                    'checkedTime' => (int)$row['checked_time'],
                    'pagecheckUid' => (int)$row['uid'],
                ];
            }
        }

        $checkItems = $this->getCheckItems($siteIdentifier, $sessionId);
        $userIds = [];
        foreach ($pages as &$page) {
            $pageCheck = $statusByPage[(int)$page['uid']] ?? [
                'status' => GoLiveStatus::PENDING,
                'checkedById' => 0,
                'checkedTime' => 0,
                'pagecheckUid' => 0,
            ];
            $page['status'] = $pageCheck['status'];
            $page['statusLabel'] = $this->getStatusLabel($page['status']);
            $page['checkedById'] = $pageCheck['checkedById'];
            $page['checkedTime'] = $pageCheck['checkedTime'];
            $page['pagecheckUid'] = $pageCheck['pagecheckUid'];
            $languageKey = (string)($page['sys_language_uid'] ?? 0);
            $languageLabel = $languageLabels[$languageKey] ?? '';
            if ($languageLabel === '' && $siteObject !== null) {
                try {
                    $languageLabel = $this->getLanguageLabel($siteObject->getLanguageById((int)$languageKey));
                } catch (\Throwable) {
                    $languageLabel = '';
                }
            }
            $page['languageLabel'] = $languageLabel !== '' ? $languageLabel : $languageKey;
            $page['languageCode'] = '';
            if ($page['checkedById'] > 0) {
                $userIds[] = $page['checkedById'];
            }
        }
        unset($page);

        foreach ($checkItems as $item) {
            if (!empty($item['checkedById'])) {
                $userIds[] = (int)$item['checkedById'];
            }
        }
        $userIds = array_values(array_unique(array_filter($userIds)));
        $userNames = $this->getBackendUserNames($userIds);

        foreach ($pages as &$page) {
            if ($page['status'] === GoLiveStatus::PENDING) {
                $page['checkedBy'] = '';
                $page['checkedTime'] = 0;
            } else {
                $page['checkedBy'] = $userNames[$page['checkedById']] ?? '';
            }
        }
        unset($page);

        foreach ($checkItems as &$item) {
            if ($item['status'] === GoLiveStatus::PENDING) {
                $item['checkedBy'] = '';
                $item['checkedTime'] = 0;
            } else {
                $item['checkedBy'] = $userNames[$item['checkedById']] ?? '';
            }
            $item['statusLabel'] = $this->getStatusLabel((int)$item['status']);
        }
        unset($item);

        if ($statusFilterValue !== null) {
            $pages = array_values(array_filter(
                $pages,
                static fn(array $page): bool => (int)($page['status'] ?? GoLiveStatus::PENDING) === $statusFilterValue
            ));
            $checkItems = array_values(array_filter(
                $checkItems,
                static fn(array $item): bool => (int)($item['status'] ?? GoLiveStatus::PENDING) === $statusFilterValue
            ));
        }

        if ($languageFilterValue !== null) {
            $pages = array_values(array_filter(
                $pages,
                static fn(array $page): bool => (int)($page['sys_language_uid'] ?? 0) === $languageFilterValue
                    || (int)($page['depth'] ?? 0) === 0
            ));
        }

        if ($includeNotes) {
            $notesByItemcheck = [];
            $notesByPagecheck = [];
            $itemcheckUids = array_values(array_filter(array_map(
                static fn(array $item): int => (int)($item['itemcheckUid'] ?? 0),
                $checkItems
            )));
            if ($itemcheckUids !== []) {
                $notesByItemcheck = $this->getNotesForExport('tx_hdgolive_note', 'itemcheck', $itemcheckUids);
            }
            $pagecheckUids = array_values(array_filter(array_map(
                static fn(array $page): int => (int)($page['pagecheckUid'] ?? 0),
                $pages
            )));
            if ($pagecheckUids !== []) {
                $notesByPagecheck = $this->getNotesForExport('tx_hdgolive_pagenote', 'pagecheck', $pagecheckUids);
            }
            foreach ($checkItems as &$item) {
                $itemcheckUid = (int)($item['itemcheckUid'] ?? 0);
                $item['notes'] = $notesByItemcheck[$itemcheckUid] ?? [];
            }
            unset($item);
            foreach ($pages as &$page) {
                $pagecheckUid = (int)($page['pagecheckUid'] ?? 0);
                $page['notes'] = $notesByPagecheck[$pagecheckUid] ?? [];
            }
            unset($page);
        } else {
            foreach ($checkItems as &$item) {
                $item['notes'] = [];
            }
            unset($item);
            foreach ($pages as &$page) {
                $page['notes'] = [];
            }
            unset($page);
        }

        $sessionRow = $this->getSessionRow($sessionId);
        return [
            'siteTitle' => $siteTitle,
            'sessionTitle' => (string)($sessionRow['title'] ?? ''),
            'createdAt' => (int)($sessionRow['crdate'] ?? 0),
            'pages' => $pages,
            'items' => $checkItems,
        ];
    }

    /**
     * @param int[] $foreignUids
     * @return array<int, array<int, array<string, string>>>
     */
    private function getNotesForExport(string $table, string $foreignField, array $foreignUids): array
    {
        if ($foreignUids === []) {
            return [];
        }
        $statusLabels = [
            0 => LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note.status.todo', 'hd_golive') ?? 'To be solved',
            1 => LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note.status.solved', 'hd_golive') ?? 'Solved',
            2 => LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang_db.xlf:tx_hdgolive_note.status.denied', 'hd_golive') ?? 'Denied',
        ];

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $rows = $queryBuilder
            ->select($foreignField, 'note_text', 'note_status', 'crdate')
            ->from($table)
            ->where(
                $queryBuilder->expr()->in(
                    $foreignField,
                    $queryBuilder->createNamedParameter($foreignUids, ArrayParameterType::INTEGER)
                )
            )
            ->orderBy($foreignField, 'ASC')
            ->addOrderBy('crdate', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        $notesByForeign = [];
        foreach ($rows as $row) {
            $foreignId = (int)$row[$foreignField];
            $status = (int)($row['note_status'] ?? 0);
            $notesByForeign[$foreignId][] = [
                'status' => $statusLabels[$status] ?? $statusLabels[0],
                'text' => trim((string)($row['note_text'] ?? '')),
                'createdAt' => (int)($row['crdate'] ?? 0),
            ];
        }
        return $notesByForeign;
    }

    private function getStatusLabel(int $status): string
    {
        return match ($status) {
            GoLiveStatus::PASS => LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:status.pass', 'hd_golive') ?? 'Pass',
            GoLiveStatus::FAILED => LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:status.failed', 'hd_golive') ?? 'Failed',
            default => LocalizationUtility::translate('LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:status.pending', 'hd_golive') ?? 'To be checked',
        };
    }

    private function getLanguageCode(\TYPO3\CMS\Core\Site\Entity\SiteLanguage $language): string
    {
        $code = trim($language->getLocale()->getLanguageCode());
        if ($code === '') {
            $candidate = trim((string)$language->getHreflang(true));
            if ($candidate === '') {
                $candidate = trim((string)$language->getHreflang());
            }
            if ($candidate === '') {
                $candidate = trim((string)$language->getTypo3Language());
            }
            if ($candidate !== '' && preg_match('/^[a-z]{2}/i', $candidate, $matches) === 1) {
                $code = $matches[0];
            }
        }
        if ($code === '') {
            return '';
        }
        return strtoupper(substr($code, 0, 2));
    }

    private function getLanguageLabel(\TYPO3\CMS\Core\Site\Entity\SiteLanguage $language): string
    {
        $label = trim((string)$language->getTitle());
        if ($label !== '') {
            return $label;
        }
        $label = trim((string)$language->getNavigationTitle());
        if ($label !== '') {
            return $label;
        }
        $label = trim((string)$language->getHreflang(true));
        if ($label === '') {
            $label = trim((string)$language->getHreflang());
        }
        if ($label === '') {
            $label = trim((string)$language->getLocale());
        }
        if ($label === '') {
            $label = trim((string)$language->getTypo3Language());
        }
        return $label;
    }

    private function normalizeStatusFilter(?string $statusFilter): ?int
    {
        if ($statusFilter === null) {
            return null;
        }
        $statusFilter = trim($statusFilter);
        if ($statusFilter === '' || $statusFilter === 'all') {
            return null;
        }
        return match ($statusFilter) {
            '0', 'pending' => GoLiveStatus::PENDING,
            '1', 'pass' => GoLiveStatus::PASS,
            '2', 'failed' => GoLiveStatus::FAILED,
            default => null,
        };
    }

    private function normalizeLanguageFilter(?string $languageFilter): ?int
    {
        if ($languageFilter === null) {
            return null;
        }
        $languageFilter = trim($languageFilter);
        if ($languageFilter === '' || $languageFilter === 'all') {
            return null;
        }
        return (int)$languageFilter;
    }

    /**
     * @param int[] $userIds
     * @return array<int, string>
     */
    private function getBackendUserNames(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $rows = $queryBuilder
            ->select('uid', 'username', 'realName')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($userIds, ArrayParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $names = [];
        foreach ($rows as $row) {
            $uid = (int)$row['uid'];
            $realName = trim((string)($row['realName'] ?? ''));
            $username = trim((string)($row['username'] ?? ''));
            $names[$uid] = $realName !== '' ? $realName : $username;
        }
        return $names;
    }

    private function isSessionClosed(int $sessionId): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_session');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $closed = $queryBuilder
            ->select('closed')
            ->from('tx_hdgolive_session')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($sessionId, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchOne();

        return (int)$closed === 1;
    }
}
