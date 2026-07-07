<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Luremo\DataExportBuilder\helpers\ExportRetentionHelper;
use PHPUnit\Framework\TestCase;

final class ExportRetentionHelperTest extends TestCase
{
    public function testNormalizesAllowedRetentionDays(): void
    {
        self::assertSame(7, ExportRetentionHelper::normalizeDays('7'));
        self::assertSame(30, ExportRetentionHelper::normalizeDays(30));
        self::assertSame(90, ExportRetentionHelper::normalizeDays('90'));
        self::assertNull(ExportRetentionHelper::normalizeDays('never'));
        self::assertNull(ExportRetentionHelper::normalizeDays('14'));
    }

    public function testDetectsExpiredFinishedAt(): void
    {
        $now = new DateTimeImmutable('2026-07-07 12:00:00', new DateTimeZone('UTC'));

        self::assertTrue(ExportRetentionHelper::isExpired('2026-06-07 12:00:00', 30, $now));
        self::assertFalse(ExportRetentionHelper::isExpired('2026-06-08 12:00:00', 30, $now));
        self::assertFalse(ExportRetentionHelper::isExpired(null, 30, $now));
        self::assertFalse(ExportRetentionHelper::isExpired('not-a-date', 30, $now));
    }
}
