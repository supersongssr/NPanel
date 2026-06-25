<?php

/**
 * 给 ss_node 增加 ECH (Encrypted Client Hello) 下载规格列.
 *
 * 字段 v2_ech: 仅 xhttp-cdn / xhttp-cdn-hy2 模式的 xhttp 槽位写入,
 * 用于在客户端隐藏真实 SNI (抗 GFW 域名封锁 / 干扰).
 *   - 默认值: ech.{root_domain}+udp://1.1.1.1
 *   - 格式:   <发布 ECHConfigList 的 DNS HTTPS 记录域名> + <拉取该记录用的 DNS 解析器>
 *   - 覆盖:   域名池 node_domain_pool 中对应域名的 meta.ech 字段可自定义.
 *
 * hy2 槽位不经过 Cloudflare (CF 无法中继 UDP), 故不使用 ECH (v2_ech 保持 null).
 *
 * 幂等: up()/down() 均先做存在性检查, 重复执行 (含旧环境已手动 ALTER 过) 不报错.
 * 详见: app/Http/Controllers/Api/NodeApiController.php (xhttp-cdn / xhttp-cdn-hy2 模式)
 */

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddV2EchToSsNodeTable extends Migration
{
    const TABLE  = 'ss_node';
    const COLUMN = 'v2_ech';
    const AFTER  = 'v2_cdn_ip';

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
            $table->string(self::COLUMN, 255)
                ->nullable()
                ->comment('xhttp-cdn模式ECH下载规格(ech.{root}+udp://1.1.1.1),仅xhttp槽位,隐藏SNI')
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
