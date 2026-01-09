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
    ];

    private int $sessionId = 0;
    private string $siteIdentifier = '';
    private int $status = GoLiveStatus::PENDING;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
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

        $this->itemsConfiguration['hdgolive_status_pending']['disabled'] = $this->status === GoLiveStatus::PENDING;
        $this->itemsConfiguration['hdgolive_status_pass']['disabled'] = $this->status === GoLiveStatus::PASS;
        $this->itemsConfiguration['hdgolive_status_failed']['disabled'] = $this->status === GoLiveStatus::FAILED;

        return parent::addItems($items);
    }

    protected function getAdditionalAttributes(string $itemName): array
    {
        if (!str_starts_with($itemName, 'hdgolive_status_')) {
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

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
