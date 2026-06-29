<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\helpers\CapabilityHelper;
use Luremo\DataExportBuilder\Plugin;
use PHPUnit\Framework\TestCase;

final class CapabilityHelperTest extends TestCase
{
    public function testStandardEditionDoesNotIncludeOperationalProFeatures(): void
    {
        self::assertFalse(CapabilityHelper::editionHasFeature(Plugin::EDITION_STANDARD, CapabilityHelper::FEATURE_XLSX));
        self::assertFalse(CapabilityHelper::editionHasFeature(Plugin::EDITION_STANDARD, CapabilityHelper::FEATURE_SCHEDULES));
        self::assertFalse(CapabilityHelper::editionHasFeature(Plugin::EDITION_STANDARD, CapabilityHelper::FEATURE_DELIVERY));
        self::assertFalse(CapabilityHelper::editionHasFeature(Plugin::EDITION_STANDARD, CapabilityHelper::FEATURE_ADVANCED_QUEUE));
        self::assertFalse(CapabilityHelper::editionHasFeature(Plugin::EDITION_STANDARD, CapabilityHelper::FEATURE_FORM_SUBMISSIONS));
    }

    public function testProEditionIncludesOperationalFeatures(): void
    {
        self::assertTrue(CapabilityHelper::editionHasFeature(Plugin::EDITION_PRO, CapabilityHelper::FEATURE_XLSX));
        self::assertTrue(CapabilityHelper::editionHasFeature(Plugin::EDITION_PRO, CapabilityHelper::FEATURE_SCHEDULES));
        self::assertTrue(CapabilityHelper::editionHasFeature(Plugin::EDITION_PRO, CapabilityHelper::FEATURE_DELIVERY));
        self::assertTrue(CapabilityHelper::editionHasFeature(Plugin::EDITION_PRO, CapabilityHelper::FEATURE_ADVANCED_QUEUE));
        self::assertTrue(CapabilityHelper::editionHasFeature(Plugin::EDITION_PRO, CapabilityHelper::FEATURE_FORM_SUBMISSIONS));
    }

    public function testFormatSupportMatchesEdition(): void
    {
        self::assertTrue(CapabilityHelper::supportsFormatForEdition(Plugin::EDITION_STANDARD, 'csv'));
        self::assertTrue(CapabilityHelper::supportsFormatForEdition(Plugin::EDITION_STANDARD, 'json'));
        self::assertFalse(CapabilityHelper::supportsFormatForEdition(Plugin::EDITION_STANDARD, 'xlsx'));
        self::assertTrue(CapabilityHelper::supportsFormatForEdition(Plugin::EDITION_PRO, 'xlsx'));
    }

    public function testFormieElementTypeHandleIsStable(): void
    {
        // The handle is persisted on saved templates and referenced by the UI
        // picker, the query dispatcher, and field discovery. Renaming it would
        // silently break every existing Formie template, so pin it.
        self::assertSame('formie-submissions', CapabilityHelper::ELEMENT_TYPE_FORMIE_SUBMISSIONS);
    }

    public function testFormieSubmissionsAreGatedBehindInstallation(): void
    {
        // Formie is not installed in the unit-test environment (only phpunit is
        // a dev dependency), so the install check short-circuits on class_exists
        // before ever touching the Craft runtime. The element type must stay
        // hidden whenever Formie is absent — same contract Commerce relies on.
        self::assertFalse(CapabilityHelper::isFormieInstalled());
        self::assertFalse(CapabilityHelper::supportsElementTypeHandle(CapabilityHelper::ELEMENT_TYPE_FORMIE_SUBMISSIONS));
    }

    public function testSupportedElementTypesExcludesFormieWhenNotInstalled(): void
    {
        $types = CapabilityHelper::supportedElementTypes();

        // The always-available core types are present regardless of edition.
        self::assertArrayHasKey('entries', $types);
        self::assertArrayHasKey('users', $types);

        // Formie (and the other optional, plugin-gated types) must not leak in
        // when their backing plugin is missing.
        self::assertArrayNotHasKey(CapabilityHelper::ELEMENT_TYPE_FORMIE_SUBMISSIONS, $types);
        self::assertArrayNotHasKey(CapabilityHelper::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS, $types);
        self::assertArrayNotHasKey('orders', $types);
    }
}
