<?php

namespace App\Components;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 节点流量重置时间存储 (中央 SQLite 状态库, 非关键参数, 不入 MySQL).
 *
 * 背景:
 *   AutoResetNodeTraffic 按 reset_day 每月重置 traffic_used, 并用一道 32 天安全网
 *   (超过 32 天未重置则强制重置 + 告警) 兜底 cron 停跑 / 时钟漂移 / reset_day 错改.
 *   "上次重置时间" 只是安全网的计时基准, 非业务关键数据, 但需长期持久化 (跨重启不丢),
 *   故存中央 SQLite (config 连接 sqlite_state), 不写入 ss_node (订阅热表).
 *
 * 存储:
 *   连接 = DB::connection('sqlite_state')  (config/database.php, 全局 SQLite 唯一入口)
 *   表   = node_traffic_reset (node_id PK, reset_at TEXT "Y-m-d H:i:s")
 *   文件 = storage/app/state.sqlite (首次连接懒创建, 表懒建 DDL)
 *
 * 容错:
 *   所有方法吞掉 DB 异常并返回安全默认值 (null / [] / 0), 确保存储故障 (磁盘满 / 权限)
 *   不会拖垮关键的流量重置逻辑 (MySQL 操作). 降级时安全网暂时失效, 月度正常重置不受影响.
 *
 * 详见: app/Console/Commands/AutoResetNodeTraffic.php
 *       app/Http/Controllers/Api/NodeApiController.php (register / applyId)
 */
class NodeTrafficResetStore
{
    /** 全局 SQLite 状态库连接名 (config/database.php). */
    const CONNECTION = 'sqlite_state';

    /** 节点流量重置时间表. */
    const TABLE = 'node_traffic_reset';

    /** 进程内表已建标记 (避免每次调用都 DDL). */
    private static $booted = false;

    /**
     * 懒建表 (CREATE TABLE IF NOT EXISTS, 幂等). 进程内只执行一次.
     * Laravel 的 SQLite 连接要求库文件预先存在 (不会自动创建空文件),
     * 故先 touch 出空文件, 再 CREATE TABLE.
     *
     * @return void
     */
    private static function boot()
    {
        if (self::$booted) {
            return;
        }
        try {
            // 确保库文件存在 (storage/app 已由 ACL 保障 www-data / root 可写).
            $path = config('database.connections.' . self::CONNECTION . '.database');
            if ($path && $path !== ':memory:' && !file_exists($path)) {
                @touch($path);
            }
            DB::connection(self::CONNECTION)->statement(
                'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (' .
                '  node_id INTEGER PRIMARY KEY,' .
                '  reset_at TEXT NOT NULL' .
                ')'
            );
            self::$booted = true;
        } catch (\Exception $e) {
            Log::warning('[NodeTrafficResetStore] 建表失败: ' . $e->getMessage());
        }
    }

    /**
     * 取某节点上次重置时间, 不存在返回 null.
     *
     * @param int $nodeId
     * @return string|null  "Y-m-d H:i:s" 或 null
     */
    public static function get($nodeId)
    {
        self::boot();
        try {
            $row = DB::connection(self::CONNECTION)->table(self::TABLE)
                ->where('node_id', (int) $nodeId)
                ->first();
            return $row ? (string) $row->reset_at : null;
        } catch (\Exception $e) {
            Log::warning('[NodeTrafficResetStore] get 失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 记录某节点的重置时间 (默认当前时间). 用 INSERT OR REPLACE 覆盖旧值.
     *
     * @param int          $nodeId
     * @param string|null  $timestamp  null = now()
     * @return void
     */
    public static function set($nodeId, $timestamp = null)
    {
        self::boot();
        try {
            DB::connection(self::CONNECTION)->statement(
                'INSERT OR REPLACE INTO ' . self::TABLE . ' (node_id, reset_at) VALUES (?, ?)',
                [(int) $nodeId, $timestamp ?: date('Y-m-d H:i:s')]
            );
        } catch (\Exception $e) {
            Log::warning('[NodeTrafficResetStore] set 失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除某节点的重置时间记录 (节点身份回收 / 彻底删除时清理).
     *
     * @param int $nodeId
     * @return void
     */
    public static function forget($nodeId)
    {
        self::boot();
        try {
            DB::connection(self::CONNECTION)->table(self::TABLE)
                ->where('node_id', (int) $nodeId)
                ->delete();
        } catch (\Exception $e) {
            Log::warning('[NodeTrafficResetStore] forget 失败: ' . $e->getMessage());
        }
    }

    /**
     * 取全部节点的重置时间映射.
     *
     * @return array  [nodeId(int) => "Y-m-d H:i:s"]
     */
    public static function all()
    {
        self::boot();
        try {
            $rows = DB::connection(self::CONNECTION)->table(self::TABLE)->get();
            $out = [];
            foreach ($rows as $r) {
                $out[(int) $r->node_id] = (string) $r->reset_at;
            }
            return $out;
        } catch (\Exception $e) {
            Log::warning('[NodeTrafficResetStore] all 失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 懒初始化: 给 $nodeIds 中尚无记录的节点批量写入 $timestamp.
     *
     * 用于 AutoResetNodeTraffic 每日运行时, 把缺失记录的节点 (首次启用 / SQLite 文件重建后)
     * 统一补成当前时间作为安全网计时基线 (不动流量, 不告警).
     *
     * @param array       $nodeIds    节点 ID 列表
     * @param string|null $timestamp  null = now()
     * @return int 实际写入的节点数
     */
    public static function initMissing(array $nodeIds, $timestamp = null)
    {
        if (empty($nodeIds)) {
            return 0;
        }
        self::boot();
        $ts = $timestamp ?: date('Y-m-d H:i:s');
        try {
            $conn = DB::connection(self::CONNECTION);
            // 取已存在的 node_id 集合 (whereIn 对数百 ID 无压力)
            $existing = $conn->table(self::TABLE)->whereIn('node_id', $nodeIds)->pluck('node_id');
            $existingSet = [];
            foreach ($existing as $id) {
                $existingSet[(int) $id] = true;
            }
            $missing = [];
            foreach ($nodeIds as $id) {
                $iid = (int) $id;
                if (!isset($existingSet[$iid])) {
                    $missing[] = ['node_id' => $iid, 'reset_at' => $ts];
                }
            }
            if (!empty($missing)) {
                $conn->table(self::TABLE)->insert($missing);
            }
            return count($missing);
        } catch (\Exception $e) {
            Log::warning('[NodeTrafficResetStore] initMissing 失败: ' . $e->getMessage());
            return 0;
        }
    }
}
