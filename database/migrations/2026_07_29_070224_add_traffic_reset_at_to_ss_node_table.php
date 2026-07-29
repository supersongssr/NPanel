<?php

/**
 * 给 ss_node 增加 traffic_reset_at 列: 记录上次流量重置时间.
 *
 * 背景:
 *   AutoResetNodeTraffic 按 reset_day 每月重置 traffic_used=0, 但此前没有任何字段
 *   记录"上一次重置发生在何时". 一旦 cron 停跑 / 系统时钟漂移 / reset_day 被错改,
 *   流量永远不归零, 节点会因 traffic_left < 120GB 而永久熔断 (status=0), 且无声.
 *
 * 用途:
 *   - 每次流量重置 (正常按 reset_day 或 32 天强制兜底) 时写入 traffic_reset_at = now()
 *   - AutoResetNodeTraffic 据此检测 "超过 32 天未重置" 的异常节点:
 *       强制重置 + 发送 error notify, 防止节点被流量耗尽永久卡死.
 *
 * 字段:
 *   traffic_reset_at: datetime, nullable.
 *     - NULL 表示从未重置过 (老数据 / 新节点注册前).
 *     - 由 AutoResetNodeTraffic (正常重置 / 懒初始化 / 强制兜底) 与
 *       NodeApiController@register (新节点初始化) 共同写入.
 *
 * 不做 backfill: 迁移仅新增列 (所有现有节点初始为 NULL), 由 AutoResetNodeTraffic
 * 每日运行时懒初始化 (traffic_reset_at IS NULL → now(), 不动流量不告警),
 * 避免老节点首次启用安全网时被误判为"从未重置"而触发全量强制清零.
 *
 * 幂等: up()/down() 均先做存在性检查, 重复执行不报错.
 * 详见: app/Console/Commands/AutoResetNodeTraffic.php
 *       app/Http/Controllers/Api/NodeApiController.php (register)
 */

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTrafficResetAtToSsNodeTable extends Migration
{
    const TABLE = 'ss_node';
    const COL   = 'traffic_reset_at';
    const AFTER = 'reset_day';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn(self::TABLE, self::COL)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dateTime(self::COL)
                ->nullable()
                ->comment('上次流量重置时间(正常按reset_day / 懒初始化 / 32天强制兜底);NULL=从未重置')
                ->after(self::AFTER);
        });
        // 注: 不在此处 backfill. 现有节点初始为 NULL, 由 AutoResetNodeTraffic 每日运行时
        // 懒初始化为当前时间 (见该命令步骤 2), 避免一次性 UPDATE 全表.
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasColumn(self::TABLE, self::COL)) {
            return;
        }
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(self::COL);
        });
    }
}
