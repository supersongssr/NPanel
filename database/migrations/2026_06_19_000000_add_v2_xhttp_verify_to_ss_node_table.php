<?php

/**
 * 给 ss_node 增加 xhttp-verify 模式所需的随机校验 token 列.
 *
 * 字段 v2_xhttp_verify: 随机 UUID v4, 对应客户端请求头 Xhttp-Verify,
 * 在 nginx 层提前过滤主动探测 / 低版本客户端, 避免请求直达 xray inbound.
 *
 * 幂等: up()/down() 均先做存在性检查, 重复执行 (含旧环境已手动 ALTER 过) 不报错.
 * 详见: app/Http/Controllers/Api/NodeApiController.php (xhttp-verify 模式)
 */

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddV2XhttpVerifyToSsNodeTable extends Migration
{
    const TABLE  = 'ss_node';
    const COLUMN = 'v2_xhttp_verify';
    const AFTER  = 'v2_path';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 幂等: 列已存在则跳过 (兼容早期手工 ALTER 过的环境).
        if (Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string(self::COLUMN, 64)
                ->nullable()
                ->comment('xhttp-verify模式随机校验token(UUID v4),对应Xhttp-Verify header')
                ->after(self::AFTER);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(self::COLUMN);
        });
    }
}
