<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddFallbackConfig extends Migration
{
    public function up()
    {
        if (!DB::table('config')->where('name', 'node_fallback_host')->exists()) {
            DB::table('config')->insert([
                'name'  => 'node_fallback_host',
                'value' => 'npanel-nav.freessr.bid',
            ]);
        }
    }

    public function down()
    {
        DB::table('config')->where('name', 'node_fallback_host')->delete();
    }
}
