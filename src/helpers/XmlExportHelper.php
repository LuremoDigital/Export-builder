<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\helpers;

/**
 * XML naming and text-safety rules for the XML export format.
 *
 * Root and row element names come from user settings and are rejected when
 * invalid (never silently rewritten — they can become importer contracts).
 * Field element names are generated from export column titles, so they are
 * sanitized and collision-disambiguated instead. Text values are cleaned of
 * characters that are illegal in XML 1.0 before they reach the writer.
 */
final class XmlExportHelper
{
    private const NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_\-.]*$/';

    /**
     * Validates a user-supplied root or row element name.
     *
     * Returns a user-facing error message, or null when the name is valid.
     */
    public static function validateElementName(string $name): ?string
    {
        $name = trim($name);

        if ($name === '') {
            return 'Enter an XML element name.';
        }

        if (preg_match('/^[A-Za-z_]/', $name) !== 1) {
            return 'XML element names must start with a letter or underscore.';
        }

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            return 'Use letters, numbers, underscores, hyphens, or periods. Spaces are not allowed.';
        }

        if (self::usesReservedPrefix($name)) {
            return 'XML element names cannot use the reserved xml or xmlns names.';
        }

        return null;
    }

    /**
     * Generates an XML element name from an export column title.
     *
     * "Order Number" becomes "order_number". The result always satisfies
     * validateElementName(), adjusting (rather than rejecting) generated
     * names since users don't type these directly.
     */
    public static function elementNameFromLabel(string $label): string
    {
        $name = strtolower(trim($label));
        $name = preg_replace('/[^a-z0-9_\-.]+/', '_', $name) ?? '';
        $name = trim($name, '_');

        if ($name === '' || preg_match('/^[a-z_]/', $name) !== 1) {
            $name = 'field' . ($name !== '' ? '_' . $name : '');
        }

        if (self::usesReservedPrefix($name)) {
            $name = 'field_' . $name;
        }

        return $name;
    }

    /**
     * Generates deduplicated element names for a list of column titles.
     *
     * The first occurrence keeps its generated name; later collisions get
     * `_2`, `_3`, and so on in column order.
     *
     * @param string[] $labels
     * @return string[] same order and count as $labels
     */
    public static function elementNamesForLabels(array $labels): array
    {
        $names = [];
        $used = [];

        foreach ($labels as $label) {
            $base = self::elementNameFromLabel($label);
            $candidate = $base;
            $suffix = 2;

            while (isset($used[$candidate])) {
                $candidate = $base . '_' . $suffix;
                $suffix++;
            }

            $used[$candidate] = true;
            $names[] = $candidate;
        }

        return $names;
    }

    /**
     * Removes characters that are illegal in XML 1.0 text content.
     *
     * Keeps tab, newline, and carriage return; strips the remaining C0
     * controls plus other code points outside the XML 1.0 Char production.
     * XMLWriter handles entity escaping for &, <, >, and quotes itself.
     */
    public static function cleanTextValue(string $value): string
    {
        $cleaned = preg_replace(
            '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $value
        );

        if ($cleaned === null) {
            // Invalid UTF-8 makes the /u regex fail. Re-encode defensively,
            // then strip again so the writer never receives bytes that would
            // abort the stream mid-file.
            $reEncoded = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            $cleaned = preg_replace(
                '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
                '',
                $reEncoded
            ) ?? '';
        }

        return $cleaned;
    }

    private static function usesReservedPrefix(string $name): bool
    {
        return stripos($name, 'xml') === 0;
    }
}
