<?php

namespace App\Services\Notification;

use App\Services\Notification\Channels\ChannelInterface;
use Log;

/**
 * 管理员告警通知统一入口
 *
 * 业务点只声明 "发生了什么 / 多严重", 由本服务按各渠道 enabled + minLevel 自动分发。
 * 单渠道异常会被隔离, 不影响其他渠道。
 *
 * 用法:
 *   app(NotifyService::class)->error('节点离线', '节点 xxx 心跳超时');
 *   app(NotifyService::class)->warning('流量异常', '用户 xxx 1h 用量超标');
 *   app(NotifyService::class)->info('新工单', '工单 #123');
 *
 * 或用全局便捷函数:
 *   notify()->error(...);
 *
 * @package App\Services\Notification
 */
class NotifyService
{
    /** @var ChannelInterface[]|null */
    private $channelsCache = null;

    /**
     * ERROR 级别 (系统故障: 节点离线/阻断/服务异常)
     *
     * @param string $title
     * @param string $content
     * @param array  $context
     * @return int 实际投递成功的渠道数
     */
    public function error($title, $content, array $context = [], $force = false)
    {
        return $this->notify(Level::ERROR, $title, $content, $context, $force);
    }

    /**
     * WARNING 级别 (需关注: 流量异常/阈值告警)
     */
    public function warning($title, $content, array $context = [], $force = false)
    {
        return $this->notify(Level::WARNING, $title, $content, $context, $force);
    }

    /**
     * INFO 级别 (日常事件: 工单/提现申请/日报)
     */
    public function info($title, $content, array $context = [], $force = false)
    {
        return $this->notify(Level::INFO, $title, $content, $context, $force);
    }

    /**
     * 强制广播: 无视各渠道 min_level 过滤, 对所有 enabled() 渠道投递。
     *
     * 用于必须由管理员即时人工处理的关键业务通知 (如返利提现申请)。
     * 与 info/warning/error 的区别仅在投递策略: 绕过级别过滤, 只要凭证齐全即推送。
     *
     * @param string $title
     * @param string $content
     * @param array  $context
     * @return int 实际投递成功的渠道数
     */
    public function broadcast($title, $content, array $context = [])
    {
        return $this->notify(Level::INFO, $title, $content, $context, true);
    }

    /**
     * 核心分发: 遍历已注册渠道, 对 enabled 且 level >= minLevel 的投递
     *
     * @param int    $level   Level::*
     * @param string $title
     * @param string $content
     * @param array  $context
     * @return int 实际投递成功的渠道数
     */
    public function notify($level, $title, $content, array $context = [], $force = false)
    {
        $notification = new Notification($level, $title, $content, $context, $force);
        $sent         = 0;

        foreach ($this->channels() as $channel) {
            if (!$channel->enabled()) {
                continue;
            }
            // 强制投递无视级别过滤; 否则仅当 level >= channel.minLevel() 才投递
            if (!$notification->isForce() && $notification->getLevel() < $channel->minLevel()) {
                continue;
            }
            try {
                if ($channel->send($notification)) {
                    $sent++;
                }
            } catch (\Exception $e) {
                // 单渠道异常不影响其他渠道
                Log::error(sprintf('[Notify][%s] exception: %s', $channel->name(), $e->getMessage()));
            }
        }

        return $sent;
    }

    /**
     * 已注册渠道实例 (新增渠道在此追加即可)
     * @return ChannelInterface[]
     */
    private function channels()
    {
        if ($this->channelsCache === null) {
            $this->channelsCache = [
                app(Channels\TelegramChannel::class),
            ];
        }

        return $this->channelsCache;
    }
}
