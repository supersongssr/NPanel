<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddExpiredDnsCleanupIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add index on ss_node.heartbeat_at for expired-node queries
        $indexes = DB::select("SHOW INDEX FROM ss_node WHERE Key_name = 'idx_heartbeat_at'");
        if (empty($indexes)) {
            DB::statement("ALTER TABLE ss_node ADD INDEX `idx_heartbeat_at` (`heartbeat_at`)");
        }

        // Add index on ss_node.created_at for the NULL heartbeat_at fallback branch
        $indexesCreated = DB::select("SHOW INDEX FROM ss_node WHERE Key_name = 'idx_created_at'");
        if (empty($indexesCreated)) {
            DB::statement("ALTER TABLE ss_node ADD INDEX `idx_created_at` (`created_at`)");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $indexes = DB::select("SHOW INDEX FROM ss_node WHERE Key_name = 'idx_heartbeat_at'");
        if (!empty($indexes)) {
            DB::statement("ALTER TABLE ss_node DROP INDEX `idx_heartbeat_at`");
        }

        $indexesCreated = DB::select("SHOW INDEX FROM ss_node WHERE Key_name = 'idx_created_at'");
        if (!empty($indexesCreated)) {
            DB::statement("ALTER TABLE ss_node DROP INDEX `idx_created_at`");
        }
    }
}
