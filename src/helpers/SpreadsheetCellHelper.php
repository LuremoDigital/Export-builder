<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\helpers;

/**
 * Prevents spreadsheet applications from interpreting exported user content
 * as a formula. CSV quoting alone does not provide this protection.
 */
final class SpreadsheetCellHelper
{
    public static function sanitize(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);
        $isFormulaPrefix = str_starts_with($trimmed, '=')
            || str_starts_with($trimmed, '+')
            || str_starts_with($trimmed, '@')
            || (str_starts_with($trimmed, '-') && !preg_match('/^-\d+(?:\.\d+)?(?:[eE][+-]?\d+)?$/', $trimmed));

        return $isFormulaPrefix ? "'" . $value : $value;
    }
}
