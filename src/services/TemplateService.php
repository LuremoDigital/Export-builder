<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use Craft;
use craft\base\Component;
use DateTimeInterface;
use Luremo\DataExportBuilder\helpers\CapabilityHelper;
use Luremo\DataExportBuilder\helpers\DateFilterHelper;
use Luremo\DataExportBuilder\helpers\ExportFormatHelper;
use Luremo\DataExportBuilder\helpers\FilterSpecMapper;
use Luremo\DataExportBuilder\helpers\XmlExportHelper;
use Luremo\DataExportBuilder\models\ExportField;
use Luremo\DataExportBuilder\models\ExportRun;
use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\records\ExportFieldRecord;
use Luremo\DataExportBuilder\records\ExportRunRecord;
use Luremo\DataExportBuilder\records\ExportTemplateRecord;
use yii\base\Exception;

final class TemplateService extends Component
{
    private const STANDARD_QUEUE_THRESHOLD = 1000;

    /**
     * @return ExportTemplate[]
     */
    public function getAllTemplates(): array
    {
        $records = ExportTemplateRecord::find()
            ->orderBy(['dateUpdated' => SORT_DESC])
            ->all();

        return array_map(fn(ExportTemplateRecord $record): ExportTemplate => $this->buildTemplateModel($record, includeFields: false), $records);
    }

    public function getTemplateById(int $templateId): ?ExportTemplate
    {
        $record = ExportTemplateRecord::findOne($templateId);

        return $record ? $this->buildTemplateModel($record) : null;
    }

    /**
     * @return ExportRun[]
     */
    public function getRunsForTemplate(int $templateId, int $limit = 20): array
    {
        $records = ExportRunRecord::find()
            ->where(['templateId' => $templateId])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit($limit)
            ->all();

        return array_map([$this, 'buildRunModel'], $records);
    }

    public function getRunById(int $runId): ?ExportRun
    {
        $record = ExportRunRecord::findOne($runId);

        return $record ? $this->buildRunModel($record) : null;
    }

    public function getFailedRunCount(): int
    {
        if (!Craft::$app->getDb()->tableExists('{{%dataexportbuilder_export_runs}}')) {
            return 0;
        }

        return (int)ExportRunRecord::find()->where(['status' => ExportRun::STATUS_FAILED])->count();
    }

    public function saveTemplate(ExportTemplate $template, bool $validate = true): bool
    {
        if ($validate && !$template->validate()) {
            return false;
        }

        if ($template->fields === []) {
            $template->addError('fields', 'Select at least one export field.');

            return false;
        }

        foreach ($template->fields as $field) {
            if (!$field->validate()) {
                $template->addErrors($field->getErrors());

                return false;
            }
        }

        if (!$this->validateEditionAccess($template)) {
            return false;
        }

        if (!$this->validateXmlSettings($template)) {
            return false;
        }

        $existing = ExportTemplateRecord::find()->where(['handle' => $template->handle])->one();
        if ($existing !== null && (int)$existing->id !== (int)$template->id) {
            $template->addError('handle', 'Handle must be unique.');

            return false;
        }

        $record = $template->id ? ExportTemplateRecord::findOne($template->id) : new ExportTemplateRecord();
        if ($record === null) {
            throw new Exception('Unable to load export template record.');
        }

        $record->name = $template->name;
        $record->handle = $template->handle;
        $record->elementType = $template->elementType;
        $record->format = $template->format;
        $record->filtersJson = $template->filters;
        $record->settingsJson = $template->settings;
        $record->creatorId = $template->creatorId;
        $record->lastRunAt = $template->lastRunAt;
        $record->save(false);

        $template->id = (int)$record->id;
        $template->uid = $record->uid;

        ExportFieldRecord::deleteAll(['templateId' => $template->id]);
        foreach ($template->getFieldsSorted() as $sortOrder => $field) {
            $fieldRecord = new ExportFieldRecord();
            $fieldRecord->templateId = $template->id;
            $fieldRecord->fieldPath = $field->fieldPath;
            $fieldRecord->columnLabel = $field->columnLabel;
            $fieldRecord->sortOrder = $sortOrder + 1;
            $fieldRecord->settingsJson = $field->settings;
            $fieldRecord->save(false);

            $field->id = (int)$fieldRecord->id;
            $field->templateId = $template->id;
            $field->sortOrder = $sortOrder + 1;
            $field->uid = $fieldRecord->uid;
        }

        return true;
    }

    private function validateEditionAccess(ExportTemplate $template): bool
    {
        if (!CapabilityHelper::supportsElementTypeHandle($template->elementType)) {
            $template->addError('elementType', 'This export type requires the Pro edition.');

            return false;
        }

        if (!CapabilityHelper::supportsFormat($template->format)) {
            $template->addError('format', ExportFormatHelper::isSupported($template->format)
                ? sprintf('%s exports require the Pro edition.', ExportFormatHelper::label($template->format))
                : 'This export format is not supported.');

            return false;
        }

        if (!$this->isQueueThresholdAllowed($template->settings) && !CapabilityHelper::hasFeature(CapabilityHelper::FEATURE_ADVANCED_QUEUE)) {
            $template->addError('settings', 'Custom queue thresholds require the Pro edition.');

            return false;
        }

        if ($this->usesScheduling($template->settings) && !CapabilityHelper::hasFeature(CapabilityHelper::FEATURE_SCHEDULES)) {
            $template->addError('settings', 'Scheduled exports require the Pro edition.');

            return false;
        }

        if ($this->usesDelivery($template->settings) && !CapabilityHelper::hasFeature(CapabilityHelper::FEATURE_DELIVERY)) {
            $template->addError('settings', 'Email, webhook, and volume delivery require the Pro edition.');

            return false;
        }

        return true;
    }

    /**
     * Validates XML root/row element names — only when the template exports
     * XML. Stored `settings.xml` values on non-XML templates are preserved
     * untouched and never produce validation noise. Invalid names are
     * rejected, not silently rewritten, because they can become importer
     * contracts.
     */
    public function validateXmlSettings(ExportTemplate $template): bool
    {
        if ($template->format !== ExportFormatHelper::FORMAT_XML) {
            return true;
        }

        $xmlSettings = is_array($template->settings['xml'] ?? null) ? $template->settings['xml'] : [];
        $isValid = true;

        foreach (['rootElement', 'rowElement'] as $key) {
            $value = $xmlSettings[$key] ?? '';
            // Non-scalar input (e.g. a malformed array payload) must be
            // rejected outright, not coerced to the literal string "Array"
            // — a scalar-looking cast would let it silently pass validation.
            $error = is_scalar($value)
                ? XmlExportHelper::validateElementName((string)$value)
                : 'Enter an XML element name.';
            if ($error !== null) {
                $template->addError('settings.xml.' . $key, $error);
                $isValid = false;
            }
        }

        return $isValid;
    }

    public function deleteTemplate(int $templateId): bool
    {
        return (bool)ExportTemplateRecord::deleteAll(['id' => $templateId]);
    }

    public function duplicateTemplate(ExportTemplate $template, int $creatorId): ExportTemplate
    {
        $duplicate = new ExportTemplate([
            'name' => $template->name . ' Copy',
            'handle' => $this->generateHandle($template->handle . '-copy'),
            'elementType' => $template->elementType,
            'format' => $template->format,
            'filters' => $template->filters,
            'settings' => $template->settings,
            'creatorId' => $creatorId,
            'fields' => array_map(static fn(ExportField $field): ExportField => new ExportField([
                'fieldPath' => $field->fieldPath,
                'columnLabel' => $field->columnLabel,
                'sortOrder' => $field->sortOrder,
                'settings' => $field->settings,
            ]), $template->getFieldsSorted()),
        ]);

        $this->saveTemplate($duplicate);

        return $duplicate;
    }

    public function createTemplateFromRequest(array $payload, ?ExportTemplate $template = null, array $fieldPayload = []): ExportTemplate
    {
        $template ??= new ExportTemplate();
        $existingSettings = $template->settings;
        $settingsPayload = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
        $filtersPayload = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        $schedulePayload = is_array($settingsPayload['schedule'] ?? null) ? $settingsPayload['schedule'] : [];
        $deliveryPayload = is_array($settingsPayload['delivery'] ?? null) ? $settingsPayload['delivery'] : [];
        $scheduleFrequency = in_array(($schedulePayload['frequency'] ?? 'daily'), ['hourly', 'daily', 'weekly'], true)
            ? (string)($schedulePayload['frequency'] ?? 'daily')
            : 'daily';
        $template->name = trim((string)($payload['name'] ?? ''));
        $requestedHandle = trim((string)($payload['handle'] ?? ''));
        $template->handle = $this->generateHandle($requestedHandle !== '' ? $requestedHandle : $template->name);
        $template->elementType = (string)($payload['elementType'] ?? 'entries');
        $template->format = (string)($payload['format'] ?? 'csv');
        $template->filters = $this->normalizeFilters($filtersPayload, $fieldPayload);
        $template->settings = [
            'queueThreshold' => (int)($settingsPayload['queueThreshold'] ?? 1000),
            'xml' => $this->normalizeXmlSettings($settingsPayload, $existingSettings),
            'schedule' => [
                'enabled' => !empty($schedulePayload['enabled']),
                'frequency' => $scheduleFrequency,
                'hour' => max(0, min(23, (int)($schedulePayload['hour'] ?? 2))),
                'minute' => max(0, min(59, (int)($schedulePayload['minute'] ?? 0))),
                'weekdays' => $this->normalizeWeekdays($schedulePayload['weekdays'] ?? []),
                'lastScheduledAt' => $existingSettings['schedule']['lastScheduledAt'] ?? null,
            ],
            'delivery' => [
                'emailRecipients' => $this->normalizeStringList($deliveryPayload['emailRecipients'] ?? []),
                'emailSubject' => trim((string)($deliveryPayload['emailSubject'] ?? '')),
                'webhookUrl' => trim((string)($deliveryPayload['webhookUrl'] ?? '')),
                'webhookSecret' => trim((string)($deliveryPayload['webhookSecret'] ?? '')),
                'remoteVolumeUid' => trim((string)($deliveryPayload['remoteVolumeUid'] ?? '')),
                'remoteSubpath' => trim((string)($deliveryPayload['remoteSubpath'] ?? '')),
                'keepLocalCopy' => !array_key_exists('keepLocalCopy', $deliveryPayload) || (bool)$deliveryPayload['keepLocalCopy'],
            ],
        ];
        $template->fields = $this->hydrateFieldsFromRequest($payload['fields'] ?? []);

        return $template;
    }

    /**
     * @param array<string, mixed> $filtersPayload
     * @param array<string, mixed> $fieldPayload
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filtersPayload, array $fieldPayload): array
    {
        $advancedPlan = FilterSpecMapper::toPlan(
            $filtersPayload,
            is_array($fieldPayload['filterableFields'] ?? null) ? $fieldPayload['filterableFields'] : [],
            is_array($fieldPayload['relationFields'] ?? null) ? $fieldPayload['relationFields'] : [],
            is_array($fieldPayload['statuses'] ?? null) ? $fieldPayload['statuses'] : [],
            (bool)($fieldPayload['supportsStatusFilter'] ?? false),
            (bool)($fieldPayload['supportsKeywordFilter'] ?? false)
        );

        return [
            'sectionUid' => $filtersPayload['sectionUid'] ?? null,
            'siteUid' => $filtersPayload['siteUid'] ?? null,
            'formId' => $this->normalizeIntegerInput($filtersPayload['formId'] ?? null),
            'dateFrom' => DateFilterHelper::normalizeDateInput($filtersPayload['dateFrom'] ?? null),
            'dateTo' => DateFilterHelper::normalizeDateInput($filtersPayload['dateTo'] ?? null),
            'statuses' => $advancedPlan['statuses'],
            'keyword' => $advancedPlan['keyword'],
            'fieldConditions' => array_map(static fn(array $condition): array => [
                'field' => $condition['field'],
                'operator' => $condition['operator'],
                'value' => $condition['value'],
            ], $advancedPlan['fieldConditions']),
            'relations' => $advancedPlan['relations'],
        ];
    }

    /**
     * Normalizes the XML settings namespace. Keys absent from the request
     * (Standard edition UI, or older saved payloads) fall back to the
     * template's existing values so switching formats never erases work.
     * Present-but-empty values are kept as typed so validation can reject
     * them explicitly instead of silently restoring an old name.
     *
     * @param array<string, mixed> $settingsPayload
     * @param array<string, mixed> $existingSettings
     * @return array{rootElement:string,rowElement:string}
     */
    private function normalizeXmlSettings(array $settingsPayload, array $existingSettings): array
    {
        $xmlPayload = is_array($settingsPayload['xml'] ?? null) ? $settingsPayload['xml'] : [];
        $existingXml = is_array($existingSettings['xml'] ?? null) ? $existingSettings['xml'] : [];

        $normalized = [];
        foreach ([
            'rootElement' => XmlExportHelper::DEFAULT_ROOT_ELEMENT,
            'rowElement' => XmlExportHelper::DEFAULT_ROW_ELEMENT,
        ] as $key => $default) {
            $normalized[$key] = array_key_exists($key, $xmlPayload)
                ? trim($this->scalarStringOrEmpty($xmlPayload[$key]))
                : trim((string)($existingXml[$key] ?? $default));
        }

        return $normalized;
    }

    /**
     * Coerces a request value to a string only when it is already scalar.
     * A non-scalar payload (e.g. `settings[xml][rootElement][]=x`) must
     * normalize to an empty string — which validateXmlSettings() rejects —
     * rather than PHP's `(string)` cast of an array, which silently produces
     * the literal, validation-passing string "Array".
     */
    private function scalarStringOrEmpty(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }

    public function touchLastRun(int $templateId, string $timestamp): void
    {
        ExportTemplateRecord::updateAll(['lastRunAt' => $timestamp], ['id' => $templateId]);
    }

    public function updateTemplateSettings(int $templateId, array $settings): void
    {
        ExportTemplateRecord::updateAll(['settingsJson' => $settings], ['id' => $templateId]);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return ExportField[]
     */
    private function hydrateFieldsFromRequest(array $fields): array
    {
        $models = [];

        foreach ($fields as $index => $field) {
            $fieldPath = trim((string)($field['fieldPath'] ?? ''));
            if ($fieldPath === '') {
                continue;
            }

            $pathSegments = explode('.', $fieldPath);
            $defaultColumnLabel = (string)(end($pathSegments) ?: $fieldPath);
            $columnLabel = trim((string)($field['columnLabel'] ?? ''));

            $models[] = new ExportField([
                'fieldPath' => $fieldPath,
                'columnLabel' => $columnLabel !== '' ? $columnLabel : $defaultColumnLabel,
                'sortOrder' => (int)($field['sortOrder'] ?? ($index + 1)),
                'settings' => $this->normalizeFieldSettings($field['settings'] ?? null),
            ]);
        }

        usort($models, static fn(ExportField $a, ExportField $b): int => $a->sortOrder <=> $b->sortOrder);

        return $models;
    }

    /**
     * @return array{separator?:string,decimalPlaces?:int,warnWhenBlank?:bool}
     */
    private function normalizeFieldSettings(mixed $settings): array
    {
        if (!is_array($settings)) {
            return [];
        }

        $normalized = [];
        $separator = $settings['separator'] ?? null;
        if (is_scalar($separator)) {
            $separator = (string)$separator;
            if (trim($separator) !== '' && mb_strlen($separator) <= 20) {
                $normalized['separator'] = $separator;
            }
        }

        if (filter_var($settings['warnWhenBlank'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $normalized['warnWhenBlank'] = true;
        }

        if (is_numeric($settings['decimalPlaces'] ?? null)) {
            $decimalPlaces = (int)$settings['decimalPlaces'];
            if ($decimalPlaces >= 0 && $decimalPlaces <= 6) {
                $normalized['decimalPlaces'] = $decimalPlaces;
            }
        }

        return $normalized;
    }

    private function isQueueThresholdAllowed(array $settings): bool
    {
        return (int)($settings['queueThreshold'] ?? self::STANDARD_QUEUE_THRESHOLD) === self::STANDARD_QUEUE_THRESHOLD;
    }

    private function usesScheduling(array $settings): bool
    {
        return !empty($settings['schedule']['enabled']);
    }

    private function usesDelivery(array $settings): bool
    {
        $delivery = is_array($settings['delivery'] ?? null) ? $settings['delivery'] : [];

        return trim((string)($delivery['webhookUrl'] ?? '')) !== ''
            || trim((string)($delivery['remoteVolumeUid'] ?? '')) !== ''
            || trim((string)($delivery['remoteSubpath'] ?? '')) !== ''
            || trim((string)($delivery['emailSubject'] ?? '')) !== ''
            || trim((string)($delivery['webhookSecret'] ?? '')) !== ''
            || array_filter(is_array($delivery['emailRecipients'] ?? null) ? $delivery['emailRecipients'] : []);
    }

    private function buildTemplateModel(ExportTemplateRecord $record, bool $includeFields = true): ExportTemplate
    {
        $template = new ExportTemplate([
            'id' => (int)$record->id,
            'uid' => $record->uid,
            'name' => $record->name,
            'handle' => $record->handle,
            'elementType' => $record->elementType,
            'format' => $record->format,
            'filters' => is_array($record->filtersJson) ? $record->filtersJson : [],
            'settings' => is_array($record->settingsJson) ? $record->settingsJson : [],
            'creatorId' => $record->creatorId !== null ? (int)$record->creatorId : null,
            'lastRunAt' => $record->lastRunAt,
        ]);

        if ($includeFields) {
            $template->fields = array_map(
                static fn(ExportFieldRecord $fieldRecord): ExportField => new ExportField([
                    'id' => (int)$fieldRecord->id,
                    'uid' => $fieldRecord->uid,
                    'templateId' => (int)$fieldRecord->templateId,
                    'fieldPath' => $fieldRecord->fieldPath,
                    'columnLabel' => $fieldRecord->columnLabel,
                    'sortOrder' => (int)$fieldRecord->sortOrder,
                    'settings' => is_array($fieldRecord->settingsJson) ? $fieldRecord->settingsJson : [],
                ]),
                ExportFieldRecord::find()
                    ->where(['templateId' => $record->id])
                    ->orderBy(['sortOrder' => SORT_ASC])
                    ->all()
            );
        }

        return $template;
    }

    private function buildRunModel(ExportRunRecord $record): ExportRun
    {
        return new ExportRun([
            'id' => (int)$record->id,
            'uid' => $record->uid,
            'templateId' => (int)$record->templateId,
            'status' => $record->status,
            'format' => $record->format,
            'rowCount' => $record->rowCount !== null ? (int)$record->rowCount : null,
            'filePath' => $record->filePath,
            'fileName' => $record->fileName,
            'fileMimeType' => $record->fileMimeType,
            'storageType' => $record->storageType,
            'startedAt' => $this->normalizeDateTimeValue($record->startedAt),
            'finishedAt' => $this->normalizeDateTimeValue($record->finishedAt),
            'triggeredByUserId' => $record->triggeredByUserId !== null ? (int)$record->triggeredByUserId : null,
            'errorMessage' => $record->errorMessage,
            'dateCreated' => $this->normalizeDateTimeValue($record->dateCreated),
            'dateUpdated' => $this->normalizeDateTimeValue($record->dateUpdated),
        ]);
    }

    private function generateHandle(string $value): string
    {
        $handle = preg_replace('/[^a-zA-Z0-9_\\-]+/', '-', strtolower(trim($value))) ?: 'export-template';

        return trim($handle, '-');
    }

    private function normalizeDateTimeValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function normalizeIntegerInput(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * @return string[]
     */
    private function normalizeStringList(mixed $value): array
    {
        $values = is_array($value)
            ? $value
            : (preg_split('/[\r\n,]+/', trim((string)$value)) ?: []);

        return array_values(array_filter(array_map(
            static fn(mixed $item): string => trim((string)$item),
            $values
        ), static fn(string $item): bool => $item !== ''));
    }

    /**
     * @return string[]
     */
    private function normalizeWeekdays(mixed $value): array
    {
        return array_values(array_filter(array_map(
            static fn(mixed $item): string => strtolower(trim((string)$item)),
            is_array($value) ? $value : []
        ), static fn(string $item): bool => in_array($item, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true)));
    }
}
