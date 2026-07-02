<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the field-picker init crash.
 *
 * The relation-filter *section wrapper* uses data-relation-filter-row as its
 * visibility target (matching the data-{x}-filter-row convention shared by all
 * filters). Individual relation *rows* must use a DIFFERENT attribute
 * (data-relation-row). When both shared data-relation-filter-row,
 * renumberAdvancedFilters() iterated [data-relation-filter-row], matched the
 * wrapper (which has no [data-relation-field] child on a new template), and
 * threw on `null.name = ...`. That throw happened inside initPicker() before
 * the add-field click handler was bound, leaving the whole field picker dead:
 * no export columns could be selected and new templates were unbuildable.
 */
final class FieldPickerMarkupTest extends TestCase
{
    private static function read(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . $relativePath;
        $contents = file_get_contents($path);
        self::assertNotFalse($contents, "Unable to read {$relativePath}");

        return $contents;
    }

    public function testRelationRowUsesADistinctAttributeFromTheSectionWrapper(): void
    {
        $edit = self::read('src/templates/_cp/exports/_edit.twig');

        // Individual relation rows are the .deb-filter-row elements inside the
        // [data-relation-filter-rows] container. They must carry data-relation-row.
        self::assertMatchesRegularExpression(
            '/<div\b(?=[^>]*\bclass="[^"]*\bdeb-filter-row\b[^"]*")(?=[^>]*\bdata-relation-row\b)[^>]*>/',
            $edit,
            'Relation rows must use data-relation-row.'
        );

        // The crash returns the moment a .deb-filter-row reuses the wrapper's
        // data-relation-filter-row attribute again.
        self::assertDoesNotMatchRegularExpression(
            '/<div\b(?=[^>]*\bclass="[^"]*\bdeb-filter-row\b[^"]*")(?=[^>]*\bdata-relation-filter-row\b)[^>]*>/',
            $edit,
            'A relation row must not reuse the wrapper attribute data-relation-filter-row.'
        );
    }

    public function testJsRenumbersRelationRowsByTheRowAttributeNotTheWrapper(): void
    {
        $js = self::read('src/web/assets/cp/dist/cp.js');

        // renumber/sync must target the row attribute so they never match the
        // section wrapper (which lacks a [data-relation-field] child).
        self::assertStringContainsString(
            "querySelectorAll('[data-relation-row]')",
            $js,
            'cp.js must query relation rows by [data-relation-row].'
        );
        self::assertStringNotContainsString(
            "querySelectorAll('[data-relation-filter-row]')",
            $js,
            'cp.js must not iterate [data-relation-filter-row]; that matches the section wrapper and crashes init.'
        );
    }

    public function testPresetFieldSettingsRoundTripThroughHiddenInputs(): void
    {
        $js = self::read('src/web/assets/cp/dist/cp.js');
        $twig = self::read('src/templates/_cp/exports/_includes/field-picker.twig');

        self::assertStringContainsString('field.settings?.separator', $js);
        self::assertStringContainsString('field.settings?.warnWhenBlank', $js);
        self::assertStringContainsString('field.settings?.decimalPlaces', $js);
        self::assertStringContainsString('[settings][separator]', $js);
        self::assertStringContainsString('[settings][warnWhenBlank]', $js);
        self::assertStringContainsString('[settings][decimalPlaces]', $js);
        self::assertStringContainsString('field.settings.separator', $twig);
        self::assertStringContainsString('field.settings.warnWhenBlank', $twig);
        self::assertStringContainsString('field.settings.decimalPlaces', $twig);
    }

    public function testApplyingPresetConfirmsBeforeReplacingSelectedFields(): void
    {
        $js = self::read('src/web/assets/cp/dist/cp.js');

        self::assertStringContainsString("selectedFields.querySelector('[data-selected-row]')", $js);
        self::assertStringContainsString(
            "confirm('Applying this preset will replace your current field selection. Continue?')",
            $js
        );
    }

    public function testDateFilterLabelsDoNotClaimEveryElementUsesCreationDate(): void
    {
        $edit = self::read('src/templates/_cp/exports/_edit.twig');

        self::assertStringContainsString('>Date From</label>', $edit);
        self::assertStringContainsString('>Date To</label>', $edit);
        self::assertStringNotContainsString('>Created From</label>', $edit);
        self::assertStringNotContainsString('>Created To</label>', $edit);
        self::assertStringContainsString('Uses the order date for Commerce orders', $edit);
    }
}
