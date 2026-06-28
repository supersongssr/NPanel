<?php

/**
 * 给 ss_node 增加 xhttp-split (上下行分离 / downloadSettings) 模式所需的下行参数列.
 *
 * 字段:
 *   v2_xhttp_dl_host: 下行域名 dl_host = {rand8}d{nodeId}.{rootDomain}
 *     - 与上行 host ({rand8}n{id}) 不同 (独立随机前缀 + 'd' 标记), 但同一 rootDomain
 *       (复用泛域名证书 + 同一 nginx server_name). 主节点与所有 clone 共用同一个.
 *     - 仅供客户端 downloadSettings (TLS SNI serverName + HTTP Host); 下行直连 IP, 无需 DNS.
 *   v2_xhttp_dl_add:  下行真实 IP (downloadSettings.address)
 *     - ipv6 clone / ipv6-only 节点 = ipv6; 其余 = ipv4 (fallback ipv6).
 *     - 始终用真实 IP (不会在域名层暴露, 不担心被节点外看到).
 *
 * 服务端 (xray / nginx) 不读取这两列 — split 是纯客户端 downloadSettings 概念,
 * 仅订阅层 (SubscribeController vless extra) 读取并注入 downloadSettings.
 *
 * 幂等: up()/down() 均先做存在性检查, 重复执行不报错.
 * 详见: app/Http/Controllers/Api/NodeApiController.php (xhttp-split 模式)
 *       app/Http/Controllers/SubscribeController.php (extra.downloadSettings)
 */

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddV2XhttpSplitToSsNodeTable extends Migration
{
    const TABLE         = 'ss_node';
    const COL_DL_HOST   = 'v2_xhttp_dl_host';
    const COL_DL_ADD    = 'v2_xhttp_dl_add';
    const AFTER         = 'v2_xhttp_verify';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 幂等: 列已存在则跳过 (兼容早期手工 ALTER 过的环境).
        if (Schema::hasColumn(self::TABLE, self::COL_DL_HOST)) {
            // 补齐可能单独缺失的 dl_add 列.
            if (!Schema::hasColumn(self::TABLE, self::COL_DL_ADD)) {
                $this->addDlAddColumn();
            }
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string(self::COL_DL_HOST, 255)
                ->nullable()
                ->comment('xhttp-split下行域名dl_host({rand8}d{id}.rootDomain),仅订阅downloadSettings.serverName/host读取')
                ->after(self::AFTER);
        });
        $this->addDlAddColumn();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach ([self::COL_DL_HOST, self::COL_DL_ADD] as $col) {
            if (Schema::hasColumn(self::TABLE, $col)) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }

    /**
     * 单独追加 v2_xhttp_dl_add 列 (after v2_xhttp_dl_host, 保证相邻).
     * 抽出以便 up() 在 dl_host 已存在但 dl_add 缺失的半成品环境下补齐.
     */
    private function addDlAddColumn()
    {
        if (Schema::hasColumn(self::TABLE, self::COL_DL_ADD)) {
            return;
        }
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string(self::COL_DL_ADD, 64)
                ->nullable()
                ->comment('xhttp-split下行真实IP(downloadSettings.address);ipv6 clone/ipv6-only=ipv6,其余=ipv4 fallback ipv6')
                ->after(self::COL_DL_HOST);
        });
    }
}
