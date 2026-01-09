<?php

declare(strict_types=1);

namespace Hyperdigital\HdGolive\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Doctrine\DBAL\ParameterType;

final class PageTreeService
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchTree(int $rootPageId): array
    {
        $pages = [];
        $this->appendPage($rootPageId, 0, $pages);
        return $pages;
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     */
    private function appendPage(int $pageId, int $depth, array &$pages): void
    {
        $page = $this->getPage($pageId);
        if ($page === null) {
            return;
        }

        $page['depth'] = $depth;
        $page['indent'] = $depth * 16;
        $page['titleDisplay'] = $page['nav_title'] !== '' ? $page['nav_title'] : $page['title'];
        $pages[] = $page;

        foreach ($this->getChildren($pageId) as $child) {
            $this->appendPage((int)$child['uid'], $depth + 1, $pages);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPage(int $pageId): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $row = $queryBuilder
            ->select('uid', 'pid', 'title', 'nav_title', 'slug', 'doktype', 'hidden', 'module', 'content_from_pid', 'sys_language_uid')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getChildren(int $pageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER)))
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
