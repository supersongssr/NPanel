<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBillingModeToSsNodeTable extends Migration
{
    public function up()
    {
        Schema::table('ss_node', function (Blueprint $table) {
            $table->string('billing_mode')->default('tx')->after('v2_name')->comment('tx or rxtx');
        });
    }

    public function down()
    {
        Schema::table('ss_node', function (Blueprint $table) {
            $table->dropColumn('billing_mode');
        });
    }
}
