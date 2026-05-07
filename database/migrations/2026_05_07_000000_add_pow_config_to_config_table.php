<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddPowConfigToConfigTable extends Migration
{
    public function up()
    {
        if (!DB::table('config')->where('name', 'pow_base_difficulty')->exists()) {
            DB::table('config')->insert([
                'name'  => 'pow_base_difficulty',
                'value' => '10000',
            ]);
        }
    }

    public function down()
    {
        DB::table('config')->where('name', 'pow_base_difficulty')->delete();
    }
}
