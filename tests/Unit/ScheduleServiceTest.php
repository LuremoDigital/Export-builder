<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use DateTimeImmutable;
use Luremo\DataExportBuilder\helpers\CapabilityHelper;
use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\services\ScheduleService;
use PHPUnit\Framework\TestCase;

final class ScheduleServiceTest extends TestCase
{
    public function testNormalizeSettingsDefaultsMissingScheduleKeys(): void
    {
        $settings = (new ScheduleService())->normalizeSettings(['schedule' => ['enabled' => true]]);

        self::assertSame([
            'enabled' => true,
            'frequency' => 'daily',
            'hour' => 2,
            'minute' => 0,
            'weekdays' => [],
            'lastScheduledAt' => null,
        ], $settings);
    }

    /**
     * Scheduling is a Pro-gated feature. getNextRunDate()/isDue() short-circuit
     * to null/false unless a Pro Plugin instance is registered, which requires a
     * bootstrapped Craft application. In a plain unit context that's unavailable,
     * so we skip here. NOTE: the scheduling date math currently has no executed
     * coverage — a Craft-backed integration suite is PLANNED (agents.md T2) to
     * exercise it end-to-end; it does not exist yet.
     */
    private function skipUnlessSchedulingAvailable(): void
    {
        if (!CapabilityHelper::hasFeature(CapabilityHelper::FEATURE_SCHEDULES)) {
            self::markTestSkipped('Scheduling is Pro-gated; requires a bootstrapped Craft Pro edition. No unit-level coverage; integration coverage is planned (agents.md T2), not yet implemented.');
        }
    }

    public function testDailyScheduleProducesNextRun(): void
    {
        $this->skipUnlessSchedulingAvailable();

        $service = new ScheduleService();
        $template = new ExportTemplate([
            'name' => 'Daily',
            'handle' => 'daily',
            'elementType' => 'entries',
            'format' => 'csv',
            'settings' => [
                'schedule' => [
                    'enabled' => true,
                    'frequency' => 'daily',
                    'hour' => 9,
                    'minute' => 30,
                ],
            ],
        ]);

        $next = $service->getNextRunDate($template, new DateTimeImmutable('2026-03-24 08:00:00'));

        self::assertSame('2026-03-24 09:30', $next?->format('Y-m-d H:i'));
    }

    public function testWeeklyScheduleUsesSelectedWeekdays(): void
    {
        $this->skipUnlessSchedulingAvailable();

        $service = new ScheduleService();
        $template = new ExportTemplate([
            'name' => 'Weekly',
            'handle' => 'weekly',
            'elementType' => 'entries',
            'format' => 'csv',
            'settings' => [
                'schedule' => [
                    'enabled' => true,
                    'frequency' => 'weekly',
                    'hour' => 10,
                    'minute' => 0,
                    'weekdays' => ['wed'],
                ],
            ],
        ]);

        $next = $service->getNextRunDate($template, new DateTimeImmutable('2026-03-24 12:00:00'));

        self::assertSame('2026-03-25 10:00', $next?->format('Y-m-d H:i'));
    }
}
