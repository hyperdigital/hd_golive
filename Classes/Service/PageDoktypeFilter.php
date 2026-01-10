<?php

declare(strict_types=1);

namespace Hyperdigital\HdGolive\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

final class PageDoktypeFilter
{
    /**
     * @var int[]|null
     */
    private ?array $includedDoktypes = null;
    private bool $includeAllDoktypes = false;

    public function __construct(private readonly ExtensionConfiguration $extensionConfiguration) {}

    public function includesDoktype(int $doktype): bool
    {
        if ($this->includedDoktypes === null) {
            $this->includedDoktypes = $this->resolveIncludedDoktypes();
        }
        if ($this->includeAllDoktypes) {
            return true;
        }
        return in_array($doktype, $this->includedDoktypes, true);
    }

    /**
     * @param array<string, mixed> $pageRow
     */
    public function includesPageRow(array $pageRow): bool
    {
        $includeOverride = (int)($pageRow['tx_hdgolive_include_in_list'] ?? 0);
        if ($includeOverride === 1) {
            return true;
        }

        $excludeOverride = (int)($pageRow['tx_hdgolive_exclude_from_list'] ?? 0);
        if ($excludeOverride === 1) {
            return false;
        }

        $doktype = (int)($pageRow['doktype'] ?? 0);
        return $this->includesDoktype($doktype);
    }

    /**
     * @return int[]
     */
    private function resolveIncludedDoktypes(): array
    {
        $config = [];
        try {
            $config = $this->extensionConfiguration->get('hd_golive');
        } catch (\Throwable) {
            $config = [];
        }

        $raw = trim((string)($config['includeDoktypes'] ?? ''));
        if ($raw === '') {
            $this->includeAllDoktypes = true;
            return [];
        }

        $values = preg_split('/[\\s,]+/', $raw) ?: [];
        $doktypes = [];
        foreach ($values as $value) {
            if ($value === '') {
                continue;
            }
            if (is_numeric($value)) {
                $doktypes[] = (int)$value;
            }
        }
        $doktypes = array_values(array_unique($doktypes));
        if ($doktypes === []) {
            $this->includeAllDoktypes = true;
        }
        return $doktypes;
    }
}
