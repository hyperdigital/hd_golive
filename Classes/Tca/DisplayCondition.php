<?php

declare(strict_types=1);

namespace Hyperdigital\HdGolive\Tca;

use Hyperdigital\HdGolive\Service\PageDoktypeFilter;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

final class DisplayCondition
{
    /**
     * @param array<string, mixed> $params
     */
    public function showExcludeCheckbox(array $params): bool
    {
        return $this->isDefaultLanguage($params) && $this->isDoktypeIncluded($params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function showIncludeCheckbox(array $params): bool
    {
        return $this->isDefaultLanguage($params) && !$this->isDoktypeIncluded($params);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function isDoktypeIncluded(array $params): bool
    {
        $doktype = $this->resolveDoktype($params);
        if ($doktype === null) {
            return true;
        }
        $filter = GeneralUtility::makeInstance(PageDoktypeFilter::class);
        return $filter->includesDoktype($doktype);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveDoktype(array $params): ?int
    {
        $record = $params['record'] ?? $params['row'] ?? $params['databaseRow'] ?? [];
        if (is_array($record)) {
            if (isset($record['doktype'])) {
                if (is_array($record['doktype'])) {
                    return (int)$record['doktype'][0];
                }
                return (int)$record['doktype'];
            }
            if (isset($record['uid'])) {
                $uid = (int)$record['uid'];
                if ($uid > 0) {
                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
                    $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
                    $doktype = $queryBuilder
                        ->select('doktype')
                        ->from('pages')
                        ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)))
                        ->executeQuery()
                        ->fetchOne();
                    if ($doktype !== false) {
                        return (int)$doktype;
                    }
                }
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function isDefaultLanguage(array $params): bool
    {
        $record = $params['record'] ?? $params['row'] ?? $params['databaseRow'] ?? [];
        if (is_array($record) && array_key_exists('sys_language_uid', $record)) {
            return (int)$record['sys_language_uid'] === 0;
        }
        if (is_array($record) && isset($record['uid'])) {
            $uid = (int)$record['uid'];
            if ($uid > 0) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
                $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
                $language = $queryBuilder
                    ->select('sys_language_uid')
                    ->from('pages')
                    ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)))
                    ->executeQuery()
                    ->fetchOne();
                if ($language !== false) {
                    return (int)$language === 0;
                }
            }
        }
        return true;
    }
}
