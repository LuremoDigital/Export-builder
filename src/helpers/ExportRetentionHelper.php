<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\helpers;

use DateTimeImmutable;
use DateTimeZone;

final class ExportRetentionHelper
{
    public const ALLOWED_DAYS = [7, 30, 90];

    public static function normalizeDays(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'never') {
            return null;
        }

        $days = is_numeric($value) ? (int)$value : 0;

        return in_array($days, self::ALLOWED_DAYS, true) ? $days : null;
    }

    public static function isExpired(mixed $finishedAt, int $retentionDays, DateTimeImmutable $now): bool
    {
        if ($retentionDays <= 0 || $finishedAt === null || $finishedAt === '') {
            return false;
        }

        try {
            $finished = $finishedAt instanceof \DateTimeInterface
                ? DateTimeImmutable::createFromInterface($finishedAt)
                : new DateTimeImmutable((string)$finishedAt, new DateTimeZone('UTC'));
        } catch (\Throwable) {
            return false;
        }

        return $finished <= $now->modify(sprintf('-%d days', $retentionDays));
    }
}
