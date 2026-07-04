<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use craft\base\Component;
use Luremo\DataExportBuilder\helpers\CapabilityHelper;

final class PresetService extends Component
{
    /**
     * @return array<int, array{handle:string,label:string,description:string,fields:array<int, array{path:string,label:string,settings?:array<string,mixed>}>,filters?:array<string,mixed>}>
     */
    public function getPresetsForElementType(string $elementType): array
    {
        return match ($elementType) {
            'orders' => [
                [
                    'handle' => 'ops',
                    'label' => 'Order Ops',
                    'description' => 'Operational order export with customer, totals, and address data.',
                    'fields' => $this->buildPresetFields([
                        'number' => 'Order Number',
                        'dateCreated' => 'Created At',
                        'email' => 'Email',
                        'customer.email' => 'Customer Email',
                        'orderStatus.handle' => 'Status',
                        'totalPrice' => 'Total',
                        'totalQty' => 'Quantity',
                        'billingAddress.fullName' => 'Billing Name',
                        'billingAddress.addressLine1' => 'Billing Address 1',
                        'billingAddress.locality' => 'Billing City',
                        'shippingAddress.fullName' => 'Shipping Name',
                        'shippingAddress.addressLine1' => 'Shipping Address 1',
                        'shippingAddress.locality' => 'Shipping City',
                    ]),
                ],
                [
                    'handle' => 'commerce-accounting',
                    'label' => 'Commerce Accounting Export',
                    'description' => 'Accountant-ready order export with totals and line items in one row per order.',
                    'filters' => ['completedOnly' => true],
                    'fields' => [
                        ['path' => 'number', 'label' => 'Order Number', 'settings' => ['warnWhenBlank' => true]],
                        ['path' => 'dateOrdered', 'label' => 'Order Date', 'settings' => ['warnWhenBlank' => true]],
                        ['path' => 'email', 'label' => 'Customer Email', 'settings' => ['warnWhenBlank' => true]],
                        ['path' => 'orderStatus.handle', 'label' => 'Order Status'],
                        ['path' => 'billingAddress.fullName', 'label' => 'Billing Name'],
                        ['path' => 'billingAddress.countryCode', 'label' => 'Billing Country'],
                        ['path' => 'billingAddress.locality', 'label' => 'Billing City'],
                        ['path' => 'paidStatus', 'label' => 'Payment Status'],
                        ['path' => 'itemSubtotal', 'label' => 'Subtotal', 'settings' => ['decimalPlaces' => 2]],
                        ['path' => 'totalDiscount', 'label' => 'Discount Total', 'settings' => ['decimalPlaces' => 2]],
                        ['path' => 'totalShippingCost', 'label' => 'Shipping Total', 'settings' => ['decimalPlaces' => 2]],
                        ['path' => 'totalTax', 'label' => 'Tax Total', 'settings' => ['decimalPlaces' => 2]],
                        ['path' => 'totalPrice', 'label' => 'Grand Total', 'settings' => ['decimalPlaces' => 2, 'warnWhenBlank' => true]],
                        ['path' => 'paymentCurrency', 'label' => 'Currency'],
                        ['path' => 'totalQty', 'label' => 'Total Quantity'],
                        ['path' => 'lineItems.sku', 'label' => 'Line Item SKUs', 'settings' => ['separator' => ' | ']],
                        ['path' => 'lineItems.description', 'label' => 'Line Item Titles', 'settings' => ['separator' => ' | ']],
                        ['path' => 'lineItems.qty', 'label' => 'Line Item Quantities', 'settings' => ['separator' => ' | ']],
                        ['path' => 'lineItems.total', 'label' => 'Line Item Totals', 'settings' => ['separator' => ' | ', 'decimalPlaces' => 2]],
                    ],
                ],
            ],
            CapabilityHelper::ELEMENT_TYPE_PRODUCTS => [[
                'handle' => 'catalog',
                'label' => 'Catalog Feed',
                'description' => 'Product export for catalog, PIM, or feed handoffs.',
                'fields' => $this->buildPresetFields([
                    'title' => 'Title',
                    'slug' => 'Slug',
                    'uri' => 'URI',
                    'type.name' => 'Product Type',
                    'dateUpdated' => 'Updated At',
                    'defaultVariant.sku' => 'Default SKU',
                    'defaultVariant.price' => 'Default Price',
                    'defaultVariant.stock' => 'Default Stock',
                ]),
            ]],
            CapabilityHelper::ELEMENT_TYPE_VARIANTS => [[
                'handle' => 'inventory',
                'label' => 'Inventory Feed',
                'description' => 'Variant-level inventory and pricing export.',
                'fields' => $this->buildPresetFields([
                    'sku' => 'SKU',
                    'title' => 'Variant Title',
                    'product.title' => 'Product Title',
                    'product.slug' => 'Product Slug',
                    'price' => 'Price',
                    'stock' => 'Stock',
                    'enabled' => 'Enabled',
                    'dateUpdated' => 'Updated At',
                ]),
            ]],
            default => [],
        };
    }

    /**
     * @param array<string, string> $definitions
     * @return array<int, array{path:string,label:string}>
     */
    private function buildPresetFields(array $definitions): array
    {
        $fields = [];

        foreach ($definitions as $path => $label) {
            $fields[] = [
                'path' => $path,
                'label' => $label,
            ];
        }

        return $fields;
    }
}
