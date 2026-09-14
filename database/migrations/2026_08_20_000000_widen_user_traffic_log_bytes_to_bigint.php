<?php

/**
 * user_traffic_log.u/d 由 INT(11) 扩为 BIGINT.
 *
 * 背景: v2 后端 API(/api/v2/backend/traffic)按契约上报原始字节, 单条/同 userId
 * 合并后的值可远超 INT(11) 上限(2147483647 ≈ 2GiB; 60s 窗口内单用户持续
 * ~286Mbps 即可达到)。严格模式(DB_STRICT 默认 true)下 INSERT 抛 1264
 * out-of-range → 整批事务回滚 → 插件 at-least-once 无限重试同一批,
 * 该节点全部用户计费卡死。扩为 BIGINT 与 user.u/d 同口径, 从根上消除。
 *
 * 幂等: 仅当 u/d 两列仍为 int 时执行 ALTER, 手工扩容过的环境直接跳过。
 * down() 有意不回滚: BIGINT 收窄回 INT 会截断已入库的超大字节数。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WidenUserTrafficLogBytesToBigint extends Migration
{
    const TABLE = 'user_traffic_log';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $columns = DB::select(
            "SELECT COLUMN_NAME AS `name`, COLUMN_TYPE AS `type`
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN ('u', 'd')",
            [self::TABLE]
        );
        $needAlter = false;
        foreach ($columns as $column) {
            if (stripos($column->type, 'bigint') === false) {
                $needAlter = true; // 仍有 int 列 → 需要扩容
                break;
            }
        }
        if (!$needAlter) {
            return; // 两列均已是 BIGINT, 幂等跳过
        }

        DB::statement(
            'ALTER TABLE `' . self::TABLE . '`
                MODIFY `u` BIGINT(20) NOT NULL DEFAULT 0 COMMENT \'上传流量(字节)\',
                MODIFY `d` BIGINT(20) NOT NULL DEFAULT 0 COMMENT \'下载流量(字节)\''
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // 有意不回滚: BIGINT → INT 会截断已入库的超大字节数, 数据不安全, 保留扩容后的列型。
    }
}
