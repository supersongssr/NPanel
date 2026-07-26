<?php

namespace App\Services\Notification;

/**
 * 告警级别
 *
 * 数字越大越严重, 用于渠道 minLevel 过滤: 仅当 level >= channel.minLevel() 才投递。
 * Class Level
 * @package App\Services\Notification
 */
class Level
{
    const ERROR   = 400; // 系统故障级: 节点离线/阻断/服务异常
    const WARNING = 300; // 需关注: 流量异常/阈值告警
    const INFO    = 200; // 日常事件: 工单/提现申请/日报

    /**
     * 配置值 -> 级别常量
     * 接受 'error'|'warning'|'info' (不区分大小写) 或数字; 无法识别时默认 ERROR
     *
     * @param string|int $name
     * @return int
     */
    public static function fromName($name)
    {
        if (is_numeric($name)) {
            return (int) $name;
        }
        $map = [
            'error'   => self::ERROR,
            'warning' => self::WARNING,
            'info'    => self::INFO,
        ];
        $key = strtolower(trim((string) $name));
        return isset($map[$key]) ? $map[$key] : self::ERROR;
    }
}
