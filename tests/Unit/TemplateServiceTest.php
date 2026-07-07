<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\services\TemplateService;
use PHPUnit\Framework\TestCase;

final class TemplateServiceTest extends TestCase
{
    public function testCreateTemplateFromRequestNormalizesHandlesDatesAndFields(): void
    {
        $service = new TemplateService();

        $template = $service->createTemplateFromRequest([
            'name' => ' Orders Export ',
            'handle' => 'Orders Export!!!',
            'elementType' => 'entries',
            'format' => 'json',
            'filters' => [
                'formId' => '42',
                'dateFrom' => [
                    'year' => '2026',
                    'month' => '03',
                    'day' => '20',
                ],
                'dateTo' => '2026-03-24 17:30:00',
            ],
            'settings' => [
                'queueThreshold' => '250',
                'retentionDays' => '30',
            ],
            'fields' => [
                [
                    'fieldPath' => 'title',
                    'columnLabel' => '',
                    'sortOrder' => 2,
                ],
                [
                    'fieldPath' => 'author.email',
                    'columnLabel' => 'Author Email',
                    'sortOrder' => 1,
                ],
            ],
        ]);

        self::assertSame('Orders Export', $template->name);
        self::assertSame('orders-export', $template->handle);
        self::assertSame('json', $template->format);
        self::assertSame(42, $template->filters['formId']);
        self::assertSame('2026-03-20', $template->filters['dateFrom']);
        self::assertSame('2026-03-24', $template->filters['dateTo']);
        self::assertSame(250, $template->settings['queueThreshold']);
        self::assertSame(30, $template->settings['retentionDays']);
        self::assertSame('Author Email', $template->fields[0]->columnLabel);
        self::assertSame('title', $template->fields[1]->fieldPath);
        self::assertSame('title', $template->fields[1]->columnLabel);
    }

    public function testCreateTemplateFromRequestDefaultsInvalidOptionalValues(): void
    {
        $service = new TemplateService();

        $template = $service->createTemplateFromRequest([
            'name' => 'Basic Export',
            'handle' => '',
            'filters' => [
                'formId' => 'not-a-number',
                'dateFrom' => 'not-a-date',
            ],
            'settings' => [
                'retentionDays' => '14',
            ],
            'fields' => [
                [
                    'fieldPath' => '',
                    'columnLabel' => 'Ignore me',
                ],
                [
                    'fieldPath' => 'slug',
                ],
            ],
        ]);

        self::assertSame('basic-export', $template->handle);
        self::assertNull($template->filters['formId']);
        self::assertNull($template->filters['dateFrom']);
        self::assertNull($template->settings['retentionDays']);
        self::assertCount(1, $template->fields);
        self::assertSame('slug', $template->fields[0]->columnLabel);
    }

    public function testCreateTemplateFromRequestNormalizesSupportedFieldSettings(): void
    {
        $template = (new TemplateService())->createTemplateFromRequest([
            'name' => 'Accounting',
            'fields' => [
                [
                    'fieldPath' => 'lineItems.sku',
                    'settings' => [
                        'separator' => ' | ',
                        'decimalPlaces' => '2',
                        'warnWhenBlank' => '1',
                        'unknown' => 'discard me',
                    ],
                ],
                [
                    'fieldPath' => 'lineItems.title',
                    'settings' => [
                        'separator' => ['invalid'],
                        'warnWhenBlank' => '0',
                    ],
                ],
            ],
        ]);

        self::assertSame([
            'separator' => ' | ',
            'warnWhenBlank' => true,
            'decimalPlaces' => 2,
        ], $template->fields[0]->settings);
        self::assertSame([], $template->fields[1]->settings);
    }

    public function testCreateTemplateFromRequestNormalizesAdvancedFiltersAgainstDiscoveryPayload(): void
    {
        $service = new TemplateService();

        $template = $service->createTemplateFromRequest([
            'name' => 'Filtered Entries',
            'filters' => [
                'statuses' => ['live', 'deleted'],
                'keyword' => ' annual report ',
                'completedOnly' => '1',
                'fieldConditions' => [
                    ['field' => 'title', 'operator' => 'contains', 'value' => 'Board'],
                    ['field' => 'body', 'operator' => 'contains', 'value' => 'bad,value'],
                    ['field' => 'andWhere', 'operator' => 'eq', 'value' => 'x'],
                ],
                'relations' => [
                    ['field' => 'topics', 'targetIds' => '12, 12, no, 15'],
                    ['field' => 'missing', 'targetIds' => '20'],
                ],
            ],
        ], null, [
            'supportsStatusFilter' => true,
            'supportsKeywordFilter' => true,
            'supportsCompletedFilter' => true,
            'statuses' => [
                ['value' => 'live'],
            ],
            'filterableFields' => [
                ['handle' => 'title', 'operators' => [['value' => 'contains']]],
                ['handle' => 'body', 'operators' => [['value' => 'contains']]],
                ['handle' => 'andWhere', 'operators' => [['value' => 'eq']]],
            ],
            'relationFields' => [
                ['handle' => 'topics'],
            ],
        ]);

        self::assertSame(['live'], $template->filters['statuses']);
        self::assertSame('annual report', $template->filters['keyword']);
        self::assertTrue($template->filters['completedOnly']);
        self::assertSame([
            [
                'field' => 'title',
                'operator' => 'contains',
                'value' => 'Board',
            ],
        ], $template->filters['fieldConditions']);
        self::assertSame([
            [
                'field' => 'topics',
                'targetIds' => [12, 15],
            ],
        ], $template->filters['relations']);

        $malformed = $service->createTemplateFromRequest([
            'filters' => ['completedOnly' => ['1']],
        ], null, ['supportsCompletedFilter' => true]);
        self::assertFalse($malformed->filters['completedOnly']);
    }

}
