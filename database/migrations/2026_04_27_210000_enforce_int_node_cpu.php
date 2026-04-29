<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class EnforceIntNodeCpu extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE ss_node MODIFY COLUMN `node_cpu` INT(11) NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE ss_node MODIFY COLUMN `node_cpu` VARCHAR(255) NULL");
    }
}
