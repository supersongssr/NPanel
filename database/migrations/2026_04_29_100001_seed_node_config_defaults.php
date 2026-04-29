<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Http\Models\Config;

class SeedNodeConfigDefaults extends Migration
{
    /**
     * where: config table
     * why: Anti-hardcoding — protocol presets and domain pool must live in DB, not in controller code.
     * how: Insert two new config rows with JSON defaults if they don't already exist.
     */
    public function up()
    {
        $defaults = [
            'node_domain_pool' => '[]',
            'node_protocol_presets' => json_encode([
                'threshold_mb' => 2048,
                'high' => 'xhttp-hy2-ws-grpc',
                'low' => 'vision-hy2-ws-grpc',
            ]),
        ];

        foreach ($defaults as $name => $value) {
            Config::firstOrCreate(['name' => $name], ['value' => $value]);
        }
    }

    public function down()
    {
        Config::whereIn('name', ['node_domain_pool', 'node_protocol_presets'])->delete();
    }
}
