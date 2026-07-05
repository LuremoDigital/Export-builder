<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\helpers;

use DOMDocument;
use DOMException;

/** XML compatibility and text-safety rules for Craft's native XML format. */
final class XmlExportHelper
{
    /** Matches yii\web\XmlResponseFormatter's field-name fallback. */
    public static function nativeElementName(string $name): string
    {
        try {
            return $name !== '' && (new DOMDocument())->createElement($name) !== false ? $name : 'item';
        } catch (DOMException) {
            return 'item';
        }
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
            if (!is_string($reEncoded)) {
                return '';
            }

            $cleaned = preg_replace(
                '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
                '',
                $reEncoded
            ) ?? '';
        }

        return $cleaned;
    }
}
