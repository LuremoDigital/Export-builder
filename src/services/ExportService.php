<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use Luremo\DataExportBuilder\helpers\CapabilityHelper;
use Luremo\DataExportBuilder\helpers\DateFilterHelper;
use Luremo\DataExportBuilder\helpers\ExportFileHelper;
use Luremo\DataExportBuilder\helpers\ExportFormatHelper;
use Luremo\DataExportBuilder\helpers\ExportRetentionHelper;
use Luremo\DataExportBuilder\helpers\FilterApplier;
use Luremo\DataExportBuilder\helpers\FilterSpecMapper;
use Luremo\DataExportBuilder\helpers\FieldValueHelper;
use Luremo\DataExportBuilder\jobs\RunExportJob;
use Luremo\DataExportBuilder\models\ExportField;
use Luremo\DataExportBuilder\models\ExportRun;
use Luremo\DataExportBuilder\models\ExportTemplate;
use Luremo\DataExportBuilder\Plugin;
use Luremo\DataExportBuilder\records\ExportRunRecord;
use Luremo\DataExportBuilder\records\ExportTemplateRecord;
use OpenSpout\Common\Entity\Row as SpoutRow;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use verbb\formie\elements\Form as FormieForm;
use verbb\formie\elements\Submission as FormieSubmission;
use wheelform\db\Form as WheelformForm;
use wheelform\db\Message as WheelformMessage;
use yii\base\Exception;

final class ExportService extends Component
{
    private int $defaultQueueThreshold = 1000;
    private int $batchSize = 200;

    public function runTemplate(ExportTemplate $template, ?int $userId, bool $forceQueue = false): ExportRun
    {
        $query = $this->buildSourceQuery($template);
        $estimatedCount = $this->estimateRowCount($query);
        $run = $this->createRunRecord($template, $userId);

        if ($forceQueue || $this->shouldQueueForCount($template, $estimatedCount)) {
            Craft::$app->getQueue()->push(new RunExportJob(['runId' => $run->id]));

            return $run;
        }

        return $this->performRun((int)$run->id);
    }

    public function performRun(int $runId, ?callable $progressCallback = null): ExportRun
    {
        $runRecord = ExportRunRecord::findOne($runId);
        if ($runRecord === null) {
            throw new Exception(sprintf('Export run %d could not be found.', $runId));
        }

        $template = Plugin::$plugin->get('templates')->getTemplateById((int)$runRecord->templateId);
        if ($template === null) {
            throw new Exception(sprintf('Template %d could not be found.', (int)$runRecord->templateId));
        }

        try {
            $this->assertEditionRuntimeAccess($template);

            $runRecord->status = ExportRun::STATUS_RUNNING;
            $runRecord->startedAt = Db::prepareDateForDb(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $runRecord->errorMessage = null;
            $runRecord->save(false);

            $query = $this->buildSourceQuery($template);
            $total = $this->estimateRowCount($query);
            if (!in_array($template->elementType, [
                CapabilityHelper::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS,
                CapabilityHelper::ELEMENT_TYPE_FORMIE_SUBMISSIONS,
            ], true)) {
                $eagerLoadPaths = Plugin::$plugin->get('fieldDiscovery')->getEagerLoadPaths(
                    array_map(static fn(ExportField $field): string => $field->fieldPath, $template->getFieldsSorted())
                );

                $this->applyEagerLoadPaths($query, $eagerLoadPaths);
            }

            $filePath = ExportFileHelper::buildFilePath($template, new ExportRun(['id' => (int)$runRecord->id, 'format' => $template->format, 'templateId' => $template->id ?? 0]));
            $rowCount = match ($template->format) {
                ExportFormatHelper::FORMAT_CSV => $this->streamCsvExport($query, $template, $filePath, $total, (int)$runRecord->id, $progressCallback),
                ExportFormatHelper::FORMAT_JSON => $this->streamJsonExport($query, $template, $filePath, $total, $progressCallback),
                ExportFormatHelper::FORMAT_XLSX => $this->streamXlsxExport($query, $template, $filePath, $total, $progressCallback),
                ExportFormatHelper::FORMAT_XML => $this->streamXmlExport($query, $template, $filePath, $total, $progressCallback),
                default => throw new Exception(sprintf('Unsupported export format "%s".', $template->format)),
            };

            $runRecord->status = ExportRun::STATUS_COMPLETED;
            $runRecord->rowCount = $rowCount;
            $runRecord->filePath = $filePath;
            $runRecord->fileName = basename($filePath);
            $runRecord->fileMimeType = ExportFileHelper::fileMimeType($template->format);
            $deliveryResult = Plugin::$plugin->get('deliveries')->deliverRun($template, new ExportRun([
                'id' => (int)$runRecord->id,
                'templateId' => $template->id ?? 0,
                'status' => ExportRun::STATUS_COMPLETED,
                'format' => $template->format,
                'rowCount' => $rowCount,
                'filePath' => $filePath,
                'fileName' => basename($filePath),
                'fileMimeType' => ExportFileHelper::fileMimeType($template->format),
            ]));
            $runRecord->storageType = $deliveryResult['storageType'];
            if (!$deliveryResult['keepLocalCopy'] && is_file($filePath)) {
                unlink($filePath);
                $runRecord->filePath = null;
            }
            $runRecord->finishedAt = Db::prepareDateForDb(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $runRecord->save(false);

            Plugin::$plugin->get('templates')->touchLastRun($template->id ?? 0, (string)$runRecord->finishedAt);
        } catch (\Throwable $exception) {
            Craft::error($exception->getMessage(), 'data-export-builder');

            $runRecord->status = ExportRun::STATUS_FAILED;
            $runRecord->errorMessage = $exception->getMessage();
            $runRecord->finishedAt = Db::prepareDateForDb(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $runRecord->save(false);
        }

        return Plugin::$plugin->get('templates')->getRunById((int)$runRecord->id)
            ?? throw new Exception('Unable to reload export run.');
    }

    private function assertEditionRuntimeAccess(ExportTemplate $template): void
    {
        if (!CapabilityHelper::supportsElementTypeHandle($template->elementType)) {
            throw new Exception('This export type requires the Pro edition.');
        }

        if (!CapabilityHelper::supportsFormat($template->format)) {
            throw new Exception('This export format requires the Pro edition.');
        }

        if ((int)($template->settings['queueThreshold'] ?? $this->defaultQueueThreshold) !== $this->defaultQueueThreshold
            && !CapabilityHelper::hasFeature(CapabilityHelper::FEATURE_ADVANCED_QUEUE)
        ) {
            throw new Exception('Custom queue thresholds require the Pro edition.');
        }
    }

    public function shouldQueueForCount(ExportTemplate $template, int $count): bool
    {
        $threshold = (int)($template->settings['queueThreshold'] ?? $this->defaultQueueThreshold);

        return $count > $threshold;
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function exportElementQuery(
        ElementQueryInterface $query,
        ExportTemplate $template,
        string $valueMode = FieldValueHelper::MODE_FLAT_TEXT
    ): \Generator
    {
        $this->assertEditionRuntimeAccess($template);

        $fields = $template->getFieldsSorted();
        $eagerLoadPaths = Plugin::$plugin->get('fieldDiscovery')->getEagerLoadPaths(
            array_map(static fn(ExportField $field): string => $field->fieldPath, $fields)
        );

        $this->applyEagerLoadPaths($query, $eagerLoadPaths);

        foreach ($query->batch($this->batchSize) as $elements) {
            foreach ($elements as $element) {
                yield $this->buildAssocRow($element, $fields, $valueMode);
            }
        }
    }

    public function cleanupExpiredFiles(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $retentionDaysByTemplate = [];
        $cleaned = 0;

        foreach (ExportRunRecord::find()
            ->where(['status' => ExportRun::STATUS_COMPLETED])
            ->andWhere(['not', ['filePath' => null]])
            ->each(100) as $run
        ) {
            $templateId = (int)$run->templateId;
            if (!array_key_exists($templateId, $retentionDaysByTemplate)) {
                $template = ExportTemplateRecord::findOne($templateId);
                $settings = is_array($template?->settingsJson) ? $template->settingsJson : [];
                $retentionDaysByTemplate[$templateId] = ExportRetentionHelper::normalizeDays($settings['retentionDays'] ?? null);
            }

            $retentionDays = $retentionDaysByTemplate[$templateId];

            if ($retentionDays === null || !ExportRetentionHelper::isExpired($run->finishedAt, $retentionDays, $now)) {
                continue;
            }

            $filePath = (string)$run->filePath;
            if (is_file($filePath)) {
                if (!ExportFileHelper::isInsideExportPath($filePath)) {
                    Craft::warning(sprintf('Cleared expired export file outside export storage "%s".', $filePath), 'data-export-builder');
                } elseif (!@unlink($filePath)) {
                    Craft::warning(sprintf('Could not delete expired export file "%s".', $filePath), 'data-export-builder');
                    continue;
                }
            }

            $run->filePath = null;
            $run->save(false);
            $cleaned++;
        }

        return $cleaned;
    }

    public function buildSourceQuery(ExportTemplate $template): mixed
    {
        if ($template->elementType === CapabilityHelper::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS) {
            return $this->buildWheelformMessageQuery($template);
        }

        if ($template->elementType === CapabilityHelper::ELEMENT_TYPE_FORMIE_SUBMISSIONS) {
            return $this->buildFormieSubmissionQuery($template);
        }

        $supported = CapabilityHelper::supportedElementTypes();
        $elementClass = $supported[$template->elementType]['class'] ?? null;

        if ($elementClass === null || !is_subclass_of($elementClass, ElementInterface::class)) {
            throw new Exception(sprintf('Unsupported element type "%s".', $template->elementType));
        }

        /** @var class-string<ElementInterface> $elementClass */
        $query = $elementClass::find();

        if (method_exists($query, 'status')) {
            $query->status(null);
        }

        if (!empty($template->filters['completedOnly']) && method_exists($query, 'isCompleted')) {
            $query->isCompleted(true);
        }

        if (method_exists($query, 'site')) {
            $siteUid = (string)($template->filters['siteUid'] ?? '');
            $site = $siteUid !== '' ? Craft::$app->getSites()->getSiteByUid($siteUid) : null;
            $query->site($site?->handle ?? '*');
        }

        if ($template->elementType === 'entries' && method_exists($query, 'section')) {
            $sectionUid = (string)($template->filters['sectionUid'] ?? '');
            if ($sectionUid !== '') {
                $section = Craft::$app->getEntries()->getSectionByUid($sectionUid);
                if ($section !== null) {
                    $query->section($section->handle);
                }
            }
        }

        if ($template->elementType === CapabilityHelper::ELEMENT_TYPE_PRODUCTS && method_exists($query, 'type')) {
            $productTypeHandle = (string)($template->filters['productTypeHandle'] ?? '');
            if ($productTypeHandle !== '') {
                $query->type($productTypeHandle);
            }
        }

        $dateFrom = DateFilterHelper::normalizeDateInput($template->filters['dateFrom'] ?? null);
        $dateTo = DateFilterHelper::normalizeDateInput($template->filters['dateTo'] ?? null);
        $dateQueryMethod = $template->elementType === 'orders' && method_exists($query, 'dateOrdered')
            ? 'dateOrdered'
            : 'dateCreated';
        if (($dateFrom || $dateTo) && method_exists($query, $dateQueryMethod)) {
            // Leading 'and' is required: a Craft element-query array param without
            // it defaults to OR, so a from+to range would match (after-from OR
            // before-to) — i.e. almost every record. 'and' makes it a true range.
            $range = ['and'];
            if ($dateFrom) {
                $range[] = '>= ' . $dateFrom . ' 00:00:00';
            }
            if ($dateTo) {
                $range[] = '<= ' . $dateTo . ' 23:59:59';
            }
            $query->{$dateQueryMethod}($range);
        }

        if (method_exists($query, 'orderBy')) {
            $query->orderBy(['elements.dateCreated' => SORT_DESC, 'elements.id' => SORT_DESC]);
        }

        $this->applyAdvancedFilters($query, $template);

        return $query;
    }

    private function createRunRecord(ExportTemplate $template, ?int $userId): ExportRun
    {
        $record = new ExportRunRecord();
        $record->templateId = $template->id;
        $record->status = ExportRun::STATUS_QUEUED;
        $record->format = $template->format;
        $record->triggeredByUserId = $userId;
        $record->save(false);

        return Plugin::$plugin->get('templates')->getRunById((int)$record->id)
            ?? throw new Exception('Unable to create export run.');
    }

    private function estimateRowCount(mixed $query): int
    {
        return (int)(clone $query)->count();
    }

    private function streamCsvExport(
        mixed $query,
        ExportTemplate $template,
        string $filePath,
        int $total,
        int $runId,
        ?callable $progressCallback = null
    ): int {
        $handle = fopen($filePath, 'wb');
        if ($handle === false) {
            throw new Exception(sprintf('Could not open export file "%s" for writing.', $filePath));
        }

        $fields = $template->getFieldsSorted();
        fputcsv($handle, array_map(static fn(ExportField $field): string => $field->columnLabel, $fields));

        $processed = 0;
        $blankCounts = [];

        try {
            foreach ($query->batch($this->batchSize) as $elements) {
                foreach ($elements as $element) {
                    $row = $this->buildRow($element, $fields, 'csv');
                    fputcsv($handle, $row);

                    foreach ($fields as $index => $field) {
                        if (($field->settings['warnWhenBlank'] ?? false) && ($row[$index] ?? '') === '') {
                            $blankCounts[$field->columnLabel] = ($blankCounts[$field->columnLabel] ?? 0) + 1;
                        }
                    }

                    $processed++;
                }

                if ($progressCallback !== null) {
                    $progressCallback($processed, $total);
                }
            }
        } finally {
            fclose($handle);
        }

        foreach ($blankCounts as $field => $blankCount) {
            Craft::warning(sprintf(
                'Accounting export field "%s" was blank for %d of %d rows (run ID: %d).',
                $field,
                $blankCount,
                $processed,
                $runId
            ), 'data-export-builder');
        }

        return $processed;
    }

    private function streamJsonExport(
        mixed $query,
        ExportTemplate $template,
        string $filePath,
        int $total,
        ?callable $progressCallback = null
    ): int {
        $handle = fopen($filePath, 'wb');
        if ($handle === false) {
            throw new Exception(sprintf('Could not open export file "%s" for writing.', $filePath));
        }

        $fields = $template->getFieldsSorted();
        fwrite($handle, '[');

        $processed = 0;
        $isFirstRow = true;

        foreach ($query->batch($this->batchSize) as $elements) {
            foreach ($elements as $element) {
                $row = $this->buildAssocRow($element, $fields, 'json');
                fwrite($handle, ($isFirstRow ? '' : ',') . PHP_EOL . (json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'));
                $isFirstRow = false;
                $processed++;
            }

            if ($progressCallback !== null) {
                $progressCallback($processed, $total);
            }
        }

        fwrite($handle, $processed > 0 ? PHP_EOL . ']' : ']');
        fclose($handle);

        return $processed;
    }

    private function streamXlsxExport(
        mixed $query,
        ExportTemplate $template,
        string $filePath,
        int $total,
        ?callable $progressCallback = null
    ): int {
        $fields = $template->getFieldsSorted();

        // Stream rows straight to disk with a constant memory footprint. The
        // previous PhpSpreadsheet implementation built the entire workbook in
        // memory (one object per cell), which made large operational exports
        // (tens of thousands of rows) run for minutes and risk OOM in the queue
        // worker. openspout writes incrementally, so memory stays flat.
        $writer = new XlsxWriter();
        $writer->openToFile($filePath);

        $writer->addRow(SpoutRow::fromValues(array_map(
            static fn(ExportField $field): string => $field->columnLabel,
            array_values($fields)
        )));

        $processed = 0;

        foreach ($query->batch($this->batchSize) as $elements) {
            foreach ($elements as $element) {
                $row = $this->buildRow($element, $fields, FieldValueHelper::MODE_XLSX);

                $cells = [];
                foreach (array_values($row) as $value) {
                    if (is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                    }
                    $cells[] = (string)($value ?? '');
                }

                $writer->addRow(SpoutRow::fromValues($cells));
                $processed++;
            }

            if ($progressCallback !== null) {
                $progressCallback($processed, $total);
            }
        }

        $writer->close();

        return $processed;
    }

    private function streamXmlExport(
        mixed $query,
        ExportTemplate $template,
        string $filePath,
        int $total,
        ?callable $progressCallback = null
    ): int {
        $fields = $template->getFieldsSorted();
        $elementNames = array_map(
            static fn(ExportField $field): string => $field->fieldPath,
            array_values($fields)
        );

        $writer = new XmlExportWriter($filePath, StringHelper::toCamelCase($template->elementType));
        $writer->open();

        $processed = 0;

        try {
            foreach ($query->batch($this->batchSize) as $elements) {
                foreach ($elements as $element) {
                    $row = $this->buildRow($element, $fields, FieldValueHelper::MODE_FLAT_TEXT);
                    $cells = array_combine($elementNames, array_map(
                        static fn(mixed $value): string => (string)($value ?? ''),
                        array_values($row)
                    ));

                    $writer->writeRow($cells);
                    $processed++;
                }

                // Checked per-batch flush: an IO failure (disk full, vanished
                // mount) fails the run here instead of surfacing after the
                // export was already reported as progressing.
                $writer->flush();

                if ($progressCallback !== null) {
                    $progressCallback($processed, $total);
                }
            }

            $writer->close();
        } catch (\Throwable $exception) {
            $writer->abort();

            throw $exception;
        }

        return $processed;
    }

    /**
     * @param ExportField[] $fields
     * @return array<int, mixed>
     */
    private function buildRow(mixed $element, array $fields, string $format): array
    {
        return array_map(
            static fn(ExportField $field): mixed => self::resolveExportFieldValue($element, $field, $format),
            $fields
        );
    }

    /**
     * @param ExportField[] $fields
     * @return array<string, mixed>
     */
    private function buildAssocRow(mixed $element, array $fields, string $format): array
    {
        $row = [];

        foreach ($fields as $field) {
            $row[$field->columnLabel] = self::resolveExportFieldValue($element, $field, $format);
        }

        return $row;
    }

    private static function resolveExportFieldValue(mixed $element, ExportField $field, string $format): mixed
    {
        $separator = $field->settings['separator'] ?? ', ';
        $decimalPlaces = $field->settings['decimalPlaces'] ?? null;

        return FieldValueHelper::resolveFieldValue(
            $element,
            $field->fieldPath,
            $format,
            is_string($separator) ? $separator : ', ',
            is_int($decimalPlaces) ? $decimalPlaces : null
        );
    }

    private function buildWheelformMessageQuery(ExportTemplate $template): mixed
    {
        if (!CapabilityHelper::isWheelFormInstalled()) {
            throw new Exception('Wheel Form is not installed.');
        }

        $formId = (int)($template->filters['formId'] ?? 0);
        if ($formId <= 0) {
            throw new Exception('Select a Wheel Form before running this export.');
        }

        $form = WheelformForm::findOne($formId);
        if ($form === null) {
            throw new Exception(sprintf('Wheel Form form %d could not be found.', $formId));
        }

        $query = WheelformMessage::find()
            ->where(['form_id' => $formId])
            ->with(['value.field', 'form'])
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC]);

        $dateFrom = DateFilterHelper::normalizeDateInput($template->filters['dateFrom'] ?? null);
        if ($dateFrom !== null) {
            $query->andWhere(['>=', 'dateCreated', $dateFrom . ' 00:00:00']);
        }

        $dateTo = DateFilterHelper::normalizeDateInput($template->filters['dateTo'] ?? null);
        if ($dateTo !== null) {
            $query->andWhere(['<=', 'dateCreated', $dateTo . ' 23:59:59']);
        }

        return $query;
    }

    private function buildFormieSubmissionQuery(ExportTemplate $template): mixed
    {
        if (!CapabilityHelper::isFormieInstalled()) {
            throw new Exception('Formie is not installed.');
        }

        $formId = (int)($template->filters['formId'] ?? 0);
        if ($formId <= 0) {
            throw new Exception('Select a Formie form before running this export.');
        }

        $form = FormieForm::find()->status(null)->id($formId)->one();
        if ($form === null) {
            throw new Exception(sprintf('Formie form %d could not be found.', $formId));
        }

        $query = FormieSubmission::find()
            ->status(null)
            ->formId($formId)
            ->orderBy(['elements.dateCreated' => SORT_DESC, 'elements.id' => SORT_DESC]);

        $dateFrom = DateFilterHelper::normalizeDateInput($template->filters['dateFrom'] ?? null);
        $dateTo = DateFilterHelper::normalizeDateInput($template->filters['dateTo'] ?? null);
        if (($dateFrom || $dateTo) && method_exists($query, 'dateCreated')) {
            // Leading 'and' is required: a Craft element-query array param without
            // it defaults to OR, so a from+to range would match (after-from OR
            // before-to) — i.e. almost every record. 'and' makes it a true range.
            $range = ['and'];
            if ($dateFrom) {
                $range[] = '>= ' . $dateFrom . ' 00:00:00';
            }
            if ($dateTo) {
                $range[] = '<= ' . $dateTo . ' 23:59:59';
            }
            $query->dateCreated($range);
        }

        $this->applyAdvancedFilters($query, $template);

        return $query;
    }

    private function applyAdvancedFilters(mixed $query, ExportTemplate $template): void
    {
        $fieldPayload = Plugin::$plugin->get('fieldDiscovery')->getDiscoveryPayload(
            $template->elementType,
            (string)($template->filters['sectionUid'] ?? ''),
            false,
            isset($template->filters['formId']) ? (int)$template->filters['formId'] : null
        );

        $plan = FilterSpecMapper::toPlan(
            $template->filters,
            is_array($fieldPayload['filterableFields'] ?? null) ? $fieldPayload['filterableFields'] : [],
            is_array($fieldPayload['relationFields'] ?? null) ? $fieldPayload['relationFields'] : [],
            is_array($fieldPayload['statuses'] ?? null) ? $fieldPayload['statuses'] : [],
            (bool)($fieldPayload['supportsStatusFilter'] ?? false),
            (bool)($fieldPayload['supportsKeywordFilter'] ?? false)
        );

        FilterApplier::applyTo($query, $plan, $template->elementType);
    }

    /**
     * @param string[] $paths
     */
    private function applyEagerLoadPaths(mixed $query, array $paths): void
    {
        foreach (['lineItems' => 'withLineItems', 'transactions' => 'withTransactions'] as $path => $method) {
            if (in_array($path, $paths, true) && method_exists($query, $method)) {
                $query->{$method}(true);
                $paths = array_values(array_diff($paths, [$path]));
            }
        }

        if ($paths !== [] && method_exists($query, 'with')) {
            $query->with($paths);
        }
    }
}
