<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\helpers\CapabilityHelper;
use Luremo\DataExportBuilder\services\PresetService;
use PHPUnit\Framework\TestCase;

final class PresetServiceTest extends TestCase
{
    public function testCommerceAccountingPresetHasVerifiedOrderedFieldsAndSettings(): void
    {
        $presets = (new PresetService())->getPresetsForElementType('orders');

        self::assertSame(['ops', 'commerce-accounting'], array_column($presets, 'handle'));

        $accounting = $presets[1];
        self::assertSame([
            'Order Number',
            'Order Date',
            'Customer Email',
            'Order Status',
            'Billing Name',
            'Billing Country',
            'Billing City',
            'Payment Status',
            'Subtotal',
            'Discount Total',
            'Shipping Total',
            'Tax Total',
            'Grand Total',
            'Currency',
            'Total Quantity',
            'Line Item SKUs',
            'Line Item Titles',
            'Line Item Quantities',
            'Line Item Totals',
        ], array_column($accounting['fields'], 'label'));

        self::assertSame('dateOrdered', $accounting['fields'][1]['path']);
        self::assertSame('paidStatus', $accounting['fields'][7]['path']);
        self::assertSame(' | ', $accounting['fields'][15]['settings']['separator']);
        self::assertCount(4, array_filter(
            $accounting['fields'],
            static fn(array $field): bool => ($field['settings']['warnWhenBlank'] ?? false) === true
        ));
    }

    public function testCommerceProductPresetContainsCatalogFields(): void
    {
        $service = new PresetService();

        $presets = $service->getPresetsForElementType(CapabilityHelper::ELEMENT_TYPE_PRODUCTS);

        self::assertNotEmpty($presets);
        self::assertSame('catalog', $presets[0]['handle']);
        self::assertSame('defaultVariant.sku', $presets[0]['fields'][5]['path']);
    }
}
