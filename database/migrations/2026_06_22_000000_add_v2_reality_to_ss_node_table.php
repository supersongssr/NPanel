<?php

/**
 * 给 ss_node 增加 vision-reality 协议所需的 REALITY X25519 密钥对 + shortId 列.
 *
 * REALITY (偷自己) 三件套, 配套使用:
 *   - v2_reality_pbk     : 公钥 (43 字符 base64url), 仅订阅层读取 -> 客户端 pbk
 *   - v2_reality_private : 私钥 (43 字符 base64url), 仅 xray 下发读取 -> 服务端 privateKey
 *   - v2_reality_sid     : shortId (8 hex), 两端共用 -> xray shortIds / 订阅 sid
 *
 * privateKey 永不进入订阅 (订阅层只读 pbk), pbk 永不进入 xray config (config 只读 private).
 * 主节点与所有 clone 共用同一组 (同一物理节点, host/sni 相同, register 时一次性生成).
 *
 * 幂等: up()/down() 均先做存在性检查, 重复执行 (含旧环境已手动 ALTER 过) 不报错.
 * 详见: app/Http/Controllers/Api/NodeApiController.php (vision-reality 模式)
 */

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddV2RealityToSsNodeTable extends Migration
{
    const TABLE = 'ss_node';
    const AFTER = 'v2_sni';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 幂等: 列已存在则跳过 (兼容早期手工 ALTER 过的环境).
        if (Schema::hasColumn(self::TABLE, 'v2_reality_pbk')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string('v2_reality_pbk', 64)
                ->nullable()
                ->comment('vision-reality公钥(base64url,43字符),仅订阅pbk读取')
                ->after(self::AFTER);
            $table->string('v2_reality_private', 64)
                ->nullable()
                ->comment('vision-reality私钥(base64url,43字符),仅xray privateKey读取')
                ->after('v2_reality_pbk');
            $table->string('v2_reality_sid', 16)
                ->nullable()
                ->comment('vision-reality shortId(8hex),两端共用')
                ->after('v2_reality_private');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach (['v2_reality_sid', 'v2_reality_private', 'v2_reality_pbk'] as $col) {
            if (!Schema::hasColumn(self::TABLE, $col)) {
                continue;
            }
            Schema::table(self::TABLE, function (Blueprint $table) use ($col) {
                $table->dropColumn($col);
            });
        }
    }
}
