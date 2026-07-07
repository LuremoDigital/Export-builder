<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Integration;

use Craft;
use Luremo\DataExportBuilder\models\ExportField;
use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\Plugin;
use PHPUnit\Framework\TestCase;

final class CommerceAccountingExportTest extends TestCase
{
    public function testGoldenAccountingCsvHasExactColumnsAndCells(): void
    {
        $csv = $this->runAccountingExport('2026-06-15', '2026-06-15');
        $golden = file_get_contents(__DIR__ . '/../fixtures/accounting-export-golden.csv');
        self::assertNotFalse($golden);

        $actualRows = $this->parseCsv($csv);
        $goldenRows = $this->parseCsv($golden);

        self::assertSame($goldenRows[0], $actualRows[0], 'Accounting column order changed.');
        self::assertSame($goldenRows, $actualRows, 'Accounting cell values changed.');
    }

    public function testNoMatchingOrdersProducesHeaderOnlyCsv(): void
    {
        $rows = $this->parseCsv($this->runAccountingExport('2030-01-01', '2030-01-01'));

        self::assertCount(1, $rows);
        self::assertSame('Order Number', $rows[0][0]);
        self::assertSame('Line Item Totals', $rows[0][22]);
    }

    public function testOrderQueryExcludesCarts(): void
    {
        $plugin = Plugin::getInstance();
        self::assertInstanceOf(Plugin::class, $plugin);
        $completedQuery = $plugin->get('exports')->buildSourceQuery(new ExportTemplate([
            'elementType' => 'orders',
            'filters' => ['completedOnly' => true],
        ]));
        $allOrdersQuery = $plugin->get('exports')->buildSourceQuery(new ExportTemplate([
            'elementType' => 'orders',
        ]));

        self::assertTrue($completedQuery->isCompleted);
        self::assertNull($allOrdersQuery->isCompleted);
    }

    public function testMarkedBlankFieldWarnsWithActualRunId(): void
    {
        $plugin = Plugin::getInstance();
        self::assertInstanceOf(Plugin::class, $plugin);
        $template = new ExportTemplate([
            'name' => 'Accounting Warning Fixture',
            'handle' => 'accounting-warning-' . bin2hex(random_bytes(5)),
            'elementType' => 'orders',
            'format' => 'csv',
            'filters' => ['dateFrom' => '2026-06-15', 'dateTo' => '2026-06-15'],
            'settings' => ['queueThreshold' => 1000],
            'fields' => [new ExportField([
                'fieldPath' => 'missingRequiredValue',
                'columnLabel' => 'Customer Email',
                'settings' => ['warnWhenBlank' => true],
            ])],
        ]);
        self::assertTrue($plugin->get('templates')->saveTemplate($template));

        $run = $plugin->get('exports')->runTemplate($template, 1);
        $messages = array_column(array_filter(
            Craft::getLogger()->messages,
            static fn(array $message): bool => ($message[2] ?? null) === 'data-export-builder'
        ), 0);

        self::assertSame('completed', $run->status);
        self::assertContains(
            sprintf('Accounting export field "Customer Email" was blank for 1 of 1 rows (run ID: %d).', $run->id),
            $messages
        );
    }

    public function testPresetFieldSettingsSurviveSaveAndReload(): void
    {
        $plugin = Plugin::getInstance();
        self::assertInstanceOf(Plugin::class, $plugin);
        $template = $plugin->get('templates')->createTemplateFromRequest([
            'name' => 'Accounting Settings Fixture',
            'handle' => 'accounting-settings-' . bin2hex(random_bytes(5)),
            'elementType' => 'orders',
            'format' => 'csv',
            'fields' => [[
                'fieldPath' => 'lineItems.sku',
                'columnLabel' => 'Line Item SKUs',
                'settings' => ['separator' => ' | ', 'warnWhenBlank' => '1'],
            ]],
        ]);

        self::assertTrue($plugin->get('templates')->saveTemplate($template));
        $reloaded = $plugin->get('templates')->getTemplateById((int)$template->id);

        self::assertNotNull($reloaded);
        self::assertSame([
            'separator' => ' | ',
            'warnWhenBlank' => true,
        ], $reloaded->fields[0]->settings);
    }

    private function runAccountingExport(string $dateFrom, string $dateTo): string
    {
        $plugin = Plugin::getInstance();
        self::assertInstanceOf(Plugin::class, $plugin);
        $preset = $plugin->get('presets')->getPresetsForElementType('orders')[1];

        $template = new ExportTemplate([
            'name' => 'Accounting Integration Fixture',
            'handle' => 'accounting-integration-' . bin2hex(random_bytes(5)),
            'elementType' => 'orders',
            'format' => 'csv',
            'filters' => compact('dateFrom', 'dateTo'),
            'settings' => ['queueThreshold' => 1000],
            'fields' => array_map(
                static fn(array $field, int $index): ExportField => new ExportField([
                    'fieldPath' => $field['path'],
                    'columnLabel' => $field['label'],
                    'sortOrder' => $index + 1,
                    'settings' => $field['settings'] ?? [],
                ]),
                $preset['fields'],
                array_keys($preset['fields'])
            ),
        ]);

        self::assertTrue($plugin->get('templates')->saveTemplate($template));
        $run = $plugin->get('exports')->runTemplate($template, 1);
        self::assertSame('completed', $run->status, $run->errorMessage ?? 'Export failed.');
        self::assertNotNull($run->filePath);

        $csv = file_get_contents($run->filePath);
        self::assertNotFalse($csv);

        return $csv;
    }

    /**
     * @return list<list<string|null>>
     */
    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);
        fwrite($stream, $csv);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }
}
