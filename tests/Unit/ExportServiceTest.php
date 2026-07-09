<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\models\ExportField;
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

    public function testCommerceNativeEagerLoadMethodsAreUsedBeforeGenericWith(): void
    {
        $query = new class () {
            /** @var string[] */
            public array $calls = [];

            /**
             * @param string[] $paths
             */
            public function with(array $paths): void
            {
                $this->calls[] = 'with:' . implode(',', $paths);
            }

            public function withLineItems(bool $value): void
            {
                $this->calls[] = 'withLineItems:' . ($value ? 'true' : 'false');
            }

            public function withTransactions(bool $value): void
            {
                $this->calls[] = 'withTransactions:' . ($value ? 'true' : 'false');
            }
        };

        $method = new \ReflectionMethod(ExportService::class, 'applyEagerLoadPaths');
        $method->invoke(new ExportService(), $query, ['lineItems', 'transactions', 'customer']);

        self::assertSame([
            'withLineItems:true',
            'withTransactions:true',
            'with:customer',
        ], $query->calls);
    }

    public function testUnknownFormatMimeTypeFailsClosed(): void
    {
        // No CSV fallback: an unknown format must throw instead of quietly
        // serving text/csv for a file that is not CSV.
        $this->expectException(\InvalidArgumentException::class);
        \Luremo\DataExportBuilder\helpers\ExportFileHelper::fileMimeType('yaml');
    }

    public function testRunSnapshotPreservesTheTemplateDefinitionAtQueueTime(): void
    {
        $template = new ExportTemplate([
            'name' => 'Orders',
            'handle' => 'orders',
            'elementType' => 'orders',
            'format' => 'csv',
            'filters' => ['dateFrom' => '2026-07-01'],
            'settings' => ['queueThreshold' => 1000],
            'fields' => [new ExportField(['fieldPath' => 'number', 'columnLabel' => 'Order Number'])],
        ]);

        $method = new \ReflectionMethod(ExportService::class, 'buildTemplateSnapshot');
        $snapshot = $method->invoke(new ExportService(), $template);
        $template->fields[0]->columnLabel = 'Changed later';

        self::assertSame('Order Number', $snapshot['fields'][0]['columnLabel']);
        self::assertSame('2026-07-01', $snapshot['filters']['dateFrom']);
    }

    public function testRejectsInvalidCallerSuppliedDeliveryKeys(): void
    {
        $method = new \ReflectionMethod(ExportService::class, 'resolveDeliveryKey');

        foreach (['', '   ', str_repeat('x', 65)] as $deliveryKey) {
            try {
                $method->invoke(new ExportService(), $deliveryKey);
                self::fail('Expected invalid delivery key to be rejected.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testPreservesAValidCallerSuppliedDeliveryKey(): void
    {
        $method = new \ReflectionMethod(ExportService::class, 'resolveDeliveryKey');

        self::assertSame('scheduled-slot-2026-07-09T12:00:00Z', $method->invoke(new ExportService(), 'scheduled-slot-2026-07-09T12:00:00Z'));
    }

    public function testGeneratesAValidDeliveryKeyWhenNoneIsSupplied(): void
    {
        $method = new \ReflectionMethod(ExportService::class, 'resolveDeliveryKey');
        $deliveryKey = $method->invoke(new ExportService(), null);

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $deliveryKey);
    }
}
