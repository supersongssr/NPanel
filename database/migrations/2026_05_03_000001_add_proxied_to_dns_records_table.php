<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddProxiedToDnsRecordsTable extends Migration
{
    public function up()
    {
        $cols = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'dns_records'", [DB::getDatabaseName()]);
        $existing = array_map(function ($c) { return $c->COLUMN_NAME; }, $cols);

        if (!in_array('proxied', $existing)) {
            DB::statement("ALTER TABLE dns_records ADD COLUMN `proxied` TINYINT(1) NOT NULL DEFAULT 0 AFTER `cf_record_id`");
        }
    }

    public function down()
    {
        Schema::table('dns_records', function (Blueprint $table) {
            $table->dropColumn('proxied');
        });
    }
}
