<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use InvalidArgumentException;
use Luremo\DataExportBuilder\helpers\ExportFormatHelper;
use Luremo\DataExportBuilder\Plugin;
use PHPUnit\Framework\TestCase;

final class ExportFormatHelperTest extends TestCase
{
    public function testAllowedFormatHandlesCoverAllFourFormats(): void
    {
        self::assertSame(['csv', 'json', 'xlsx', 'xml'], ExportFormatHelper::allowedFormatHandles());
    }

    public function testMimeTypesPerFormat(): void
    {
        self::assertSame('text/csv', ExportFormatHelper::mimeType('csv'));
        self::assertSame('application/json', ExportFormatHelper::mimeType('json'));
        self::assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ExportFormatHelper::mimeType('xlsx')
        );
        self::assertSame('application/xml', ExportFormatHelper::mimeType('xml'));
    }

    public function testExtensionsPerFormat(): void
    {
        self::assertSame('csv', ExportFormatHelper::extension('csv'));
        self::assertSame('json', ExportFormatHelper::extension('json'));
        self::assertSame('xlsx', ExportFormatHelper::extension('xlsx'));
        self::assertSame('xml', ExportFormatHelper::extension('xml'));
    }

    public function testProOnlyFlags(): void
    {
        self::assertFalse(ExportFormatHelper::isProOnly('csv'));
        self::assertFalse(ExportFormatHelper::isProOnly('json'));
        self::assertTrue(ExportFormatHelper::isProOnly('xlsx'));
        self::assertTrue(ExportFormatHelper::isProOnly('xml'));
    }

    public function testStandardEditionGetsCsvAndJsonOnly(): void
    {
        $handles = array_column(ExportFormatHelper::optionsForEdition(Plugin::EDITION_STANDARD), 'value');

        self::assertSame(['csv', 'json'], $handles);
    }

    public function testProEditionGetsAllFourFormats(): void
    {
        $handles = array_column(ExportFormatHelper::optionsForEdition(Plugin::EDITION_PRO), 'value');

        self::assertSame(['csv', 'json', 'xlsx', 'xml'], $handles);
    }

    public function testEditionAvailabilityMatrix(): void
    {
        self::assertTrue(ExportFormatHelper::isAvailableForEdition('csv', Plugin::EDITION_STANDARD));
        self::assertTrue(ExportFormatHelper::isAvailableForEdition('json', Plugin::EDITION_STANDARD));
        self::assertFalse(ExportFormatHelper::isAvailableForEdition('xlsx', Plugin::EDITION_STANDARD));
        self::assertFalse(ExportFormatHelper::isAvailableForEdition('xml', Plugin::EDITION_STANDARD));
        self::assertTrue(ExportFormatHelper::isAvailableForEdition('xlsx', Plugin::EDITION_PRO));
        self::assertTrue(ExportFormatHelper::isAvailableForEdition('xml', Plugin::EDITION_PRO));
    }

    public function testUnknownFormatsFailClosed(): void
    {
        self::assertFalse(ExportFormatHelper::isSupported('yaml'));
        self::assertFalse(ExportFormatHelper::isAvailableForEdition('yaml', Plugin::EDITION_STANDARD));
        self::assertFalse(ExportFormatHelper::isAvailableForEdition('yaml', Plugin::EDITION_PRO));

        // No CSV fallback anywhere: metadata lookups throw for unknown handles.
        $this->expectException(InvalidArgumentException::class);
        ExportFormatHelper::mimeType('yaml');
    }

    public function testUnknownFormatExtensionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ExportFormatHelper::extension('yaml');
    }

    public function testUnknownFormatLabelThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ExportFormatHelper::label('yaml');
    }

    public function testKnownFormatsAreSupportedWithStableLabels(): void
    {
        // Labels surface in CP format options and in the Pro-gating error
        // message TemplateService builds, so they are contract, not cosmetics.
        foreach (['csv' => 'CSV', 'json' => 'JSON', 'xlsx' => 'XLSX', 'xml' => 'XML'] as $handle => $label) {
            self::assertTrue(ExportFormatHelper::isSupported($handle));
            self::assertSame($label, ExportFormatHelper::label($handle));
        }
    }

    public function testFormatInstructionsAreBuiltFromTheRegistryNotHardcoded(): void
    {
        // Pins the exact CP copy while sourcing every format name from the
        // registry, so adding a 5th format can't silently leave stale text
        // behind (the bug a hardcoded instructions string would reintroduce).
        self::assertSame(
            'Choose CSV, JSON, XLSX, or XML.',
            ExportFormatHelper::formatInstructionsForEdition(Plugin::EDITION_PRO)
        );
        self::assertSame(
            'CSV and JSON are included in Standard. Upgrade to Pro for XLSX and XML.',
            ExportFormatHelper::formatInstructionsForEdition(Plugin::EDITION_STANDARD)
        );
    }
}
