<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\services\TemplateService;
use PHPUnit\Framework\TestCase;

final class TemplateServiceTest extends TestCase
{
    public function testCreateTemplateFromRequestNormalizesHandlesDatesAndFields(): void
    {
        $service = new TemplateService();

        $template = $service->createTemplateFromRequest([
            'name' => ' Orders Export ',
            'handle' => 'Orders Export!!!',
            'elementType' => 'entries',
            'format' => 'json',
            'filters' => [
                'formId' => '42',
                'dateFrom' => [
                    'year' => '2026',
                    'month' => '03',
                    'day' => '20',
                ],
                'dateTo' => '2026-03-24 17:30:00',
            ],
            'settings' => [
                'queueThreshold' => '250',
            ],
            'fields' => [
                [
                    'fieldPath' => 'title',
                    'columnLabel' => '',
                    'sortOrder' => 2,
                ],
                [
                    'fieldPath' => 'author.email',
                    'columnLabel' => 'Author Email',
                    'sortOrder' => 1,
                ],
            ],
        ]);

        self::assertSame('Orders Export', $template->name);
        self::assertSame('orders-export', $template->handle);
        self::assertSame('json', $template->format);
        self::assertSame(42, $template->filters['formId']);
        self::assertSame('2026-03-20', $template->filters['dateFrom']);
        self::assertSame('2026-03-24', $template->filters['dateTo']);
        self::assertSame(250, $template->settings['queueThreshold']);
        self::assertSame('Author Email', $template->fields[0]->columnLabel);
        self::assertSame('title', $template->fields[1]->fieldPath);
        self::assertSame('title', $template->fields[1]->columnLabel);
    }

    public function testCreateTemplateFromRequestDefaultsInvalidOptionalValues(): void
    {
        $service = new TemplateService();

        $template = $service->createTemplateFromRequest([
            'name' => 'Basic Export',
            'handle' => '',
            'filters' => [
                'formId' => 'not-a-number',
                'dateFrom' => 'not-a-date',
            ],
            'fields' => [
                [
                    'fieldPath' => '',
                    'columnLabel' => 'Ignore me',
                ],
                [
                    'fieldPath' => 'slug',
                ],
            ],
        ]);

        self::assertSame('basic-export', $template->handle);
        self::assertNull($template->filters['formId']);
        self::assertNull($template->filters['dateFrom']);
        self::assertCount(1, $template->fields);
        self::assertSame('slug', $template->fields[0]->columnLabel);
    }

    public function testCreateTemplateFromRequestNormalizesSupportedFieldSettings(): void
    {
        $template = (new TemplateService())->createTemplateFromRequest([
            'name' => 'Accounting',
            'fields' => [
                [
                    'fieldPath' => 'lineItems.sku',
                    'settings' => [
                        'separator' => ' | ',
                        'decimalPlaces' => '2',
                        'warnWhenBlank' => '1',
                        'unknown' => 'discard me',
                    ],
                ],
                [
                    'fieldPath' => 'lineItems.title',
                    'settings' => [
                        'separator' => ['invalid'],
                        'warnWhenBlank' => '0',
                    ],
                ],
            ],
        ]);

        self::assertSame([
            'separator' => ' | ',
            'warnWhenBlank' => true,
            'decimalPlaces' => 2,
        ], $template->fields[0]->settings);
        self::assertSame([], $template->fields[1]->settings);
    }

    public function testCreateTemplateFromRequestNormalizesAdvancedFiltersAgainstDiscoveryPayload(): void
    {
        $service = new TemplateService();

        $template = $service->createTemplateFromRequest([
            'name' => 'Filtered Entries',
            'filters' => [
                'statuses' => ['live', 'deleted'],
                'keyword' => ' annual report ',
                'completedOnly' => '1',
                'fieldConditions' => [
                    ['field' => 'title', 'operator' => 'contains', 'value' => 'Board'],
                    ['field' => 'body', 'operator' => 'contains', 'value' => 'bad,value'],
                    ['field' => 'andWhere', 'operator' => 'eq', 'value' => 'x'],
                ],
                'relations' => [
                    ['field' => 'topics', 'targetIds' => '12, 12, no, 15'],
                    ['field' => 'missing', 'targetIds' => '20'],
                ],
            ],
        ], null, [
            'supportsStatusFilter' => true,
            'supportsKeywordFilter' => true,
            'supportsCompletedFilter' => true,
            'statuses' => [
                ['value' => 'live'],
            ],
            'filterableFields' => [
                ['handle' => 'title', 'operators' => [['value' => 'contains']]],
                ['handle' => 'body', 'operators' => [['value' => 'contains']]],
                ['handle' => 'andWhere', 'operators' => [['value' => 'eq']]],
            ],
            'relationFields' => [
                ['handle' => 'topics'],
            ],
        ]);

        self::assertSame(['live'], $template->filters['statuses']);
        self::assertSame('annual report', $template->filters['keyword']);
        self::assertTrue($template->filters['completedOnly']);
        self::assertSame([
            [
                'field' => 'title',
                'operator' => 'contains',
                'value' => 'Board',
            ],
        ], $template->filters['fieldConditions']);
        self::assertSame([
            [
                'field' => 'topics',
                'targetIds' => [12, 15],
            ],
        ], $template->filters['relations']);
    }

    public function testCreateTemplateFromRequestNormalizesXmlSettings(): void
    {
        $service = new TemplateService();

        $template = $service->createTemplateFromRequest([
            'name' => 'XML Export',
            'format' => 'xml',
            'settings' => [
                'xml' => [
                    'rootElement' => '  orders  ',
                    'rowElement' => 'order',
                ],
            ],
            'fields' => [
                ['fieldPath' => 'title'],
            ],
        ]);

        self::assertSame('xml', $template->format);
        self::assertSame('orders', $template->settings['xml']['rootElement']);
        self::assertSame('order', $template->settings['xml']['rowElement']);
    }

    public function testXmlSettingsDefaultWhenAbsentFromRequest(): void
    {
        $service = new TemplateService();

        $template = $service->createTemplateFromRequest([
            'name' => 'CSV Export',
            'format' => 'csv',
            'fields' => [
                ['fieldPath' => 'title'],
            ],
        ]);

        self::assertSame('export', $template->settings['xml']['rootElement']);
        self::assertSame('row', $template->settings['xml']['rowElement']);
    }

    public function testXmlSettingsArePreservedWhenSwitchingAwayFromXml(): void
    {
        $service = new TemplateService();

        $existing = new ExportTemplate([
            'name' => 'Orders',
            'handle' => 'orders',
            'format' => 'xml',
            'settings' => [
                'xml' => ['rootElement' => 'orders', 'rowElement' => 'order'],
            ],
        ]);

        // A Standard-edition save (or any payload without settings[xml]) must
        // not erase previously configured XML names.
        $template = $service->createTemplateFromRequest([
            'name' => 'Orders',
            'format' => 'csv',
            'fields' => [
                ['fieldPath' => 'title'],
            ],
        ], $existing);

        self::assertSame('csv', $template->format);
        self::assertSame('orders', $template->settings['xml']['rootElement']);
        self::assertSame('order', $template->settings['xml']['rowElement']);
    }

    public function testPresentButEmptyXmlValuesAreKeptEmptyNotRestored(): void
    {
        $service = new TemplateService();

        $existing = new ExportTemplate([
            'name' => 'Orders',
            'handle' => 'orders',
            'format' => 'xml',
            'settings' => [
                'xml' => ['rootElement' => 'orders', 'rowElement' => 'order'],
            ],
        ]);

        // The user cleared both fields in the CP. Keeping them empty (instead
        // of silently restoring the old names) lets validateXmlSettings reject
        // the save explicitly — the user asked for a change, not a revert.
        $template = $service->createTemplateFromRequest([
            'name' => 'Orders',
            'format' => 'xml',
            'settings' => [
                'xml' => ['rootElement' => '', 'rowElement' => '   '],
            ],
            'fields' => [
                ['fieldPath' => 'title'],
            ],
        ], $existing);

        self::assertSame('', $template->settings['xml']['rootElement']);
        self::assertSame('', $template->settings['xml']['rowElement']);
        self::assertFalse($service->validateXmlSettings($template));
    }

    public function testPartialXmlPayloadMergesPerKeyWithExistingValues(): void
    {
        $service = new TemplateService();

        $existing = new ExportTemplate([
            'name' => 'Orders',
            'handle' => 'orders',
            'format' => 'xml',
            'settings' => [
                'xml' => ['rootElement' => 'orders', 'rowElement' => 'order'],
            ],
        ]);

        // Fallback is per key, not all-or-nothing: a payload that only sends
        // rootElement must not reset rowElement to the "row" default.
        $template = $service->createTemplateFromRequest([
            'name' => 'Orders',
            'format' => 'xml',
            'settings' => [
                'xml' => ['rootElement' => 'catalog'],
            ],
            'fields' => [
                ['fieldPath' => 'title'],
            ],
        ], $existing);

        self::assertSame('catalog', $template->settings['xml']['rootElement']);
        self::assertSame('order', $template->settings['xml']['rowElement']);
    }

    public function testValidateXmlSettingsRejectsInvalidNamesOnlyForXmlTemplates(): void
    {
        $service = new TemplateService();

        $xmlTemplate = new ExportTemplate([
            'format' => 'xml',
            'settings' => [
                'xml' => ['rootElement' => '123root', 'rowElement' => 'xmlRow'],
            ],
        ]);

        self::assertFalse($service->validateXmlSettings($xmlTemplate));
        self::assertSame(
            'XML element names must start with a letter or underscore.',
            $xmlTemplate->getFirstError('settings.xml.rootElement')
        );
        self::assertSame(
            'XML element names cannot use the reserved xml or xmlns names.',
            $xmlTemplate->getFirstError('settings.xml.rowElement')
        );

        // The same invalid stored values are ignored for non-XML formats so
        // switching formats never produces validation noise.
        $csvTemplate = new ExportTemplate([
            'format' => 'csv',
            'settings' => [
                'xml' => ['rootElement' => '123root', 'rowElement' => 'xmlRow'],
            ],
        ]);

        self::assertTrue($service->validateXmlSettings($csvTemplate));
        self::assertSame([], $csvTemplate->getErrors());
    }

    public function testValidateXmlSettingsRejectsEmptyNames(): void
    {
        $service = new TemplateService();

        $template = new ExportTemplate([
            'format' => 'xml',
            'settings' => [
                'xml' => ['rootElement' => '', 'rowElement' => '   '],
            ],
        ]);

        self::assertFalse($service->validateXmlSettings($template));
        self::assertSame('Enter an XML element name.', $template->getFirstError('settings.xml.rootElement'));
        self::assertSame('Enter an XML element name.', $template->getFirstError('settings.xml.rowElement'));
    }

    public function testValidateXmlSettingsAcceptsValidNames(): void
    {
        $service = new TemplateService();

        $template = new ExportTemplate([
            'format' => 'xml',
            'settings' => [
                'xml' => ['rootElement' => 'export', 'rowElement' => 'row'],
            ],
        ]);

        self::assertTrue($service->validateXmlSettings($template));
        self::assertSame([], $template->getErrors());
    }

    public function testValidateXmlSettingsRejectsNonScalarValuesInsteadOfCoercingThem(): void
    {
        // A malformed payload like settings[xml][rootElement][]=x arrives as
        // an array. PHP's (string) cast on an array produces the literal
        // string "Array", which would pass name validation and get
        // persisted as a real tag name. Non-scalar input must be rejected.
        $service = new TemplateService();

        $template = new ExportTemplate([
            'format' => 'xml',
            'settings' => [
                'xml' => ['rootElement' => ['nested' => 'value'], 'rowElement' => 'row'],
            ],
        ]);

        self::assertFalse($service->validateXmlSettings($template));
        self::assertSame('Enter an XML element name.', $template->getFirstError('settings.xml.rootElement'));
        self::assertNull($template->getFirstError('settings.xml.rowElement'));
    }

    public function testCreateTemplateFromRequestRejectsNonScalarXmlSettingsInsteadOfCoercingToArrayString(): void
    {
        $service = new TemplateService();

        $template = $service->createTemplateFromRequest([
            'name' => 'XML Export',
            'format' => 'xml',
            'settings' => [
                'xml' => [
                    'rootElement' => ['0' => 'x'],
                    'rowElement' => 'order',
                ],
            ],
            'fields' => [
                ['fieldPath' => 'title'],
            ],
        ]);

        // Must normalize to an empty string (rejected by validation), never
        // to the literal "Array".
        self::assertSame('', $template->settings['xml']['rootElement']);
        self::assertFalse($service->validateXmlSettings($template));
    }
}
