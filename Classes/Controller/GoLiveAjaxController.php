<?php

declare(strict_types=1);

namespace Hyperdigital\HdGolive\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Doctrine\DBAL\ParameterType;
use Hyperdigital\HdGolive\Domain\GoLiveStatus;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use Hyperdigital\HdGolive\Service\PageModuleDataProvider;

final class GoLiveAjaxController
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
        private readonly PageModuleDataProvider $pageModuleDataProvider,
    ) {}

    public function togglePage(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->canViewChecklist()) {
            return new JsonResponse(['success' => false, 'message' => 'Checklist view not enabled for your user group.']);
        }
        $data = $request->getParsedBody() ?? [];
        $sessionId = (int)($data['session'] ?? 0);
        $pageId = (int)($data['page'] ?? 0);
        $status = GoLiveStatus::normalize((int)($data['status'] ?? 0));

        if ($sessionId <= 0 || $pageId <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Missing session or page.']);
        }

        $state = $this->getBackendUser()->getSessionData('hd_golive');
        if (!is_array($state) || (int)($state['session'] ?? 0) !== $sessionId) {
            return new JsonResponse(['success' => false, 'message' => 'No active checklist session.']);
        }

        $siteIdentifier = (string)($state['site'] ?? '');
        if ($siteIdentifier === '') {
            return new JsonResponse(['success' => false, 'message' => 'No active site.']);
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
        } catch (\Throwable) {
            return new JsonResponse(['success' => false, 'message' => 'Page not in a site.']);
        }

        if ($site->getIdentifier() !== $siteIdentifier) {
            return new JsonResponse(['success' => false, 'message' => 'Page not in active site.']);
        }

        $now = $GLOBALS['EXEC_TIME'] ?? time();
        $userId = (int)($this->getBackendUser()->user['uid'] ?? 0);
        $checked = $status !== GoLiveStatus::PENDING;

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_pagecheck');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $existing = $queryBuilder
            ->select('uid')
            ->from('tx_hdgolive_pagecheck')
            ->where(
                $queryBuilder->expr()->eq('session', $queryBuilder->createNamedParameter($sessionId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('page', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER))
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
                'session' => $sessionId,
                'page' => $pageId,
                'status' => $status,
                'checked' => $checked ? 1 : 0,
                'checked_time' => $checked ? $now : 0,
                'checked_by' => $checked ? $userId : 0,
            ]);
        }

        return new JsonResponse(['success' => true, 'status' => $status]);
    }

    public function togglePageModule(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->canViewChecklist()) {
            return new JsonResponse(['success' => false, 'message' => 'Checklist view not enabled for your user group.']);
        }
        $data = $request->getParsedBody() ?? [];
        $sessionId = (int)($data['session'] ?? 0);
        $pageId = (int)($data['page'] ?? 0);
        $siteIdentifier = (string)($data['site'] ?? '');
        $status = GoLiveStatus::normalize((int)($data['status'] ?? 0));

        if ($sessionId <= 0 || $pageId <= 0 || $siteIdentifier === '') {
            return new JsonResponse(['success' => false, 'message' => 'Missing session, site or page.']);
        }

        if ($this->isSessionClosed($sessionId)) {
            return new JsonResponse(['success' => false, 'message' => 'Checklist session is closed.']);
        }

        $sessionRow = $this->getSessionRow($sessionId);
        if ($sessionRow === null || (string)$sessionRow['site_identifier'] !== $siteIdentifier) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid session.']);
        }

        $now = $GLOBALS['EXEC_TIME'] ?? time();
        $userId = (int)($this->getBackendUser()->user['uid'] ?? 0);
        $checked = $status !== GoLiveStatus::PENDING;

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_pagecheck');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $existing = $queryBuilder
            ->select('uid')
            ->from('tx_hdgolive_pagecheck')
            ->where(
                $queryBuilder->expr()->eq('session', $queryBuilder->createNamedParameter($sessionId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('page', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchOne();

        $connection = $this->connectionPool->getConnectionForTable('tx_hdgolive_pagecheck');
        $pagecheckUid = 0;
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
            $pagecheckUid = (int)$existing;
        } else {
            $connection->insert('tx_hdgolive_pagecheck', [
                'pid' => 0,
                'tstamp' => $now,
                'crdate' => $now,
                'cruser_id' => $userId,
                'session' => $sessionId,
                'page' => $pageId,
                'status' => $status,
                'checked' => $checked ? 1 : 0,
                'checked_time' => $checked ? $now : 0,
                'checked_by' => $checked ? $userId : 0,
            ]);
            $pagecheckUid = (int)$connection->lastInsertId('tx_hdgolive_pagecheck');
        }

        $editUrl = '';
        if ($pagecheckUid > 0) {
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $returnUrl = (string)$uriBuilder->buildUriFromRoute('web_hdgolive', [
                'site' => $siteIdentifier,
                'session' => $sessionId,
            ]);
            $editUrl = (string)$uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [
                    'tx_hdgolive_pagecheck' => [
                        $pagecheckUid => 'edit',
                    ],
                ],
                'returnUrl' => $returnUrl,
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'status' => $status,
            'checkedBy' => $checked ? $this->getBackendUserName($userId) : '',
            'checkedTime' => $checked ? $now : 0,
            'editUrl' => $editUrl,
        ]);
    }

    public function toggleItemModule(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->canViewChecklist()) {
            return new JsonResponse(['success' => false, 'message' => 'Checklist view not enabled for your user group.']);
        }
        $data = $request->getParsedBody() ?? [];
        $sessionId = (int)($data['session'] ?? 0);
        $itemKey = trim((string)($data['itemKey'] ?? ''));
        $siteIdentifier = (string)($data['site'] ?? '');
        $status = GoLiveStatus::normalize((int)($data['status'] ?? 0));

        if ($sessionId <= 0 || $itemKey === '' || $siteIdentifier === '') {
            return new JsonResponse(['success' => false, 'message' => 'Missing session, site or item.']);
        }

        if ($this->isSessionClosed($sessionId)) {
            return new JsonResponse(['success' => false, 'message' => 'Checklist session is closed.']);
        }

        $sessionRow = $this->getSessionRow($sessionId);
        if ($sessionRow === null || (string)$sessionRow['site_identifier'] !== $siteIdentifier) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid session.']);
        }

        $now = $GLOBALS['EXEC_TIME'] ?? time();
        $userId = (int)($this->getBackendUser()->user['uid'] ?? 0);
        $checked = $status !== GoLiveStatus::PENDING;

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_itemcheck');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $existing = $queryBuilder
            ->select('uid')
            ->from('tx_hdgolive_itemcheck')
            ->where(
                $queryBuilder->expr()->eq('session', $queryBuilder->createNamedParameter($sessionId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('item_key', $queryBuilder->createNamedParameter($itemKey))
            )
            ->executeQuery()
            ->fetchOne();

        $connection = $this->connectionPool->getConnectionForTable('tx_hdgolive_itemcheck');
        $itemcheckUid = 0;
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
            $itemcheckUid = (int)$existing;
        } else {
            $connection->insert('tx_hdgolive_itemcheck', [
                'pid' => 0,
                'tstamp' => $now,
                'crdate' => $now,
                'cruser_id' => $userId,
                'session' => $sessionId,
                'item_key' => $itemKey,
                'status' => $status,
                'checked' => $checked ? 1 : 0,
                'checked_time' => $checked ? $now : 0,
                'checked_by' => $checked ? $userId : 0,
            ]);
            $itemcheckUid = (int)$connection->lastInsertId('tx_hdgolive_itemcheck');
        }

        $editUrl = '';
        if ($itemcheckUid > 0) {
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $returnUrl = (string)$uriBuilder->buildUriFromRoute('web_hdgolive', [
                'site' => $siteIdentifier,
                'session' => $sessionId,
            ]);
            $editUrl = (string)$uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [
                    'tx_hdgolive_itemcheck' => [
                        $itemcheckUid => 'edit',
                    ],
                ],
                'returnUrl' => $returnUrl,
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'status' => $status,
            'checkedBy' => $checked ? $this->getBackendUserName($userId) : '',
            'checkedTime' => $checked ? $now : 0,
            'editUrl' => $editUrl,
        ]);
    }

    public function pageModuleEntries(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->canViewChecklist()) {
            return new JsonResponse(['success' => false, 'message' => 'Checklist view not enabled for your user group.']);
        }
        $data = array_merge($request->getQueryParams(), $request->getParsedBody() ?? []);
        $pageId = (int)($data['page'] ?? 0);
        $selectedLanguage = (int)($data['language'] ?? 0);
        $returnUrl = (string)($data['returnUrl'] ?? '');
        $debugEnabled = (int)($data['debug'] ?? 0) === 1;

        if ($pageId <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Missing page.']);
        }

        $entries = $this->pageModuleDataProvider->getLanguageEntries($pageId, $selectedLanguage);
        if ($entries === []) {
            $payload = ['success' => true, 'entries' => []];
            if ($debugEnabled) {
                $payload['debug'] = $this->pageModuleDataProvider->getDebugInfo($pageId, $selectedLanguage);
            }
            return new JsonResponse($payload);
        }

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        foreach ($entries as &$entry) {
            $pagecheckUid = (int)($entry['pagecheckUid'] ?? 0);
            if ($pagecheckUid > 0) {
                $entry['editUrl'] = (string)$uriBuilder->buildUriFromRoute('record_edit', [
                    'edit' => [
                        'tx_hdgolive_pagecheck' => [
                            $pagecheckUid => 'edit',
                        ],
                    ],
                    'returnUrl' => $returnUrl,
                ]);
            } else {
                $entry['editUrl'] = (string)$uriBuilder->buildUriFromRoute('record_edit', [
                    'edit' => [
                        'tx_hdgolive_pagecheck' => [
                            0 => 'new',
                        ],
                    ],
                    'defVals' => [
                        'tx_hdgolive_pagecheck' => [
                            'session' => (int)$entry['sessionId'],
                            'page' => (int)$entry['pageId'],
                            'pid' => 0,
                            'checked_by' => (int)($this->getBackendUser()->user['uid'] ?? 0),
                        ],
                    ],
                    'returnUrl' => $returnUrl,
                ]);
            }
        }
        unset($entry);

        $payload = ['success' => true, 'entries' => $entries];
        if ($debugEnabled) {
            $payload['debug'] = $this->pageModuleDataProvider->getDebugInfo($pageId, $selectedLanguage);
        }
        return new JsonResponse($payload);
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
            ->select('uid', 'site_identifier', 'closed')
            ->from('tx_hdgolive_session')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($sessionId, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: null;
    }

    private function isSessionClosed(int $sessionId): bool
    {
        $row = $this->getSessionRow($sessionId);
        return $row !== null && (int)$row['closed'] === 1;
    }

    private function getBackendUserName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $row = $queryBuilder
            ->select('uid', 'username', 'realName')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($userId, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            return '';
        }
        $realName = trim((string)($row['realName'] ?? ''));
        $username = trim((string)($row['username'] ?? ''));
        return $realName !== '' ? $realName : $username;
    }
}
