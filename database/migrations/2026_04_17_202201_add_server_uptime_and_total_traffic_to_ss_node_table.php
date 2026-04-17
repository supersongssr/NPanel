<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddServerUptimeAndTotalTrafficToSsNodeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ss_node', function (Blueprint $table) {
            $table->unsignedBigInteger('server_uptime')->default(0)->after('heartbeat_at')->comment('服务器运行时间（秒）');
            $table->unsignedBigInteger('server_total_traffic')->default(0)->after('server_uptime')->comment('服务器累计流量（字节）');
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
            $table->dropColumn(['server_uptime', 'server_total_traffic']);
        });
    }
}
