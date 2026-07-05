<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use Craft;
use Luremo\DataExportBuilder\helpers\XmlExportHelper;
use XMLWriter;
use yii\base\Exception;

/**
 * Streams a row-based XML export document to disk.
 *
 * Uses XMLWriter::openUri() so memory stays flat regardless of row count —
 * the document is never built in memory as a DOM tree or string. Flushes are
 * checked so an IO failure (disk full, vanished mount) fails the run instead
 * of marking a truncated document as completed. On failure, abort() removes
 * the partial file so malformed XML is never left behind in export storage.
 */
final class XmlExportWriter
{
    private ?XMLWriter $writer = null;
    private bool $closed = false;
    /** @var array<string, string> */
    private array $cellNames = [];

    public function __construct(
        private readonly string $filePath,
        private readonly string $rootElement,
    ) {
    }

    public function open(): void
    {
        $writer = new XMLWriter();

        // openUri() goes through PHP streams (PHP 7.1+), so plain filesystem
        // paths — including ones containing spaces, "%", or "#" — are used
        // literally. Do not percent-encode; encoding breaks path resolution.
        if (!$writer->openUri($this->filePath)) {
            throw new Exception(sprintf('Could not open export file "%s" for writing.', $this->filePath));
        }

        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement($this->rootElement);

        $this->writer = $writer;
    }

    /**
     * Writes one row node with one child element per cell.
     *
     * Invalid field paths use Craft's native `<item>` fallback.
     *
     * @param array<string, string> $cells field path => text value
     */
    public function writeRow(array $cells): void
    {
        $writer = $this->writer ?? throw new Exception('XML writer is not open.');

        $writer->startElement('item');

        foreach ($cells as $name => $value) {
            $name = (string)$name;
            $writer->startElement($this->cellNames[$name] ??= XmlExportHelper::nativeElementName($name));
            $writer->text(XmlExportHelper::cleanTextValue($value));
            $writer->endElement();
        }

        $writer->endElement();
    }

    /**
     * Flushes buffered output to disk and fails loudly if the write failed.
     * Called per batch so an IO failure surfaces mid-run instead of after
     * the whole export "succeeded".
     */
    public function flush(): void
    {
        $writer = $this->writer ?? throw new Exception('XML writer is not open.');

        $this->assertFlushSucceeded($writer->flush());
    }

    public function close(): void
    {
        $writer = $this->writer ?? throw new Exception('XML writer is not open.');

        $writer->endElement();
        $writer->endDocument();
        $this->assertFlushSucceeded($writer->flush());

        clearstatcache(true, $this->filePath);
        if (!is_file($this->filePath) || filesize($this->filePath) === 0) {
            throw new Exception(sprintf('XML export file "%s" was not written.', $this->filePath));
        }

        $this->writer = null;
        $this->closed = true;
    }

    /**
     * Aborts a failed run: releases the writer and deletes the partial file
     * so it can never surface as a completed download.
     */
    public function abort(): void
    {
        if ($this->closed) {
            return;
        }

        // XMLWriter has no explicit close; dropping the reference flushes and
        // releases the file handle so the partial file can be removed.
        $this->writer = null;

        if (is_file($this->filePath) && !@unlink($this->filePath) && Craft::$app !== null) {
            Craft::warning(sprintf('Could not remove partial XML export file "%s".', $this->filePath), 'data-export-builder');
        }
    }

    private function assertFlushSucceeded(mixed $result): void
    {
        // XMLWriter::flush() returns the written byte count for URI writers;
        // libxml reports IO errors as false/negative. A zero result is fine
        // (nothing buffered since the last flush).
        if ($result === false || (is_int($result) && $result < 0)) {
            throw new Exception(sprintf('Failed writing XML export data to "%s".', $this->filePath));
        }
    }
}
