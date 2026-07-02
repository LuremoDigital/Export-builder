<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use DOMDocument;
use InvalidArgumentException;
use Luremo\DataExportBuilder\helpers\XmlExportHelper;
use Luremo\DataExportBuilder\services\XmlExportWriter;
use PHPUnit\Framework\TestCase;

final class XmlExportWriterTest extends TestCase
{
    /** @var string[] */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testEmptyExportProducesWellFormedRootOnlyDocument(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'export', 'row');
        $writer->open();
        $writer->close();

        $document = $this->parse($path);

        self::assertSame('export', $document->documentElement->nodeName);
        self::assertSame(0, $document->documentElement->getElementsByTagName('row')->length);
    }

    public function testSingleRowExportParses(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'export', 'row');
        $writer->open();
        $writer->writeRow(['order_number' => '1001', 'customer' => 'Ada Lovelace']);
        $writer->close();

        $document = $this->parse($path);
        $rows = $document->documentElement->getElementsByTagName('row');

        self::assertSame(1, $rows->length);
        self::assertSame('1001', $rows->item(0)->getElementsByTagName('order_number')->item(0)->textContent);
        self::assertSame('Ada Lovelace', $rows->item(0)->getElementsByTagName('customer')->item(0)->textContent);
    }

    public function testMultipleRowsKeepOrder(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'orders', 'order');
        $writer->open();
        $writer->writeRow(['id' => '1']);
        $writer->writeRow(['id' => '2']);
        $writer->writeRow(['id' => '3']);
        $writer->close();

        $document = $this->parse($path);
        $ids = [];
        foreach ($document->documentElement->getElementsByTagName('id') as $node) {
            $ids[] = $node->textContent;
        }

        self::assertSame(['1', '2', '3'], $ids);
    }

    public function testCustomRootAndRowElementNames(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'catalog-feed', 'product.item');
        $writer->open();
        $writer->writeRow(['sku' => 'A-1']);
        $writer->close();

        $document = $this->parse($path);

        self::assertSame('catalog-feed', $document->documentElement->nodeName);
        self::assertSame(1, $document->getElementsByTagName('product.item')->length);
    }

    public function testSpecialCharactersAreEscapedAndRoundTrip(): void
    {
        $path = $this->tempFile();
        $value = 'a & b < c > "d" \'e\' — Größe ✓';

        $writer = new XmlExportWriter($path, 'export', 'row');
        $writer->open();
        $writer->writeRow(['note' => $value]);
        $writer->close();

        $document = $this->parse($path);

        self::assertSame($value, $document->getElementsByTagName('note')->item(0)->textContent);
    }

    public function testIllegalXml10ControlsAreStrippedAtTheWriterBoundary(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'export', 'row');
        $writer->open();
        $writer->writeRow(['note' => "before\x00\x08\x0B\x1Fafter\tok\n"]);
        $writer->close();

        $document = $this->parse($path);

        self::assertSame("beforeafter\tok\n", $document->getElementsByTagName('note')->item(0)->textContent);
    }

    public function testCollisionDisambiguatedFieldNamesParse(): void
    {
        $path = $this->tempFile();
        $names = XmlExportHelper::elementNamesForLabels(['Name', 'name', 'NAME']);

        $writer = new XmlExportWriter($path, 'export', 'row');
        $writer->open();
        $writer->writeRow(array_combine($names, ['first', 'second', 'third']));
        $writer->close();

        $document = $this->parse($path);

        self::assertSame('first', $document->getElementsByTagName('name')->item(0)->textContent);
        self::assertSame('second', $document->getElementsByTagName('name_2')->item(0)->textContent);
        self::assertSame('third', $document->getElementsByTagName('name_3')->item(0)->textContent);
    }

    public function testInvalidRootElementNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new XmlExportWriter($this->tempFile(), '123root', 'row');
    }

    public function testInvalidRowElementNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new XmlExportWriter($this->tempFile(), 'export', 'xmlRow');
    }

    public function testAbortRemovesPartialFile(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'export', 'row');
        $writer->open();
        $writer->writeRow(['id' => '1']);

        self::assertFileExists($path);

        $writer->abort();

        self::assertFileDoesNotExist($path);
    }

    public function testAbortAfterCloseKeepsCompletedFile(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'export', 'row');
        $writer->open();
        $writer->close();
        $writer->abort();

        self::assertFileExists($path);
    }

    public function testWriteRowBeforeOpenThrows(): void
    {
        // Lifecycle guard: writing without open() must fail loudly instead of
        // silently dropping rows — a caller bug should never yield an export
        // that looks successful but is missing data.
        $writer = new XmlExportWriter($this->tempFile(), 'export', 'row');

        $this->expectException(\yii\base\Exception::class);
        $this->expectExceptionMessage('XML writer is not open.');
        $writer->writeRow(['id' => '1']);
    }

    public function testCloseBeforeOpenThrows(): void
    {
        // Same guard on close(): closing an unopened writer is a caller bug,
        // not a no-op, so it must not report success.
        $writer = new XmlExportWriter($this->tempFile(), 'export', 'row');

        $this->expectException(\yii\base\Exception::class);
        $this->expectExceptionMessage('XML writer is not open.');
        $writer->close();
    }

    public function testAbortBeforeOpenIsSafeAndRemovesPreexistingFile(): void
    {
        // abort() is called from a catch-all in streamXmlExport, so it must
        // be safe to invoke at any lifecycle stage — including before open()
        // ever succeeded (e.g. openUri failed after tempnam created the file).
        $path = $this->tempFile();
        file_put_contents($path, 'stale partial content');

        $writer = new XmlExportWriter($path, 'export', 'row');
        $writer->abort();

        self::assertFileDoesNotExist($path);
    }

    public function testOpenFailsLoudlyWhenDirectoryDoesNotExist(): void
    {
        // The openUri failure branch decides whether a bad export path is a
        // loud failed run or a silent bad state — it must throw.
        $path = sys_get_temp_dir() . '/deb-missing-' . uniqid('', true) . '/out.xml';
        $writer = new XmlExportWriter($path, 'export', 'row');

        $this->expectException(\yii\base\Exception::class);
        $this->expectExceptionMessage('Could not open export file');
        @$writer->open();
    }

    public function testOpenHandlesPathsWithUriSpecialCharacters(): void
    {
        // openUri() resolves plain paths via PHP streams, so directories
        // containing spaces, "%", or "#" must be written to literally, not
        // URI-decoded. Pins the raw-path contract (encoding would break it).
        $dir = sys_get_temp_dir() . '/deb 50% #test-' . uniqid('', true);
        mkdir($dir);
        $path = $dir . '/out.xml';
        $this->tempFiles[] = $path;

        $writer = new XmlExportWriter($path, 'export', 'row');
        $writer->open();
        $writer->writeRow(['id' => '1']);
        $writer->close();

        self::assertFileExists($path);
        $this->parse($path);

        unlink($path);
        rmdir($dir);
    }

    public function testWriteRowRejectsInvalidCellElementNames(): void
    {
        // Defense in depth: cell names normally come pre-sanitized from
        // XmlExportHelper, but the writer re-validates so a future caller
        // can't silently emit a malformed document.
        $path = $this->tempFile();
        $writer = new XmlExportWriter($path, 'export', 'row');
        $writer->open();

        try {
            $this->expectException(InvalidArgumentException::class);
            $writer->writeRow(['bad name!' => 'value']);
        } finally {
            $writer->abort();
        }
    }

    public function testPerBatchFlushKeepsDocumentValid(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'export', 'row');
        $writer->open();
        $writer->writeRow(['id' => '1']);
        $writer->flush();
        $writer->writeRow(['id' => '2']);
        $writer->flush();
        $writer->close();

        $document = $this->parse($path);

        self::assertSame(2, $document->getElementsByTagName('row')->length);
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'deb-xml-test-');
        self::assertIsString($path);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function parse(string $path): DOMDocument
    {
        $document = new DOMDocument();

        self::assertTrue($document->load($path), 'Generated XML must be parseable.');

        return $document;
    }
}
