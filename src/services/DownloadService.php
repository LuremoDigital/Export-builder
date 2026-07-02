<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use Craft;
use craft\base\Component;
use InvalidArgumentException;
use Luremo\DataExportBuilder\helpers\ExportFileHelper;
use Luremo\DataExportBuilder\models\ExportRun;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class DownloadService extends Component
{
    public function sendRunFile(ExportRun $run): Response
    {
        if (!$run->isDownloadable() || $run->filePath === null || !is_file($run->filePath)) {
            throw new NotFoundHttpException('The requested export file is no longer available.');
        }

        if (!ExportFileHelper::isInsideExportPath($run->filePath)) {
            throw new NotFoundHttpException('Invalid export file path.');
        }

        return Craft::$app->getResponse()->sendFile(
            $run->filePath,
            $run->fileName,
            [
                'mimeType' => $this->resolveMimeType($run),
                'inline' => false,
            ]
        );
    }

    /**
     * Resolves the download MIME type. Public for unit testability (this
     * suite is deliberately Craft-runtime-free; sendFile() is not).
     *
     * Checks the format registry first — fail-closed for unknown formats —
     * rather than short-circuiting on a stored `fileMimeType` via `??`. A
     * corrupt/legacy row can have both a stale `format` AND a stored MIME
     * type; the `??` form would skip the registry check entirely for that row.
     */
    public function resolveMimeType(ExportRun $run): string
    {
        try {
            $registryMimeType = ExportFileHelper::fileMimeType($run->format);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException('The requested export file is no longer available.');
        }

        return $run->fileMimeType ?? $registryMimeType;
    }
}
