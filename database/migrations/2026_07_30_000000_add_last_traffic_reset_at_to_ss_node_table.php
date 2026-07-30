<?php

/**
 * 给 ss_node 增加 "上次流量重置时间" 列.
 *
 * 字段 last_traffic_reset_at: AutoResetNodeTraffic 的 32 天安全网计时基线.
 *   - 每次正常月度重置 / 强制兜底重置 / 节点回收 (applyId) 时刷新为 now.
 *   - register 时设为 now 作为活跃计费起点.
 *   - 安全网据此判定: 主节点 (is_clone=0) 若上次重置距今超过 32 天且 traffic_used>0,
 *     判定定时任务停跑 / 时钟漂移 / reset_day 错改, 强制清零 + 告警.
 *
 * 该字段非关键参数, 但需与 ss_node 强一致 (随节点生命周期, 不应脱表), 故直接挂 ss_node,
 * 不再使用独立 SQLite / Redis 存储 (简化部署, 免额外持久化依赖).
 *
 * 幂等: up()/down() 均先做存在性检查, 重复执行 (含旧环境已手工 ALTER 过) 不报错.
 * 回填: up() 末尾把存量节点统一置 NOW(), 作为安全网初始基线, 避免首次启用时全量误判.
 * 详见: app/Console/Commands/AutoResetNodeTraffic.php
 *       app/Http/Controllers/Api/NodeApiController.php (register / applyId)
 */

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddLastTrafficResetAtToSsNodeTable extends Migration
{
    const TABLE  = 'ss_node';
    const COLUMN = 'last_traffic_reset_at';
    const AFTER  = 'reset_day';

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
            $table->dateTime(self::COLUMN)
                ->nullable()
                ->comment('上次流量重置时间;AutoResetNodeTraffic 32天安全网计时基线')
                ->after(self::AFTER);
        });

        // 回填基线: 存量节点统一置 NOW(), 作为安全网初始计时基线.
        // 避免首次启用安全网时全部 NULL 节点被误判为"从未重置"而全量强制清零.
        DB::table(self::TABLE)
            ->whereNull(self::COLUMN)
            ->update([self::COLUMN => DB::raw('NOW()')]);
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
