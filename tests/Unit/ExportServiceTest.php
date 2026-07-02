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

    public function testUnknownFormatMimeTypeFailsClosed(): void
    {
        // No CSV fallback: an unknown format must throw instead of quietly
        // serving text/csv for a file that is not CSV.
        $this->expectException(\InvalidArgumentException::class);
        \Luremo\DataExportBuilder\helpers\ExportFileHelper::fileMimeType('yaml');
    }
}
