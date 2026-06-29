<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\helpers;

final class FilterApplier
{
    /**
     * @param array{
     *     statuses:string[],
     *     keyword:string,
     *     fieldConditions:array<int, array{field:string,param:string}>,
     *     relations:array<int, array{field:string,targetIds:int[]}>
     * } $plan
     */
    public static function applyTo(mixed $query, array $plan, string $elementType): void
    {
        if ($plan['statuses'] !== []) {
            self::applyStatuses($query, $plan['statuses'], $elementType);
        }

        if ($plan['keyword'] !== '' && method_exists($query, 'search')) {
            $query->search($plan['keyword']);
        }

        foreach ($plan['fieldConditions'] as $condition) {
            $handle = (string)$condition['field'];
            if (!AdvancedFilterHelper::isSafeHandle($handle)) {
                continue;
            }

            $query->{$handle}($condition['param']);
        }

        foreach ($plan['relations'] as $index => $relation) {
            $criteria = [
                'field' => $relation['field'],
                'targetElement' => $relation['targetIds'],
            ];

            if ($index === 0 && method_exists($query, 'relatedTo')) {
                $query->relatedTo($criteria);
                continue;
            }

            if (method_exists($query, 'andRelatedTo')) {
                $query->andRelatedTo($criteria);
            }
        }
    }

    /**
     * @param string[] $statuses
     */
    private static function applyStatuses(mixed $query, array $statuses, string $elementType): void
    {
        if ($elementType === 'orders' && method_exists($query, 'orderStatus')) {
            $query->orderStatus($statuses);

            return;
        }

        if (method_exists($query, 'status')) {
            $query->status($statuses);
        }
    }
}
