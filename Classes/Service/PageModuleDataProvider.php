<?php

declare(strict_types=1);

namespace Hyperdigital\HdGolive\Service;

use Hyperdigital\HdGolive\Domain\GoLiveStatus;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\ArrayParameterType;

final class PageModuleDataProvider
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
        private readonly IconFactory $iconFactory,
    ) {}

    /**
     * @return array<string, string|int>|null
     */
    public function resolveSessionForPage(int $pageId): ?array
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
        } catch (\Throwable) {
            return null;
        }

        $siteIdentifier = $site->getIdentifier();
        $state = $this->getBackendUser()->getSessionData('hd_golive');
        if (is_array($state)) {
            $candidateSession = (int)($state['session'] ?? 0);
            $candidateSite = (string)($state['site'] ?? '');
            if ($candidateSession > 0 && $candidateSite === $siteIdentifier) {
                $candidateRow = $this->getSessionRow($candidateSession);
                if ($candidateRow !== null && (int)($candidateRow['closed'] ?? 0) === 0) {
                    return [
                        'siteIdentifier' => $siteIdentifier,
                        'sessionId' => $candidateSession,
                    ];
                }
            }
        }

        $latest = $this->getLatestOpenSession($siteIdentifier);
        if ($latest === null) {
            return null;
        }

        return [
            'siteIdentifier' => $siteIdentifier,
            'sessionId' => (int)$latest['uid'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLanguageEntries(int $pageId, int $selectedLanguage): array
    {
        $sessionData = $this->resolveSessionForPage($pageId);
        if ($sessionData === null) {
            return [];
        }

        $siteIdentifier = (string)$sessionData['siteIdentifier'];
        $sessionId = (int)$sessionData['sessionId'];

        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
        } catch (\Throwable) {
            return [];
        }

        $basePageId = $this->getBasePageId($pageId);
        if ($basePageId === 0) {
            return [];
        }

        $languages = $site->getLanguages();
        $defaultLanguage = $site->getDefaultLanguage();
        $languageList = [$defaultLanguage];
        foreach ($languages as $language) {
            if ($language->getLanguageId() === 0) {
                continue;
            }
            $languageList[] = $language;
        }

        $entries = [];
        foreach ($languageList as $language) {
            $languageId = (int)$language->getLanguageId();
            if ($selectedLanguage !== -1 && $selectedLanguage !== $languageId) {
                continue;
            }

            $languagePageId = $this->getPageIdForLanguage($basePageId, $languageId);
            if ($languagePageId === 0) {
                continue;
            }

            $pageCheck = $this->getPageCheck($sessionId, $languagePageId);
            $status = $pageCheck['status'] ?? GoLiveStatus::PENDING;
            if ($status === GoLiveStatus::PENDING && ($pageCheck['checked'] ?? 0) === 1) {
                $status = GoLiveStatus::PASS;
            }

            $flagIdentifier = $language->getFlagIdentifier();
            $flagHtml = '';
            if ($flagIdentifier !== '') {
                $flagHtml = $this->iconFactory->getIcon($flagIdentifier, \TYPO3\CMS\Core\Imaging\IconSize::SMALL)->render();
            }

            $entries[] = [
                'languageId' => $languageId,
                'languageTitle' => $language->getTitle(),
                'languageFlag' => $flagHtml,
                'status' => $status,
                'sessionId' => $sessionId,
                'siteIdentifier' => $siteIdentifier,
                'pageId' => $languagePageId,
                'pagecheckUid' => (int)($pageCheck['uid'] ?? 0),
                'notesPreview' => [],
            ];
        }

        $pagecheckIds = array_values(array_filter(array_map(
            static fn(array $entry): int => (int)($entry['pagecheckUid'] ?? 0),
            $entries
        )));
        if ($pagecheckIds !== []) {
            $notesByCheck = $this->getNotesPreview($pagecheckIds);
            foreach ($entries as &$entry) {
                $pagecheckUid = (int)($entry['pagecheckUid'] ?? 0);
                $entry['notesPreview'] = $notesByCheck[$pagecheckUid] ?? [];
            }
            unset($entry);
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDebugInfo(int $pageId, int $selectedLanguage): array
    {
        $info = [
            'pageId' => $pageId,
            'selectedLanguage' => $selectedLanguage,
            'session' => null,
            'siteIdentifier' => null,
            'basePageId' => null,
            'languages' => [],
            'translationPageIds' => [],
        ];

        $sessionData = $this->resolveSessionForPage($pageId);
        if ($sessionData === null) {
            return $info;
        }

        $info['session'] = (int)$sessionData['sessionId'];
        $info['siteIdentifier'] = (string)$sessionData['siteIdentifier'];

        $basePageId = $this->getBasePageId($pageId);
        $info['basePageId'] = $basePageId;
        if ($basePageId === 0) {
            return $info;
        }

        try {
            $site = $this->siteFinder->getSiteByIdentifier((string)$sessionData['siteIdentifier']);
        } catch (\Throwable) {
            return $info;
        }

        $languages = $site->getLanguages();
        $defaultLanguage = $site->getDefaultLanguage();
        $languageList = [$defaultLanguage];
        foreach ($languages as $language) {
            if ($language->getLanguageId() === 0) {
                continue;
            }
            $languageList[] = $language;
        }

        foreach ($languageList as $language) {
            $languageId = (int)$language->getLanguageId();
            $info['languages'][] = [
                'id' => $languageId,
                'title' => $language->getTitle(),
            ];
            $translationPageId = $this->getPageIdForLanguage($basePageId, $languageId);
            if ($translationPageId > 0) {
                $info['translationPageIds'][$languageId] = $translationPageId;
            }
        }

        return $info;
    }

    private function getPageIdForLanguage(int $pageId, int $languageId): int
    {
        if ($languageId <= 0) {
            return $pageId;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $uid = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageId, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchOne();

        return $uid ? (int)$uid : 0;
    }

    private function getBasePageId(int $pageId): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $row = $queryBuilder
            ->select('uid', 'sys_language_uid', 'l10n_parent')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            return 0;
        }

        $language = (int)($row['sys_language_uid'] ?? 0);
        if ($language > 0) {
            return (int)($row['l10n_parent'] ?? 0);
        }

        return (int)$row['uid'];
    }

    /**
     * @return array<string, int>
     */
    private function getPageCheck(int $sessionId, int $pageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_pagecheck');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $row = $queryBuilder
            ->select('uid', 'status', 'checked')
            ->from('tx_hdgolive_pagecheck')
            ->where(
                $queryBuilder->expr()->eq('session', $queryBuilder->createNamedParameter($sessionId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('page', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            return [];
        }

        return [
            'uid' => (int)$row['uid'],
            'status' => (int)$row['status'],
            'checked' => (int)$row['checked'],
        ];
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

    private function getLatestOpenSession(string $siteIdentifier): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_session');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $backendUserId = (int)($this->getBackendUser()->user['uid'] ?? 0);
        $row = $queryBuilder
            ->select('uid', 'site_identifier', 'closed')
            ->from('tx_hdgolive_session')
            ->where(
                $queryBuilder->expr()->eq('site_identifier', $queryBuilder->createNamedParameter($siteIdentifier)),
                $queryBuilder->expr()->eq('closed', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('shared', $queryBuilder->createNamedParameter(1, ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('cruser_id', $queryBuilder->createNamedParameter($backendUserId, ParameterType::INTEGER))
                )
            )
            ->orderBy('crdate', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: null;
    }


    /**
     * @param int[] $pagecheckIds
     * @return array<int, array<int, array<string, int|string>>>
     */
    private function getNotesPreview(array $pagecheckIds): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_pagenote');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $rows = $queryBuilder
            ->select('pagecheck', 'note_text', 'note_status', 'crdate')
            ->from('tx_hdgolive_pagenote')
            ->where(
                $queryBuilder->expr()->in(
                    'pagecheck',
                    $queryBuilder->createNamedParameter($pagecheckIds, ArrayParameterType::INTEGER)
                )
            )
            ->orderBy('pagecheck', 'ASC')
            ->addOrderBy('crdate', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        $notesByCheck = [];
        foreach ($rows as $row) {
            $pagecheckId = (int)$row['pagecheck'];
            if (!isset($notesByCheck[$pagecheckId])) {
                $notesByCheck[$pagecheckId] = [];
            }
            if (count($notesByCheck[$pagecheckId]) >= 2) {
                continue;
            }
            $notesByCheck[$pagecheckId][] = [
                'text' => trim((string)($row['note_text'] ?? '')),
                'status' => (int)($row['note_status'] ?? 0),
            ];
        }

        return $notesByCheck;
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
