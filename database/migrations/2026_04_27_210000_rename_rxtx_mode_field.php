<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

class RenameRxtxModeField extends Migration
{
    private function columnExists($table, $column)
    {
        $count = DB::selectOne(
            "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [DB::getDatabaseName(), $table, $column]
        );
        return $count->cnt > 0;
    }

    private function getColumnDef($column)
    {
        $info = DB::selectOne(
            "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'ss_node' AND COLUMN_NAME = ?",
            [DB::getDatabaseName(), $column]
        );
        if (!$info) return 'VARCHAR(255) NULL';
        $type = strtoupper($info->COLUMN_TYPE);
        $null = ($info->IS_NULLABLE === 'YES') ? 'NULL' : 'NOT NULL';
        $default = ($info->COLUMN_DEFAULT !== null) ? " DEFAULT '{$info->COLUMN_DEFAULT}'" : '';
        return "{$type} {$null}{$default}";
    }

    public function up()
    {
        if ($this->columnExists('ss_node', 'node_traffic_rxtx_mode') && !$this->columnExists('ss_node', 'node_rxtx_mode')) {
            $def = $this->getColumnDef('node_traffic_rxtx_mode');
            DB::statement("ALTER TABLE ss_node CHANGE COLUMN `node_traffic_rxtx_mode` `node_rxtx_mode` {$def}");
        }
    }

    public function down()
    {
        if ($this->columnExists('ss_node', 'node_rxtx_mode') && !$this->columnExists('ss_node', 'node_traffic_rxtx_mode')) {
            $def = $this->getColumnDef('node_rxtx_mode');
            DB::statement("ALTER TABLE ss_node CHANGE COLUMN `node_rxtx_mode` `node_traffic_rxtx_mode` {$def}");
        }
    }
}
