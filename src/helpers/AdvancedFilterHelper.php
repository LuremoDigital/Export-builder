<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\helpers;

final class AdvancedFilterHelper
{
    public const OP_EQ = 'eq';
    public const OP_CONTAINS = 'contains';
    public const OP_NOT_EMPTY = 'notEmpty';

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public static function operatorsForType(string $type): array
    {
        return array_map(
            static fn(string $operator): array => [
                'value' => $operator,
                'label' => self::operatorLabel($operator),
            ],
            self::operatorValuesForType($type)
        );
    }

    /**
     * @return string[]
     */
    public static function operatorValuesForType(string $type): array
    {
        return match ($type) {
            'text' => [self::OP_EQ, self::OP_CONTAINS, self::OP_NOT_EMPTY],
            'number', 'date', 'boolean', 'option', 'field' => [self::OP_EQ, self::OP_NOT_EMPTY],
            default => [],
        };
    }

    public static function operatorLabel(string $operator): string
    {
        return match ($operator) {
            self::OP_EQ => 'Equals',
            self::OP_CONTAINS => 'Contains',
            self::OP_NOT_EMPTY => 'Is not empty',
            default => $operator,
        };
    }

    public static function queryParamForCondition(string $operator, string $value): ?string
    {
        $value = trim($value);

        return match ($operator) {
            self::OP_EQ => $value !== '' ? $value : null,
            self::OP_CONTAINS => self::isSafeContainsValue($value) ? '*' . $value . '*' : null,
            self::OP_NOT_EMPTY => ':notempty:',
            default => null,
        };
    }

    public static function isSafeContainsValue(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && !str_contains($value, '*') && !str_contains($value, ',');
    }

    public static function isSafeHandle(string $handle): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $handle) === 1
            && !in_array($handle, self::reservedQueryHandles(), true);
    }

    /**
     * @return string[]
     */
    private static function reservedQueryHandles(): array
    {
        return [
            'andWhere',
            'anyStatus',
            'asArray',
            'batch',
            'cache',
            'count',
            'exists',
            'fixedOrder',
            'ids',
            'limit',
            'offset',
            'orderBy',
            'relatedTo',
            'search',
            'site',
            'status',
            'where',
            'with',
        ];
    }
}
