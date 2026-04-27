<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddV2NameAndHardwareToSsNodeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ss_node', function (Blueprint $table) {
            $table->string('v2_name')->default('vision-hy2-ws-grpc')->after('name');
            $table->string('cpu')->nullable()->after('info');
            $table->float('memory')->nullable()->after('cpu');
            $table->float('disk')->nullable()->after('memory');
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
            $table->dropColumn(['v2_name', 'cpu', 'memory', 'disk']);
        });
    }
}
