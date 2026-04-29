<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateDnsRecordsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('dns_records')) {
            // Table exists — migrate its structure to our needs
            $cols = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'dns_records'", [DB::getDatabaseName()]);
            $existing = array_map(function ($c) { return $c->COLUMN_NAME; }, $cols);

            if (!in_array('root_domain', $existing)) {
                DB::statement("ALTER TABLE dns_records ADD COLUMN `root_domain` VARCHAR(255) NOT NULL AFTER `node_id`");
            }
            if (!in_array('subdomain', $existing)) {
                DB::statement("ALTER TABLE dns_records ADD COLUMN `subdomain` VARCHAR(255) NOT NULL AFTER `root_domain`");
            }
            if (!in_array('ip_addr', $existing)) {
                DB::statement("ALTER TABLE dns_records ADD COLUMN `ip_addr` VARCHAR(255) NOT NULL AFTER `record_type`");
            }
            if (!in_array('cf_record_id', $existing)) {
                DB::statement("ALTER TABLE dns_records ADD COLUMN `cf_record_id` VARCHAR(255) NULL AFTER `ip_addr`");
            }
            if (!in_array('cf_zone_id', $existing)) {
                DB::statement("ALTER TABLE dns_records ADD COLUMN `cf_zone_id` VARCHAR(255) NULL AFTER `cf_record_id`");
            }

            // Add indexes if not present
            $indexes = DB::select("SHOW INDEX FROM dns_records WHERE Key_name = 'dns_root_domain_index'");
            if (empty($indexes)) {
                DB::statement("ALTER TABLE dns_records ADD INDEX `dns_root_domain_index` (`root_domain`)");
            }
            $uniqueIndexes = DB::select("SHOW INDEX FROM dns_records WHERE Key_name = 'dns_unique_record'");
            if (empty($uniqueIndexes)) {
                DB::statement("ALTER TABLE dns_records ADD UNIQUE INDEX `dns_unique_record` (`subdomain`, `root_domain`, `record_type`)");
            }
        } else {
            Schema::create('dns_records', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('node_id')->index();
                $table->string('root_domain')->index();
                $table->string('subdomain');
                $table->string('record_type', 8);
                $table->string('ip_addr');
                $table->string('cf_record_id')->nullable();
                $table->string('cf_zone_id')->nullable();
                $table->timestamps();
                $table->unique(['subdomain', 'root_domain', 'record_type'], 'dns_unique_record');
            });
        }
    }

    public function down()
    {
        // Only drop if we created it; if it existed before, just remove our added columns
        Schema::dropIfExists('dns_records');
    }
}
