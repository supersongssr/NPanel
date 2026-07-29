<?php

namespace App\Components;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * 节点流量重置时间存储 (Redis Hash, 非关键参数, 不入库).
 *
 * 背景:
 *   AutoResetNodeTraffic 按 reset_day 每月重置 traffic_used, 并用一道 32 天安全网
 *   (超过 32 天未重置则强制重置 + 告警) 兜底 cron 停跑 / 时钟漂移 / reset_day 错改.
 *   "上次重置时间" 只是安全网的计时基准, 非业务关键数据 —— 不写入 ss_node (热表),
 *   而存 Redis Hash, 避免 MySQL schema 膨胀.
 *
 * 结构:
 *   key   = node:traffic_reset_at   (Hash)
 *   field = {node_id}
 *   value = "Y-m-d H:i:s" (上次成功重置时间)
 *
 * 自愈性:
 *   Redis 重启 / 丢数据后, AutoResetNodeTraffic 每日运行的懒初始化阶段会发现
 *   全部缺失并重新写入 now(), 安全网重新开始 32 天计时 (期间由正常月度重置兜底).
 *
 * 容错:
 *   所有方法吞掉 Redis 异常并返回安全默认值 (null / [] / 0), 确保 Redis 故障
 *   不会拖垮关键的流量重置逻辑 (MySQL 操作). 降级时安全网暂时失效, 但月度
 *   正常重置不受影响. 失败记 warning 日志.
 *
 * 详见: app/Console/Commands/AutoResetNodeTraffic.php
 *       app/Http/Controllers/Api/NodeApiController.php (register)
 */
class NodeTrafficResetStore
{
    const KEY = 'node:traffic_reset_at';

    /**
     * 取某节点上次重置时间, 不存在返回 null.
     *
     * @param int $nodeId
     * @return string|null  "Y-m-d H:i:s" 或 null
     */
    public static function get($nodeId)
    {
        try {
            $val = Redis::hget(self::KEY, (string) $nodeId);
            return $val !== false && $val !== null ? (string) $val : null;
        } catch (\Exception $e) {
            Log::warning('[NodeTrafficResetStore] Redis hget 失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 记录某节点的重置时间 (默认当前时间).
     *
     * @param int          $nodeId
     * @param string|null  $timestamp  null = now()
     * @return void
     */
    public static function set($nodeId, $timestamp = null)
    {
        try {
            Redis::hset(self::KEY, (string) $nodeId, $timestamp ?: date('Y-m-d H:i:s'));
        } catch (\Exception $e) {
            Log::warning('[NodeTrafficResetStore] Redis hset 失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除某节点的重置时间记录 (节点被彻底删除 / 回收时清理).
     *
     * @param int $nodeId
     * @return void
     */
    public static function forget($nodeId)
    {
        try {
            Redis::hdel(self::KEY, (string) $nodeId);
        } catch (\Exception $e) {
            Log::warning('[NodeTrafficResetStore] Redis hdel 失败: ' . $e->getMessage());
        }
    }

    /**
     * 取全部节点的重置时间映射.
     *
     * @return array  [nodeId(int) => "Y-m-d H:i:s"]
     */
    public static function all()
    {
        try {
            $raw = Redis::hgetall(self::KEY) ?: [];
            $out = [];
            foreach ($raw as $id => $ts) {
                $out[(int) $id] = (string) $ts;
            }
            return $out;
        } catch (\Exception $e) {
            Log::warning('[NodeTrafficResetStore] Redis hgetall 失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 懒初始化: 给 $nodeIds 中尚无记录的节点批量写入 $timestamp.
     *
     * 用于 AutoResetNodeTraffic 每日运行时, 把迁移前 / Redis 重启后缺失记录的老节点
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
        $ts = $timestamp ?: date('Y-m-d H:i:s');
        try {
            $existing = Redis::hgetall(self::KEY) ?: [];
            $missing = [];
            foreach ($nodeIds as $id) {
                if (!isset($existing[(string) $id])) {
                    $missing[(string) $id] = $ts;
                }
            }
            if (!empty($missing)) {
                Redis::hmset(self::KEY, $missing);
            }
            return count($missing);
        } catch (\Exception $e) {
            Log::warning('[NodeTrafficResetStore] Redis initMissing 失败: ' . $e->getMessage());
            return 0;
        }
    }
}
