<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\helpers\XmlExportHelper;
use PHPUnit\Framework\TestCase;

final class XmlExportHelperTest extends TestCase
{
    public function testValidElementNamesPass(): void
    {
        self::assertNull(XmlExportHelper::validateElementName('export'));
        self::assertNull(XmlExportHelper::validateElementName('row'));
        self::assertNull(XmlExportHelper::validateElementName('_private'));
        self::assertNull(XmlExportHelper::validateElementName('order-line.item_2'));
        self::assertNull(XmlExportHelper::validateElementName('Rows'));
    }

    public function testEmptyNameIsRejected(): void
    {
        self::assertSame('Enter an XML element name.', XmlExportHelper::validateElementName(''));
        self::assertSame('Enter an XML element name.', XmlExportHelper::validateElementName('   '));
    }

    public function testLeadingDigitOrPunctuationIsRejected(): void
    {
        $expected = 'XML element names must start with a letter or underscore.';

        self::assertSame($expected, XmlExportHelper::validateElementName('123root'));
        self::assertSame($expected, XmlExportHelper::validateElementName('-row'));
        self::assertSame($expected, XmlExportHelper::validateElementName('.row'));
    }

    public function testSpacesAndUnsupportedCharactersAreRejected(): void
    {
        $expected = 'Use letters, numbers, underscores, hyphens, or periods. Spaces are not allowed.';

        self::assertSame($expected, XmlExportHelper::validateElementName('order number'));
        self::assertSame($expected, XmlExportHelper::validateElementName('row&col'));
        self::assertSame($expected, XmlExportHelper::validateElementName('row<item>'));
    }

    public function testReservedXmlNamesAreRejected(): void
    {
        $expected = 'XML element names cannot use the reserved xml or xmlns names.';

        self::assertSame($expected, XmlExportHelper::validateElementName('xml'));
        self::assertSame($expected, XmlExportHelper::validateElementName('XML'));
        self::assertSame($expected, XmlExportHelper::validateElementName('xmlns'));
        self::assertSame($expected, XmlExportHelper::validateElementName('xmlData'));
    }

    public function testElementNameFromLabelSanitizesTitles(): void
    {
        self::assertSame('order_number', XmlExportHelper::elementNameFromLabel('Order Number'));
        self::assertSame('total_eur', XmlExportHelper::elementNameFromLabel('Total (EUR)'));
        self::assertSame('title', XmlExportHelper::elementNameFromLabel('title'));
        self::assertSame('author.email', XmlExportHelper::elementNameFromLabel('author.email'));
    }

    public function testElementNameFromLabelHandlesLeadingDigitsAndEmptyLabels(): void
    {
        self::assertSame('field_123_count', XmlExportHelper::elementNameFromLabel('123 Count'));
        self::assertSame('field', XmlExportHelper::elementNameFromLabel(''));
        self::assertSame('field', XmlExportHelper::elementNameFromLabel('***'));
    }

    public function testElementNameFromLabelAdjustsReservedPrefixes(): void
    {
        $name = XmlExportHelper::elementNameFromLabel('XML Data');

        self::assertSame('field_xml_data', $name);
        self::assertNull(XmlExportHelper::validateElementName($name));
    }

    public function testGeneratedNamesAlwaysPassValidation(): void
    {
        foreach (['Order Number', '123', 'xmlns:foo', '   ', 'Größe (ø)', 'a b c'] as $label) {
            $name = XmlExportHelper::elementNameFromLabel($label);
            self::assertNull(XmlExportHelper::validateElementName($name), sprintf('Label "%s" produced invalid name "%s".', $label, $name));
        }
    }

    public function testCollisionsGetNumericSuffixesInColumnOrder(): void
    {
        self::assertSame(
            ['name', 'name_2', 'name_3'],
            XmlExportHelper::elementNamesForLabels(['Name', 'name', 'NAME'])
        );
    }

    public function testCollisionSuffixSkipsAlreadyTakenNames(): void
    {
        // The second "Name" wants name_2, but an explicit "Name 2" column
        // already produced it — the suffix keeps counting until free.
        self::assertSame(
            ['name', 'name_2', 'name_3'],
            XmlExportHelper::elementNamesForLabels(['Name', 'Name 2', 'name'])
        );
    }

    public function testCleanTextValueKeepsLegalWhitespaceAndUnicode(): void
    {
        self::assertSame("a\tb\nc\rd", XmlExportHelper::cleanTextValue("a\tb\nc\rd"));
        self::assertSame('Größe — 100% ✓', XmlExportHelper::cleanTextValue('Größe — 100% ✓'));
        self::assertSame('a & b < c > "d"', XmlExportHelper::cleanTextValue('a & b < c > "d"'));
    }

    public function testCleanTextValueStripsIllegalXml10Controls(): void
    {
        self::assertSame('ab', XmlExportHelper::cleanTextValue("a\x00b"));
        self::assertSame('ab', XmlExportHelper::cleanTextValue("a\x08b"));
        self::assertSame('ab', XmlExportHelper::cleanTextValue("a\x0Bb"));
        self::assertSame('ab', XmlExportHelper::cleanTextValue("a\x1Fb"));
    }

    public function testCleanTextValueSurvivesInvalidUtf8(): void
    {
        $result = XmlExportHelper::cleanTextValue("valid \xC3\x28 tail");

        self::assertStringContainsString('valid', $result);
        self::assertStringContainsString('tail', $result);
        self::assertSame(1, preg_match('//u', $result), 'Cleaned value must be valid UTF-8.');
    }
}
