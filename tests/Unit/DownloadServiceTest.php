<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\models\ExportRun;
use Luremo\DataExportBuilder\services\DownloadService;
use PHPUnit\Framework\TestCase;
use yii\web\NotFoundHttpException;

final class DownloadServiceTest extends TestCase
{
    public function testResolveMimeTypeUsesStoredValueWhenFormatIsSupported(): void
    {
        $service = new DownloadService();
        $run = new ExportRun(['templateId' => 1, 'format' => 'csv', 'fileMimeType' => 'text/csv']);

        self::assertSame('text/csv', $service->resolveMimeType($run));
    }

    public function testResolveMimeTypeFallsBackToRegistryWhenNotStored(): void
    {
        $service = new DownloadService();
        $run = new ExportRun(['templateId' => 1, 'format' => 'xml', 'fileMimeType' => null]);

        self::assertSame('application/xml', $service->resolveMimeType($run));
    }

    public function testResolveMimeTypeFailsClosedForUnknownFormatEvenWithStoredMimeType(): void
    {
        // A corrupt/legacy run row can have both an unsupported format AND a
        // stale stored fileMimeType. The registry check must run regardless
        // of whether fileMimeType is already set, not be skipped by a `??`.
        $service = new DownloadService();
        $run = new ExportRun(['templateId' => 1, 'format' => 'yaml', 'fileMimeType' => 'application/x-yaml']);

        $this->expectException(NotFoundHttpException::class);
        $service->resolveMimeType($run);
    }
}
