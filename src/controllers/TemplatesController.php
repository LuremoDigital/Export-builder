<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\controllers;

use Craft;
use craft\web\Controller;
use Luremo\DataExportBuilder\helpers\CapabilityHelper;
use Luremo\DataExportBuilder\helpers\ExportFileHelper;
use Luremo\DataExportBuilder\helpers\ExportFormatHelper;
use Luremo\DataExportBuilder\Plugin;
use Luremo\DataExportBuilder\web\assets\cp\CpAsset;
use JsonException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;
use yii\web\UploadedFile;

final class TemplatesController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!Craft::$app->getUser()->checkPermission('manageDataExports')) {
            throw new ForbiddenHttpException('You do not have permission to manage export templates.');
        }

        return true;
    }

    public function actionIndex(): Response
    {
        Craft::$app->getView()->registerAssetBundle(CpAsset::class);

        return $this->renderTemplate('data-export-builder/_cp/exports/index', [
            'templates' => Plugin::$plugin->get('templates')->getAllTemplates(),
            'isProEdition' => CapabilityHelper::isProEdition(),
        ]);
    }

    public function actionEdit(?int $templateId = null): Response
    {
        Craft::$app->getView()->registerAssetBundle(CpAsset::class);

        $template = $templateId
            ? Plugin::$plugin->get('templates')->getTemplateById($templateId)
            : Plugin::$plugin->get('templates')->createTemplateFromRequest([]);

        if ($template === null) {
            throw new NotFoundHttpException('Export template not found.');
        }

        $fieldPayload = Plugin::$plugin->get('fieldDiscovery')->getDiscoveryPayload(
            $template->elementType,
            (string)($template->filters['sectionUid'] ?? ''),
            false,
            isset($template->filters['formId']) ? (int)$template->filters['formId'] : null
        );

        return $this->renderTemplate('data-export-builder/_cp/exports/_edit', [
            'template' => $template,
            'fieldPayload' => $fieldPayload,
            'isProEdition' => CapabilityHelper::isProEdition(),
            'formatOptions' => ExportFormatHelper::optionsForEdition(CapabilityHelper::getEdition()),
            'formatInstructions' => ExportFormatHelper::formatInstructionsForEdition(CapabilityHelper::getEdition()),
            'elementTypeOptions' => Plugin::$plugin->get('fieldDiscovery')->getElementTypeOptions(),
            'runs' => $template->id ? Plugin::$plugin->get('templates')->getRunsForTemplate($template->id) : [],
            'volumeOptions' => Plugin::$plugin->get('deliveries')->getVolumeOptions(),
            'nextScheduledRun' => Plugin::$plugin->get('schedules')->getNextRunDate($template),
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $templateId = $request->getBodyParam('templateId');
        $existing = $templateId ? Plugin::$plugin->get('templates')->getTemplateById((int)$templateId) : null;

        if ($templateId && $existing === null) {
            throw new NotFoundHttpException('Export template not found.');
        }

        $bodyParams = $request->getBodyParams();
        $filters = is_array($bodyParams['filters'] ?? null) ? $bodyParams['filters'] : [];
        $fieldPayload = Plugin::$plugin->get('fieldDiscovery')->getDiscoveryPayload(
            (string)($bodyParams['elementType'] ?? $existing?->elementType ?? 'entries'),
            (string)($filters['sectionUid'] ?? ''),
            false,
            isset($filters['formId']) ? (int)$filters['formId'] : null
        );
        $template = Plugin::$plugin->get('templates')->createTemplateFromRequest(
            $bodyParams,
            $existing,
            $fieldPayload
        );

        if ($template->creatorId === null) {
            $template->creatorId = (int)Craft::$app->getUser()->getId();
        }

        if (!Plugin::$plugin->get('templates')->saveTemplate($template)) {
            Craft::$app->getView()->registerAssetBundle(CpAsset::class);
            Craft::$app->getSession()->setError('Could not save export template.');

            return $this->renderTemplate('data-export-builder/_cp/exports/_edit', [
                'template' => $template,
                'fieldPayload' => $fieldPayload,
                'isProEdition' => CapabilityHelper::isProEdition(),
                'formatOptions' => ExportFormatHelper::optionsForEdition(CapabilityHelper::getEdition()),
            'formatInstructions' => ExportFormatHelper::formatInstructionsForEdition(CapabilityHelper::getEdition()),
                'elementTypeOptions' => Plugin::$plugin->get('fieldDiscovery')->getElementTypeOptions(),
                'runs' => $template->id ? Plugin::$plugin->get('templates')->getRunsForTemplate((int)$template->id) : [],
                'volumeOptions' => Plugin::$plugin->get('deliveries')->getVolumeOptions(),
                'nextScheduledRun' => Plugin::$plugin->get('schedules')->getNextRunDate($template),
            ]);
        }

        Craft::$app->getSession()->setNotice('Export template saved.');

        return $this->redirect('data-export-builder/exports/' . $template->id);
    }

    public function actionDuplicate(?int $templateId = null): Response
    {
        $this->requirePostRequest();

        $templateId ??= (int)Craft::$app->getRequest()->getRequiredBodyParam('templateId');

        $template = Plugin::$plugin->get('templates')->getTemplateById($templateId);
        if ($template === null) {
            throw new NotFoundHttpException('Export template not found.');
        }

        $duplicate = Plugin::$plugin->get('templates')->duplicateTemplate($template, (int)Craft::$app->getUser()->getId());
        Craft::$app->getSession()->setNotice('Export template duplicated.');

        return $this->redirect('data-export-builder/exports/' . $duplicate->id);
    }

    public function actionExportConfig(int $templateId): Response
    {
        $template = Plugin::$plugin->get('templates')->getTemplateById($templateId);
        if ($template === null) {
            throw new NotFoundHttpException('Export template not found.');
        }

        $fileName = ExportFileHelper::sanitizeFileName($template->handle ?: $template->name) . '.json';
        try {
            $json = json_encode(
                Plugin::$plugin->get('templates')->exportTemplateConfig($template),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new ServerErrorHttpException('Could not encode export template config.', 0, $exception);
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()
            ->set('Content-Type', 'application/json; charset=UTF-8')
            ->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->content = $json . "\n";

        return $response;
    }

    public function actionImport(): Response
    {
        $this->requirePostRequest();

        $file = UploadedFile::getInstanceByName('templateFile');
        if ($file === null || $file->tempName === '' || $file->error !== UPLOAD_ERR_OK) {
            Craft::$app->getSession()->setError('Choose a template JSON file to import.');

            return $this->redirect('data-export-builder/exports');
        }

        try {
            $payload = json_decode((string)file_get_contents($file->tempName), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            Craft::$app->getSession()->setError('Template import failed: invalid JSON.');

            return $this->redirect('data-export-builder/exports');
        }

        if (!is_array($payload)) {
            Craft::$app->getSession()->setError('Template import failed: JSON must contain an object.');

            return $this->redirect('data-export-builder/exports');
        }

        if (!is_array($payload['template'] ?? null)) {
            Craft::$app->getSession()->setError('Template import failed: JSON must contain a template object.');

            return $this->redirect('data-export-builder/exports');
        }

        $templates = Plugin::$plugin->get('templates');
        $template = $templates->createTemplateFromImport($payload, (int)Craft::$app->getUser()->getId());
        $template->handle = $templates->generateUniqueHandle($template->handle);

        if (!$templates->saveTemplate($template)) {
            Craft::$app->getSession()->setError('Template import failed: ' . implode(' ', $template->getFirstErrors()));

            return $this->redirect('data-export-builder/exports');
        }

        Craft::$app->getSession()->setNotice('Export template imported.');

        return $this->redirect('data-export-builder/exports/' . $template->id);
    }

    public function actionDelete(?int $templateId = null): Response
    {
        $this->requirePostRequest();

        $templateId ??= (int)Craft::$app->getRequest()->getRequiredBodyParam('templateId');

        Plugin::$plugin->get('templates')->deleteTemplate($templateId);
        Craft::$app->getSession()->setNotice('Export template deleted.');

        return $this->redirect('data-export-builder/exports');
    }
}
