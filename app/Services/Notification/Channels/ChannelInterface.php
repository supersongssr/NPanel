<?php

namespace App\Services\Notification\Channels;

use App\Services\Notification\Notification;

/**
 * 告警投递渠道契约
 *
 * 新增渠道 (如 Bark/钉钉/飞书) 只需实现本接口, 并在 NotifyService::channels() 注册。
 *
 * @package App\Services\Notification\Channels
 */
interface ChannelInterface
{
    /**
     * 渠道唯一标识 (telegram / bark / ...)
     * @return string
     */
    public function name();

    /**
     * 是否启用 (查对应开关 + 必要凭证是否已填写)
     * @return bool
     */
    public function enabled();

    /**
     * 该渠道接受的最低级别; 低于此级别的消息不投递
     * @return int Level::*
     */
    public function minLevel();

    /**
     * 实际投递一条告警
     *
     * @param Notification $notification
     * @return bool 是否投递成功
     */
    public function send(Notification $notification);
}
