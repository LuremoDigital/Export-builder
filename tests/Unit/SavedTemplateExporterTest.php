<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\elements\exporters\SavedTemplateExporter;
use Luremo\DataExportBuilder\models\ExportTemplate;
use PHPUnit\Framework\TestCase;

final class SavedTemplateExporterTest extends TestCase
{
    public function testSavedTemplatesGetDistinctExporterClasses(): void
    {
        $ordersClass = SavedTemplateExporter::classForTemplate(new ExportTemplate([
            'id' => 101,
            'name' => 'Orders',
        ]));
        $ordersClassAgain = SavedTemplateExporter::classForTemplate(new ExportTemplate([
            'id' => 101,
            'name' => 'Renamed Orders',
        ]));
        $entriesClass = SavedTemplateExporter::classForTemplate(new ExportTemplate([
            'id' => 102,
            'name' => 'Entries',
        ]));

        self::assertSame($ordersClass, $ordersClassAgain);
        self::assertNotSame($ordersClass, $entriesClass);
        self::assertTrue(is_subclass_of($ordersClass, SavedTemplateExporter::class));
        self::assertSame($ordersClass, get_class(new $ordersClass()));
    }
}
