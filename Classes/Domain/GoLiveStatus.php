<?php

declare(strict_types=1);

namespace Hyperdigital\HdGolive\Domain;

final class GoLiveStatus
{
    public const PENDING = 0;
    public const PASS = 1;
    public const FAILED = 2;

    public static function normalize(int $status): int
    {
        return match ($status) {
            self::PASS, self::FAILED => $status,
            default => self::PENDING,
        };
    }

    public static function isCompleted(int $status): bool
    {
        return $status === self::PASS || $status === self::FAILED;
    }
}
