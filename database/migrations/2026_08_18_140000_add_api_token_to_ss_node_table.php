<?php

/**
 * 给 ss_node 增加 v2 后端节点 API(/api/v2/backend)鉴权列.
 *
 * api_token: 每节点独立 Bearer token, 32 字节随机数的 base64url(43 字符), 全表唯一。
 *   由 artisan node:generate-api-tokens 批量生成(免逐节点人工配置), 已有值保持不变。
 * api_ip_allowlist: 可选逗号分隔 IP/CIDR 白名单(纵深防御, 非唯一鉴权因素), NULL=不启用。
 *
 * 幂等: up()/down() 均先做存在性检查, 重复执行(含手工 ALTER 过的环境)不报错。
 * 契约: xray-plugin-api docs/openapi.yaml
 */

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddApiTokenToSsNodeTable extends Migration
{
    const TABLE = 'ss_node';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn(self::TABLE, 'api_token')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string('api_token', 64)
                    ->nullable()
                    ->comment('v2节点API Bearer token(base64url 43字符, 每节点唯一)')
                    ->after('heartbeat_at')
                    ->unique();
            });
        }

        if (!Schema::hasColumn(self::TABLE, 'api_ip_allowlist')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string('api_ip_allowlist', 255)
                    ->nullable()
                    ->comment('v2节点API可选IP白名单, 逗号分隔IP/CIDR')
                    ->after('api_token');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn(self::TABLE, 'api_ip_allowlist')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropColumn('api_ip_allowlist');
            });
        }
        if (Schema::hasColumn(self::TABLE, 'api_token')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(['api_token']);
                $table->dropColumn('api_token');
            });
        }
    }
}
