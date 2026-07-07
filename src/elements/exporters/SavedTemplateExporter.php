<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\elements\exporters;

use Craft;
use craft\base\ElementExporter;
use craft\elements\db\ElementQueryInterface;
use Luremo\DataExportBuilder\helpers\ExportFileHelper;
use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\Plugin;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class SavedTemplateExporter extends ElementExporter
{
    protected const TEMPLATE_ID = 0;

    public static function classForTemplate(ExportTemplate $template): string
    {
        $templateId = (int)$template->id;
        if ($templateId <= 0) {
            throw new \InvalidArgumentException('Saved template exporters require a template ID.');
        }

        $className = sprintf('SavedTemplateExporter_%d', $templateId);
        $class = __NAMESPACE__ . '\\' . $className;

        if (!class_exists($class, false)) {
            // Craft posts exporter class names only, so each template needs a
            // stable class name that can be resolved again on export requests.
            eval(sprintf(
                'namespace %s; final class %s extends SavedTemplateExporter { protected const TEMPLATE_ID = %d; }',
                __NAMESPACE__,
                $className,
                $templateId
            ));
        }

        return $class;
    }

    public static function displayName(): string
    {
        $fallback = Craft::t(Plugin::TRANSLATION_CATEGORY, 'Saved export template');

        return Craft::t(Plugin::TRANSLATION_CATEGORY, 'Template: {name}', [
            'name' => static::findTemplate()?->name ?? $fallback,
        ]);
    }

    public function getFilename(): string
    {
        $template = $this->template();

        return ExportFileHelper::sanitizeFileName($template->handle ?: $template->name);
    }

    public function export(ElementQueryInterface $query): mixed
    {
        if (!Craft::$app->getUser()->checkPermission('runDataExports')) {
            throw new ForbiddenHttpException('You do not have permission to run exports.');
        }

        $template = $this->template();

        return Plugin::$plugin->get('exports')->exportElementQuery($query, $template);
    }

    private function template(): ExportTemplate
    {
        $template = static::findTemplate();
        if ($template === null) {
            throw new NotFoundHttpException('Export template not found.');
        }

        return $template;
    }

    private static function findTemplate(): ?ExportTemplate
    {
        return Plugin::$plugin->get('templates')->getTemplateById(static::TEMPLATE_ID);
    }
}
