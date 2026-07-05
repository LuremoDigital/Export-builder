<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use DOMDocument;
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

        $writer = new XmlExportWriter($path, 'entries');
        $writer->open();
        $writer->close();

        $document = $this->parse($path);

        self::assertSame('entries', $document->documentElement->nodeName);
        self::assertSame(0, $document->documentElement->getElementsByTagName('item')->length);
    }

    public function testSingleRowExportParses(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'entries');
        $writer->open();
        $writer->writeRow(['order_number' => '1001', 'customer' => 'Ada Lovelace']);
        $writer->close();

        $document = $this->parse($path);
        $rows = $document->documentElement->getElementsByTagName('item');

        self::assertSame(1, $rows->length);
        self::assertSame('1001', $rows->item(0)->getElementsByTagName('order_number')->item(0)->textContent);
        self::assertSame('Ada Lovelace', $rows->item(0)->getElementsByTagName('customer')->item(0)->textContent);
    }

    public function testOutputMatchesCraftNativeXmlShape(): void
    {
        $path = $this->tempFile();
        $writer = new XmlExportWriter($path, 'entries');
        $writer->open();
        $writer->writeRow(['title' => 'A & B', 'enabled' => 'true']);
        $writer->close();

        self::assertSame(
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<entries><item><title>A &amp; B</title><enabled>true</enabled></item></entries>\n",
            file_get_contents($path)
        );
    }

    public function testMultipleRowsKeepOrder(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'orders');
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

    public function testCustomRootElementName(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'catalog-feed');
        $writer->open();
        $writer->writeRow(['sku' => 'A-1']);
        $writer->close();

        $document = $this->parse($path);

        self::assertSame('catalog-feed', $document->documentElement->nodeName);
        self::assertSame(1, $document->getElementsByTagName('item')->length);
    }

    public function testSpecialCharactersAreEscapedAndRoundTrip(): void
    {
        $path = $this->tempFile();
        $value = 'a & b < c > "d" \'e\' — Größe ✓';

        $writer = new XmlExportWriter($path, 'entries');
        $writer->open();
        $writer->writeRow(['note' => $value]);
        $writer->close();

        $document = $this->parse($path);

        self::assertSame($value, $document->getElementsByTagName('note')->item(0)->textContent);
    }

    public function testIllegalXml10ControlsAreStrippedAtTheWriterBoundary(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'entries');
        $writer->open();
        $writer->writeRow(['note' => "before\x00\x08\x0B\x1Fafter\tok\n"]);
        $writer->close();

        $document = $this->parse($path);

        self::assertSame("beforeafter\tok\n", $document->getElementsByTagName('note')->item(0)->textContent);
    }

    public function testInvalidFieldNamesUseNativeItemFallback(): void
    {
        $path = $this->tempFile();
        $writer = new XmlExportWriter($path, 'entries');
        $writer->open();
        $writer->writeRow(['Order Number' => '1001']);
        $writer->close();

        $document = $this->parse($path);

        self::assertSame('1001', $document->documentElement->firstElementChild->firstElementChild->textContent);
        self::assertSame('item', $document->documentElement->firstElementChild->firstElementChild->nodeName);
    }

    public function testAbortRemovesPartialFile(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'entries');
        $writer->open();
        $writer->writeRow(['id' => '1']);

        self::assertFileExists($path);

        $writer->abort();

        self::assertFileDoesNotExist($path);
    }

    public function testAbortAfterCloseKeepsCompletedFile(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'entries');
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
        $writer = new XmlExportWriter($this->tempFile(), 'entries');

        $this->expectException(\yii\base\Exception::class);
        $this->expectExceptionMessage('XML writer is not open.');
        $writer->writeRow(['id' => '1']);
    }

    public function testCloseBeforeOpenThrows(): void
    {
        // Same guard on close(): closing an unopened writer is a caller bug,
        // not a no-op, so it must not report success.
        $writer = new XmlExportWriter($this->tempFile(), 'entries');

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

        $writer = new XmlExportWriter($path, 'entries');
        $writer->abort();

        self::assertFileDoesNotExist($path);
    }

    public function testOpenFailsLoudlyWhenDirectoryDoesNotExist(): void
    {
        // The openUri failure branch decides whether a bad export path is a
        // loud failed run or a silent bad state — it must throw.
        $path = sys_get_temp_dir() . '/deb-missing-' . uniqid('', true) . '/out.xml';
        $writer = new XmlExportWriter($path, 'entries');

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

        $writer = new XmlExportWriter($path, 'entries');
        $writer->open();
        $writer->writeRow(['id' => '1']);
        $writer->close();

        self::assertFileExists($path);
        $this->parse($path);

        unlink($path);
        rmdir($dir);
    }

    public function testPerBatchFlushKeepsDocumentValid(): void
    {
        $path = $this->tempFile();

        $writer = new XmlExportWriter($path, 'entries');
        $writer->open();
        $writer->writeRow(['id' => '1']);
        $writer->flush();
        $writer->writeRow(['id' => '2']);
        $writer->flush();
        $writer->close();

        $document = $this->parse($path);

        self::assertSame(2, $document->getElementsByTagName('item')->length);
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
