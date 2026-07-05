<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\helpers\XmlExportHelper;
use PHPUnit\Framework\TestCase;

final class XmlExportHelperTest extends TestCase
{
    public function testNativeElementNameKeepsValidNames(): void
    {
        self::assertSame('title', XmlExportHelper::nativeElementName('title'));
        self::assertSame('author.email', XmlExportHelper::nativeElementName('author.email'));
        self::assertSame('xmlData', XmlExportHelper::nativeElementName('xmlData'));
    }

    public function testNativeElementNameMatchesCraftFallback(): void
    {
        foreach (['', '123', 'Order Number', 'row&col'] as $name) {
            self::assertSame('item', XmlExportHelper::nativeElementName($name));
        }
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
