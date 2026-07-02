<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\services\ExportService;
use PHPUnit\Framework\TestCase;

final class ExportServiceTest extends TestCase
{
    public function testShouldQueueForLargeExports(): void
    {
        $service = new ExportService();
        $template = new ExportTemplate([
            'name' => 'Large Export',
            'handle' => 'large-export',
            'elementType' => 'entries',
            'format' => 'csv',
            'settings' => ['queueThreshold' => 100],
        ]);

        self::assertFalse($service->shouldQueueForCount($template, 100));
        self::assertTrue($service->shouldQueueForCount($template, 101));
    }

    public function testXlsxMimeTypeIsSupported(): void
    {
        self::assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            \Luremo\DataExportBuilder\helpers\ExportFileHelper::fileMimeType('xlsx')
        );
    }

    public function testXmlMimeTypeIsSupported(): void
    {
        self::assertSame('application/xml', \Luremo\DataExportBuilder\helpers\ExportFileHelper::fileMimeType('xml'));
    }

    public function testXmlTemplateFileNameGetsXmlExtension(): void
    {
        // buildFileName resolves the extension through the format registry;
        // this pins the registry wiring so an XML run never downloads as
        // "*.xml-format-name" or falls back to the raw format handle.
        $template = new ExportTemplate([
            'name' => 'Orders Feed',
            'handle' => 'ordersFeed',
            'elementType' => 'entries',
            'format' => 'xml',
        ]);
        $run = new \Luremo\DataExportBuilder\models\ExportRun(['id' => 7, 'format' => 'xml', 'templateId' => 1]);

        $fileName = \Luremo\DataExportBuilder\helpers\ExportFileHelper::buildFileName($template, $run);

        self::assertMatchesRegularExpression('/^ordersfeed-\d{8}-\d{6}-7\.xml$/', $fileName);
    }

    public function testUnknownFormatMimeTypeFailsClosed(): void
    {
        // No CSV fallback: an unknown format must throw instead of quietly
        // serving text/csv for a file that is not CSV.
        $this->expectException(\InvalidArgumentException::class);
        \Luremo\DataExportBuilder\helpers\ExportFileHelper::fileMimeType('yaml');
    }
}
