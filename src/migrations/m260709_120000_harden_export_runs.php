<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\migrations;

use craft\db\Migration;

final class m260709_120000_harden_export_runs extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists('{{%dataexportbuilder_export_runs}}')) {
            $this->safeAddColumn('{{%dataexportbuilder_export_runs}}', 'templateSnapshotJson', $this->json());
            $this->safeAddColumn('{{%dataexportbuilder_export_runs}}', 'deliveryKey', $this->string(64));
        }

        if ($this->db->tableExists('{{%dataexportbuilder_export_templates}}')) {
            $this->safeAddColumn('{{%dataexportbuilder_export_templates}}', 'lastScheduledSlot', $this->dateTime());
            $this->safeCreateIndex('idx_deb_templates_scheduled_slot', '{{%dataexportbuilder_export_templates}}', 'lastScheduledSlot');
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->tableExists('{{%dataexportbuilder_export_templates}}')) {
            $this->safeDropIndex('idx_deb_templates_scheduled_slot', '{{%dataexportbuilder_export_templates}}');
            $this->safeDropColumn('{{%dataexportbuilder_export_templates}}', 'lastScheduledSlot');
        }

        if ($this->db->tableExists('{{%dataexportbuilder_export_runs}}')) {
            $this->safeDropColumn('{{%dataexportbuilder_export_runs}}', 'templateSnapshotJson');
            $this->safeDropColumn('{{%dataexportbuilder_export_runs}}', 'deliveryKey');
        }

        return true;
    }

    private function safeAddColumn(string $table, string $column, mixed $type): void
    {
        if (!$this->columnExists($table, $column)) {
            $this->addColumn($table, $column, $type);
        }
    }

    private function safeDropColumn(string $table, string $column): void
    {
        if ($this->columnExists($table, $column)) {
            $this->dropColumn($table, $column);
        }
    }

    private function safeCreateIndex(string $name, string $table, string $column): void
    {
        if (!$this->indexExists($table, $name)) {
            $this->createIndex($name, $table, $column, false);
        }
    }

    private function safeDropIndex(string $name, string $table): void
    {
        if ($this->indexExists($table, $name)) {
            $this->dropIndex($name, $table);
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $rawTableName = $this->db->getSchema()->getRawTableName($table);
        $schema = $this->db->getSchema()->getTableSchema($rawTableName, true);

        return $schema !== null && isset($schema->columns[$column]);
    }

    private function indexExists(string $table, string $name): bool
    {
        $rawTableName = $this->db->getSchema()->getRawTableName($table);

        foreach ($this->db->getSchema()->getTableIndexes($rawTableName, true) as $index) {
            if ($index->name === $name) {
                return true;
            }
        }

        return false;
    }
}
