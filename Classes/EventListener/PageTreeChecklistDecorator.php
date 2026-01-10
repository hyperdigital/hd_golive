<?php

declare(strict_types=1);

namespace Hyperdigital\HdGolive\EventListener;

use TYPO3\CMS\Backend\Controller\Event\AfterPageTreeItemsPreparedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Backend\Dto\Tree\Label\Label;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Hyperdigital\HdGolive\Domain\GoLiveStatus;
use Hyperdigital\HdGolive\Service\PageDoktypeFilter;

#[AsEventListener('hd-golive.page-tree-checklist')]
final class PageTreeChecklistDecorator
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
        private readonly PageDoktypeFilter $pageDoktypeFilter,
    ) {}

    public function __invoke(AfterPageTreeItemsPreparedEvent $event): void
    {
        if (!$this->canViewChecklist()) {
            return;
        }

        $state = $this->getBackendUser()->getSessionData('hd_golive');
        if (!is_array($state)) {
            return;
        }

        $sessionId = (int)($state['session'] ?? 0);
        $siteIdentifier = (string)($state['site'] ?? '');
        if ($sessionId <= 0 || $siteIdentifier === '') {
            return;
        }

        $items = $event->getItems();
        $pageIds = [];
        foreach ($items as $item) {
            if (($item['recordType'] ?? '') !== 'pages') {
                continue;
            }
            $pageIds[] = (int)$item['identifier'];
        }

        if ($pageIds === []) {
            return;
        }

        $pageIdsInSite = [];
        foreach ($pageIds as $pageId) {
            try {
                $site = $this->siteFinder->getSiteByPageId($pageId);
            } catch (\Throwable) {
                continue;
            }
            if ($site->getIdentifier() === $siteIdentifier) {
                $pageIdsInSite[] = $pageId;
            }
        }

        if ($pageIdsInSite === []) {
            return;
        }

        $includedPageIds = $this->filterPageIdsByDoktype($pageIdsInSite);
        if ($includedPageIds === []) {
            return;
        }

        $statusByPage = [];
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_pagecheck');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $rows = $queryBuilder
            ->select('page', 'status', 'checked')
            ->from('tx_hdgolive_pagecheck')
            ->where(
                $queryBuilder->expr()->eq('session', $queryBuilder->createNamedParameter($sessionId, ParameterType::INTEGER)),
                $queryBuilder->expr()->in(
                    'page',
                    $queryBuilder->createNamedParameter($includedPageIds, ArrayParameterType::INTEGER)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $status = GoLiveStatus::normalize((int)($row['status'] ?? 0));
            if ($status === GoLiveStatus::PENDING && (int)($row['checked'] ?? 0) === 1) {
                $status = GoLiveStatus::PASS;
            }
            $statusByPage[(int)$row['page']] = $status;
        }

        foreach ($items as &$item) {
            if (($item['recordType'] ?? '') !== 'pages') {
                continue;
            }
            $pageId = (int)$item['identifier'];
            if (!in_array($pageId, $includedPageIds, true)) {
                continue;
            }
            $status = $statusByPage[$pageId] ?? GoLiveStatus::PENDING;
            $color = match ($status) {
                GoLiveStatus::PASS => '#2f6f5e',
                GoLiveStatus::FAILED => '#b02a37',
                default => '#c88f00',
            };
            $item['labels'] = array_values(array_merge($item['labels'] ?? [], [
                new Label(
                    label: 'GO Live',
                    color: $color,
                    priority: 50,
                ),
            ]));
            // Only left-side label highlight, no right-side status icon.
        }
        unset($item);

        $event->setItems($items);
    }

    /**
     * @param int[] $pageIds
     * @return int[]
     */
    private function filterPageIdsByDoktype(array $pageIds): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $rows = $queryBuilder
            ->select('uid', 'doktype', 'tx_hdgolive_exclude_from_list', 'tx_hdgolive_include_in_list')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($pageIds, ArrayParameterType::INTEGER)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $included = [];
        foreach ($rows as $row) {
            if ($this->pageDoktypeFilter->includesPageRow($row)) {
                $included[] = (int)$row['uid'];
            }
        }

        return $included;
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
}
