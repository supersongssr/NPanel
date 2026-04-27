<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRxtxModeToSsNodeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ss_node', function (Blueprint $table) {
            // rx_tx: 'rx' = downstream only, 'tx' = upstream only, 'rxtx' = (rx+tx)/2
            $table->string('rxtx_mode')->default('tx')->after('disk')->comment('流量计费模式: rx, tx, rxtx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ss_node', function (Blueprint $table) {
            $table->dropColumn(['rxtx_mode']);
        });
    }
}
