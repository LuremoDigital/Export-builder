<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use InvalidArgumentException;
use Luremo\DataExportBuilder\helpers\XmlExportHelper;
use XMLWriter;
use yii\base\Exception;

/**
 * Streams a row-based XML export document to disk.
 *
 * Uses XMLWriter::openUri() so memory stays flat regardless of row count —
 * the document is never built in memory as a DOM tree or string. On failure,
 * abort() removes the partial file so a malformed document is never left
 * behind in export storage.
 */
final class XmlExportWriter
{
    private ?XMLWriter $writer = null;
    private bool $closed = false;

    public function __construct(
        private readonly string $filePath,
        private readonly string $rootElement,
        private readonly string $rowElement,
    ) {
        foreach (['Root' => $rootElement, 'Row' => $rowElement] as $kind => $name) {
            $error = XmlExportHelper::validateElementName($name);
            if ($error !== null) {
                throw new InvalidArgumentException(sprintf('%s element name "%s" is invalid: %s', $kind, $name, $error));
            }
        }
    }

    public function open(): void
    {
        $writer = new XMLWriter();

        if (!$writer->openUri($this->filePath)) {
            throw new Exception(sprintf('Could not open export file "%s" for writing.', $this->filePath));
        }

        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement($this->rootElement);

        $this->writer = $writer;
    }

    /**
     * Writes one row node with one child element per cell.
     *
     * @param array<string, string> $cells element name => text value
     */
    public function writeRow(array $cells): void
    {
        $writer = $this->writer ?? throw new Exception('XML writer is not open.');

        $writer->startElement($this->rowElement);

        foreach ($cells as $name => $value) {
            $writer->startElement($name);
            $writer->text(XmlExportHelper::cleanTextValue($value));
            $writer->endElement();
        }

        $writer->endElement();
    }

    public function close(): void
    {
        $writer = $this->writer ?? throw new Exception('XML writer is not open.');

        $writer->endElement();
        $writer->endDocument();
        $writer->flush();

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

        if (is_file($this->filePath)) {
            @unlink($this->filePath);
        }
    }
}
