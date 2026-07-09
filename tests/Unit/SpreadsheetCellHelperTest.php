<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\helpers\SpreadsheetCellHelper;
use PHPUnit\Framework\TestCase;

final class SpreadsheetCellHelperTest extends TestCase
{
    public function testNeutralizesSpreadsheetFormulaPrefixes(): void
    {
        foreach (['=SUM(A1:A2)', '+cmd', '-1+1', '@SUM(A1)', " \t=SUM(A1:A2)"] as $value) {
            self::assertSame("'" . $value, SpreadsheetCellHelper::sanitize($value));
        }
    }

    public function testLeavesOrdinaryStringsAndNumbersUntouched(): void
    {
        self::assertSame('Order 123', SpreadsheetCellHelper::sanitize('Order 123'));
        self::assertSame('-12.50', SpreadsheetCellHelper::sanitize('-12.50'));
        self::assertSame(-12.5, SpreadsheetCellHelper::sanitize(-12.5));
    }
}
