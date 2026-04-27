<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class StandardizeSsNodeFields extends Migration
{
    private function columnExists($table, $column)
    {
        $count = DB::selectOne(
            "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [DB::getDatabaseName(), $table, $column]
        );
        return $count->cnt > 0;
    }

    public function up()
    {
        // Rename columns using raw ALTER TABLE (avoids doctrine/dbal dependency)
        $renames = [
            ['cpu', 'node_cpu'],
            ['memory', 'node_memory'],
            ['disk', 'node_disk'],
            ['health', 'node_health'],
            ['billing_mode', 'node_traffic_rxtx_mode'],
        ];

        foreach ($renames as $pair) {
            if ($this->columnExists('ss_node', $pair[0]) && !$this->columnExists('ss_node', $pair[1])) {
                DB::statement("ALTER TABLE ss_node CHANGE COLUMN `{$pair[0]}` `{$pair[1]}` " . $this->getColumnDefinition($pair[0]));
            }
        }

        // Drop legacy rxtx_mode column
        if ($this->columnExists('ss_node', 'rxtx_mode')) {
            DB::statement("ALTER TABLE ss_node DROP COLUMN `rxtx_mode`");
        }

        // Add new columns
        if (!$this->columnExists('ss_node', 'node_country')) {
            DB::statement("ALTER TABLE ss_node ADD COLUMN `node_country` VARCHAR(64) NULL AFTER `country_code`");
        }
        if (!$this->columnExists('ss_node', 'node_city')) {
            DB::statement("ALTER TABLE ss_node ADD COLUMN `node_city` VARCHAR(64) NULL AFTER `node_country`");
        }
    }

    public function down()
    {
        // Reverse renames
        $renames = [
            ['node_cpu', 'cpu'],
            ['node_memory', 'memory'],
            ['node_disk', 'disk'],
            ['node_health', 'health'],
            ['node_traffic_rxtx_mode', 'billing_mode'],
        ];

        foreach ($renames as $pair) {
            if ($this->columnExists('ss_node', $pair[0]) && !$this->columnExists('ss_node', $pair[1])) {
                DB::statement("ALTER TABLE ss_node CHANGE COLUMN `{$pair[0]}` `{$pair[1]}` " . $this->getColumnDefinition($pair[0]));
            }
        }

        // Restore rxtx_mode (AFTER disk, which is the reverted name)
        if (!$this->columnExists('ss_node', 'rxtx_mode')) {
            DB::statement("ALTER TABLE ss_node ADD COLUMN `rxtx_mode` VARCHAR(255) NOT NULL DEFAULT 'tx' AFTER `disk`");
        }

        // Drop new columns
        if ($this->columnExists('ss_node', 'node_country')) {
            DB::statement("ALTER TABLE ss_node DROP COLUMN `node_country`");
        }
        if ($this->columnExists('ss_node', 'node_city')) {
            DB::statement("ALTER TABLE ss_node DROP COLUMN `node_city`");
        }
    }

    /**
     * Get the column definition from INFORMATION_SCHEMA to preserve type during rename.
     */
    private function getColumnDefinition($column)
    {
        $info = DB::selectOne(
            "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [DB::getDatabaseName(), 'ss_node', $column]
        );

        if (!$info) {
            return 'VARCHAR(255) NULL';
        }

        $type = strtoupper($info->COLUMN_TYPE);
        $null = ($info->IS_NULLABLE === 'YES') ? 'NULL' : 'NOT NULL';
        $default = ($info->COLUMN_DEFAULT !== null) ? " DEFAULT '{$info->COLUMN_DEFAULT}'" : '';

        return "{$type} {$null}{$default}";
    }
}
