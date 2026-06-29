<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\helpers\FilterSpecMapper;
use PHPUnit\Framework\TestCase;

final class FilterSpecMapperTest extends TestCase
{
    public function testMapsSavedFiltersToSafeQueryPlan(): void
    {
        $plan = FilterSpecMapper::toPlan(
            [
                'statuses' => ['live', 'bogus'],
                'keyword' => '  accounting  ',
                'fieldConditions' => [
                    ['field' => 'title', 'operator' => 'contains', 'value' => 'spring'],
                    ['field' => 'limit', 'operator' => 'eq', 'value' => '100'],
                    ['field' => 'summary', 'operator' => 'contains', 'value' => 'a,b'],
                    ['field' => 'summary', 'operator' => 'notEmpty', 'value' => ''],
                ],
                'relations' => [
                    ['field' => 'topics', 'targetIds' => ['7', '7', 'nope', '9']],
                    ['field' => 'missing', 'targetIds' => ['12']],
                ],
            ],
            [
                ['handle' => 'title', 'operators' => [['value' => 'eq'], ['value' => 'contains'], ['value' => 'notEmpty']]],
                ['handle' => 'summary', 'operators' => [['value' => 'contains'], ['value' => 'notEmpty']]],
                ['handle' => 'limit', 'operators' => [['value' => 'eq']]],
            ],
            [
                ['handle' => 'topics'],
            ],
            [
                ['value' => 'live'],
                ['value' => 'disabled'],
            ]
        );

        self::assertSame(['live'], $plan['statuses']);
        self::assertSame('accounting', $plan['keyword']);
        self::assertSame([
            [
                'field' => 'title',
                'operator' => 'contains',
                'value' => 'spring',
                'param' => '*spring*',
            ],
            [
                'field' => 'summary',
                'operator' => 'notEmpty',
                'value' => '',
                'param' => ':notempty:',
            ],
        ], $plan['fieldConditions']);
        self::assertSame([
            [
                'field' => 'topics',
                'targetIds' => [7, 9],
            ],
        ], $plan['relations']);
    }

    public function testEmptyAllowedListsFailClosed(): void
    {
        $plan = FilterSpecMapper::toPlan(
            [
                'statuses' => ['live'],
                'keyword' => 'needle',
                'fieldConditions' => [
                    ['field' => 'title', 'operator' => 'eq', 'value' => 'Hello'],
                ],
                'relations' => [
                    ['field' => 'topics', 'targetIds' => ['7']],
                ],
            ],
            [],
            [],
            [],
            false,
            false
        );

        self::assertSame([], $plan['statuses']);
        self::assertSame('', $plan['keyword']);
        self::assertSame([], $plan['fieldConditions']);
        self::assertSame([], $plan['relations']);
    }
}
