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

        try {
            $mimeType = $run->fileMimeType ?? ExportFileHelper::fileMimeType($run->format);
        } catch (InvalidArgumentException) {
            // Unknown format on a legacy/corrupt run row: fail closed as a
            // 404 rather than serving the file with a guessed MIME type.
            throw new NotFoundHttpException('The requested export file is no longer available.');
        }

        return Craft::$app->getResponse()->sendFile(
            $run->filePath,
            $run->fileName,
            [
                'mimeType' => $mimeType,
                'inline' => false,
            ]
        );
    }
}
