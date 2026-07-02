<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\helpers;

use InvalidArgumentException;
use Luremo\DataExportBuilder\Plugin;

/**
 * Single source of truth for export format metadata.
 *
 * Every consumer of format knowledge — edition gating, model validation,
 * MIME/extension lookup, CP format options, and export dispatch — must read
 * from this registry instead of keeping its own format list. Unknown formats
 * fail closed: lookups throw and availability checks return false. There is
 * no CSV fallback.
 */
final class ExportFormatHelper
{
    public const FORMAT_CSV = 'csv';
    public const FORMAT_JSON = 'json';
    public const FORMAT_XLSX = 'xlsx';
    public const FORMAT_XML = 'xml';

    /**
     * @var array<string, array{label:string,mimeType:string,extension:string,proOnly:bool}>
     */
    private const FORMATS = [
        self::FORMAT_CSV => [
            'label' => 'CSV',
            'mimeType' => 'text/csv',
            'extension' => 'csv',
            'proOnly' => false,
        ],
        self::FORMAT_JSON => [
            'label' => 'JSON',
            'mimeType' => 'application/json',
            'extension' => 'json',
            'proOnly' => false,
        ],
        self::FORMAT_XLSX => [
            'label' => 'XLSX',
            'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'extension' => 'xlsx',
            'proOnly' => true,
        ],
        self::FORMAT_XML => [
            'label' => 'XML',
            'mimeType' => 'application/xml',
            'extension' => 'xml',
            'proOnly' => true,
        ],
    ];

    /**
     * @return string[]
     */
    public static function allowedFormatHandles(): array
    {
        return array_keys(self::FORMATS);
    }

    public static function isSupported(string $format): bool
    {
        return isset(self::FORMATS[$format]);
    }

    public static function isProOnly(string $format): bool
    {
        return self::get($format)['proOnly'];
    }

    public static function isAvailableForEdition(string $format, string $edition): bool
    {
        if (!self::isSupported($format)) {
            return false;
        }

        return !self::FORMATS[$format]['proOnly'] || $edition === Plugin::EDITION_PRO;
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    public static function optionsForEdition(string $edition): array
    {
        $options = [];

        foreach (self::FORMATS as $handle => $meta) {
            if (self::isAvailableForEdition($handle, $edition)) {
                $options[] = ['label' => $meta['label'], 'value' => $handle];
            }
        }

        return $options;
    }

    public static function label(string $format): string
    {
        return self::get($format)['label'];
    }

    /**
     * CP copy for the Output Format field, built from the registry so it
     * cannot drift out of sync with the actual format list the way a
     * hardcoded instructions string would.
     */
    public static function formatInstructionsForEdition(string $edition): string
    {
        $available = array_column(self::optionsForEdition($edition), 'label');
        $locked = array_values(array_diff(
            array_column(self::optionsForEdition(Plugin::EDITION_PRO), 'label'),
            $available
        ));

        if ($locked === []) {
            return sprintf('Choose %s.', self::joinLabels($available, 'or'));
        }

        return sprintf(
            '%s %s included in Standard. Upgrade to Pro for %s.',
            self::joinLabels($available, 'and'),
            count($available) === 1 ? 'is' : 'are',
            self::joinLabels($locked, 'and')
        );
    }

    /**
     * @param string[] $labels
     */
    private static function joinLabels(array $labels, string $conjunction): string
    {
        if (count($labels) < 2) {
            return $labels[0] ?? '';
        }

        $last = array_pop($labels);

        // Two items: "A and B" (no comma). Three or more: Oxford comma,
        // "A, B, and C".
        return count($labels) === 1
            ? sprintf('%s %s %s', $labels[0], $conjunction, $last)
            : sprintf('%s, %s %s', implode(', ', $labels), $conjunction, $last);
    }

    public static function mimeType(string $format): string
    {
        return self::get($format)['mimeType'];
    }

    public static function extension(string $format): string
    {
        return self::get($format)['extension'];
    }

    /**
     * @return array{label:string,mimeType:string,extension:string,proOnly:bool}
     */
    private static function get(string $format): array
    {
        return self::FORMATS[$format]
            ?? throw new InvalidArgumentException(sprintf('Unsupported export format "%s".', $format));
    }
}
