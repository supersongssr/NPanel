<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RenameRxtxModeAndAddNodeIds extends Migration
{
    /**
     * where: ss_node table
     * why: node_rxtx_mode is verbose; rename to node_rxtx for brevity.
     *      Add node_ids JSON field to seal the fission matrix under the main node.
     * how: Use raw SQL for rename to avoid doctrine/dbal dependency.
     */
    public function up()
    {
        DB::statement('ALTER TABLE ss_node CHANGE node_rxtx_mode node_rxtx VARCHAR(255) DEFAULT NULL');

        Schema::table('ss_node', function (Blueprint $table) {
            $table->text('node_ids')->nullable()->after('is_clone');
        });
    }

    public function down()
    {
        Schema::table('ss_node', function (Blueprint $table) {
            $table->dropColumn('node_ids');
        });

        DB::statement('ALTER TABLE ss_node CHANGE node_rxtx node_rxtx_mode VARCHAR(255) DEFAULT NULL');
    }
}
