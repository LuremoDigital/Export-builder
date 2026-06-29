<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\helpers;

final class FilterSpecMapper
{
    /**
     * @param array<string, mixed> $filters
     * @param array<int, array<string, mixed>> $filterableFields
     * @param array<int, array<string, mixed>> $relationFields
     * @param array<int, array<string, mixed>> $statuses
     * @return array{
     *     statuses:string[],
     *     keyword:string,
     *     fieldConditions:array<int, array{field:string,operator:string,value:string,param:string}>,
     *     relations:array<int, array{field:string,targetIds:int[]}>
     * }
     */
    public static function toPlan(
        array $filters,
        array $filterableFields,
        array $relationFields,
        array $statuses,
        bool $supportsStatusFilter = true,
        bool $supportsKeywordFilter = true
    ): array {
        return [
            'statuses' => $supportsStatusFilter ? self::normalizeStatuses($filters['statuses'] ?? [], $statuses) : [],
            'keyword' => $supportsKeywordFilter ? trim((string)($filters['keyword'] ?? '')) : '',
            'fieldConditions' => self::normalizeFieldConditions($filters['fieldConditions'] ?? [], $filterableFields),
            'relations' => self::normalizeRelations($filters['relations'] ?? [], $relationFields),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $statuses
     * @return string[]
     */
    public static function normalizeStatuses(mixed $value, array $statuses): array
    {
        $allowed = [];
        foreach ($statuses as $status) {
            $statusValue = trim((string)($status['value'] ?? ''));
            if ($statusValue !== '') {
                $allowed[$statusValue] = true;
            }
        }

        if ($allowed === []) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn(mixed $item): string => trim((string)$item),
            is_array($value) ? $value : []
        ), static fn(string $item): bool => isset($allowed[$item]))));
    }

    /**
     * @param array<int, array<string, mixed>> $filterableFields
     * @return array<int, array{field:string,operator:string,value:string,param:string}>
     */
    public static function normalizeFieldConditions(mixed $value, array $filterableFields): array
    {
        $allowed = [];
        foreach ($filterableFields as $field) {
            $handle = trim((string)($field['handle'] ?? ''));
            $operators = array_values(array_filter(array_map(
                static fn(mixed $operator): string => trim((string)(is_array($operator) ? ($operator['value'] ?? '') : $operator)),
                is_array($field['operators'] ?? null) ? $field['operators'] : []
            )));

            if ($handle !== '' && AdvancedFilterHelper::isSafeHandle($handle) && $operators !== []) {
                $allowed[$handle] = $operators;
            }
        }

        if (!is_array($value) || $allowed === []) {
            return [];
        }

        $conditions = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }

            $field = trim((string)($row['field'] ?? ''));
            $operator = trim((string)($row['operator'] ?? ''));
            $rawValue = trim((string)($row['value'] ?? ''));

            if (!isset($allowed[$field]) || !in_array($operator, $allowed[$field], true)) {
                continue;
            }

            $param = AdvancedFilterHelper::queryParamForCondition($operator, $rawValue);
            if ($param === null) {
                continue;
            }

            $conditions[] = [
                'field' => $field,
                'operator' => $operator,
                'value' => $operator === AdvancedFilterHelper::OP_NOT_EMPTY ? '' : $rawValue,
                'param' => $param,
            ];
        }

        return $conditions;
    }

    /**
     * @param array<int, array<string, mixed>> $relationFields
     * @return array<int, array{field:string,targetIds:int[]}>
     */
    public static function normalizeRelations(mixed $value, array $relationFields): array
    {
        $allowed = [];
        foreach ($relationFields as $field) {
            $handle = trim((string)($field['handle'] ?? ''));
            if ($handle !== '' && AdvancedFilterHelper::isSafeHandle($handle)) {
                $allowed[$handle] = true;
            }
        }

        if (!is_array($value) || $allowed === []) {
            return [];
        }

        $relations = [];
        foreach (array_slice($value, 0, 5) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $field = trim((string)($row['field'] ?? ''));
            if (!isset($allowed[$field])) {
                continue;
            }

            $targetIds = self::normalizeTargetIds($row['targetIds'] ?? ($row['targetId'] ?? []));
            if ($targetIds === []) {
                continue;
            }

            $relations[] = [
                'field' => $field,
                'targetIds' => $targetIds,
            ];
        }

        return $relations;
    }

    /**
     * @return int[]
     */
    private static function normalizeTargetIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn(mixed $item): int => is_numeric($item) ? (int)$item : 0,
            $value
        ), static fn(int $id): bool => $id > 0)));
    }
}
