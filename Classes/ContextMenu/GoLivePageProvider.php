<?php

declare(strict_types=1);

namespace Hyperdigital\HdGolive\ContextMenu;

use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Doctrine\DBAL\ParameterType;
use Hyperdigital\HdGolive\Domain\GoLiveStatus;
use Hyperdigital\HdGolive\Service\PageDoktypeFilter;

final class GoLivePageProvider extends AbstractProvider
{
    protected $table = 'pages';

    protected $itemsConfiguration = [
        'divider_hdgolive' => [
            'type' => 'divider',
        ],
        'hdgolive_status_pending' => [
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:contextmenu.status.pending',
            'iconIdentifier' => 'actions-circle',
            'callbackAction' => 'setGoLivePageStatus',
        ],
        'hdgolive_status_pass' => [
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:contextmenu.status.pass',
            'iconIdentifier' => 'actions-check-circle',
            'callbackAction' => 'setGoLivePageStatus',
        ],
        'hdgolive_status_failed' => [
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:contextmenu.status.failed',
            'iconIdentifier' => 'actions-close',
            'callbackAction' => 'setGoLivePageStatus',
        ],
        'hdgolive_list_include' => [
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:contextmenu.list.include',
            'iconIdentifier' => 'actions-add',
            'callbackAction' => 'setGoLivePageInclusion',
        ],
        'hdgolive_list_exclude' => [
            'label' => 'LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:contextmenu.list.exclude',
            'iconIdentifier' => 'actions-remove',
            'callbackAction' => 'setGoLivePageInclusion',
        ],
    ];

    private int $sessionId = 0;
    private string $siteIdentifier = '';
    private int $status = GoLiveStatus::PENDING;
    private bool $isIncludedInList = false;
    /**
     * @var array<string, mixed>
     */
    private array $pageRow = [];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
        private readonly PageDoktypeFilter $pageDoktypeFilter,
    ) {
        parent::__construct();
    }

    public function getPriority(): int
    {
        return 80;
    }

    public function canHandle(): bool
    {
        if ($this->table !== 'pages' || $this->context !== 'tree') {
            return false;
        }

        $state = $this->getBackendUser()->getSessionData('hd_golive');
        if (!is_array($state)) {
            return false;
        }

        $this->sessionId = (int)($state['session'] ?? 0);
        $this->siteIdentifier = (string)($state['site'] ?? '');
        if ($this->sessionId <= 0 || $this->siteIdentifier === '') {
            return false;
        }

        $pageId = (int)$this->identifier;
        if ($pageId <= 0) {
            return false;
        }

        $pageRow = $this->getPageChecklistRow($pageId);
        if ($pageRow === null) {
            return false;
        }
        if ((int)($pageRow['sys_language_uid'] ?? 0) !== 0) {
            return false;
        }
        $this->pageRow = $pageRow;
        $this->isIncludedInList = $this->pageDoktypeFilter->includesPageRow($pageRow);

        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
        } catch (\Throwable) {
            return false;
        }

        if ($site->getIdentifier() !== $this->siteIdentifier) {
            return false;
        }

        $this->status = $this->getPageStatus($this->sessionId, $pageId);
        return true;
    }

    public function addItems(array $items): array
    {
        if (!$this->canHandle()) {
            return $items;
        }

        $originalConfiguration = $this->itemsConfiguration;
        $itemsConfiguration = $this->itemsConfiguration;

        if (!$this->isIncludedInList) {
            unset(
                $itemsConfiguration['hdgolive_status_pending'],
                $itemsConfiguration['hdgolive_status_pass'],
                $itemsConfiguration['hdgolive_status_failed']
            );
        } else {
            $itemsConfiguration['hdgolive_status_pending']['disabled'] = $this->status === GoLiveStatus::PENDING;
            $itemsConfiguration['hdgolive_status_pass']['disabled'] = $this->status === GoLiveStatus::PASS;
            $itemsConfiguration['hdgolive_status_failed']['disabled'] = $this->status === GoLiveStatus::FAILED;
        }

        if ($this->isIncludedInList) {
            unset($itemsConfiguration['hdgolive_list_include']);
        } else {
            unset($itemsConfiguration['hdgolive_list_exclude']);
        }

        $this->itemsConfiguration = $itemsConfiguration;
        $items = parent::addItems($items);
        $this->itemsConfiguration = $originalConfiguration;

        return $items;
    }

    protected function getAdditionalAttributes(string $itemName): array
    {
        if (!str_starts_with($itemName, 'hdgolive_status_')) {
            if ($itemName === 'hdgolive_list_include' || $itemName === 'hdgolive_list_exclude') {
                $action = $itemName === 'hdgolive_list_include' ? 'include' : 'exclude';
                return [
                    'data-callback-module' => '@hyperdigital/hd-golive/context-menu',
                    'data-session' => (string)$this->sessionId,
                    'data-site' => $this->siteIdentifier,
                    'data-action' => $action,
                ];
            }
            return [];
        }
        $status = match ($itemName) {
            'hdgolive_status_pass' => GoLiveStatus::PASS,
            'hdgolive_status_failed' => GoLiveStatus::FAILED,
            default => GoLiveStatus::PENDING,
        };

        return [
            'data-callback-module' => '@hyperdigital/hd-golive/context-menu',
            'data-session' => (string)$this->sessionId,
            'data-site' => $this->siteIdentifier,
            'data-status' => (string)$status,
        ];
    }

    private function getPageStatus(int $sessionId, int $pageId): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_hdgolive_pagecheck');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $row = $queryBuilder
            ->select('status', 'checked')
            ->from('tx_hdgolive_pagecheck')
            ->where(
                $queryBuilder->expr()->eq('session', $queryBuilder->createNamedParameter($sessionId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('page', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            return GoLiveStatus::PENDING;
        }
        $status = GoLiveStatus::normalize((int)($row['status'] ?? 0));
        if ($status === GoLiveStatus::PENDING && (int)($row['checked'] ?? 0) === 1) {
            return GoLiveStatus::PASS;
        }
        return $status;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPageChecklistRow(int $pageId): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $row = $queryBuilder
            ->select('doktype', 'sys_language_uid', 'tx_hdgolive_exclude_from_list', 'tx_hdgolive_include_in_list')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: null;
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
