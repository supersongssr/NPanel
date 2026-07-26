<?php

namespace App\Services\Notification;

use App\Http\Models\EmailLog;
use Log;

/**
 * 告警投递日志写入 (复用 email_log 表)
 *
 * email_log.type 约定:
 *   1 = 邮件 (历史)
 *   2 = ServerChan (历史, 已废弃)
 *   3 = Telegram
 *
 * @package App\Services\Notification
 */
class NotificationLogger
{
    /**
     * 写入一条投递日志
     *
     * @param Notification $notification
     * @param int    $type    email_log.type
     * @param string $address 渠道标识 / 收信人
     * @param int    $status  1 成功 / 0 失败
     * @param string $error   失败原因
     * @return int|null 日志 ID, 写入异常时 null
     */
    public function log(Notification $notification, $type, $address, $status = 1, $error = '')
    {
        try {
            $log = new EmailLog();
            $log->type    = $type;
            $log->address = $address;
            $log->title   = $notification->getTitle();
            $log->content = $notification->getContent();
            $log->status  = $status;
            $log->error   = $error;
            $log->save();

            return $log->id;
        } catch (\Exception $e) {
            // 日志写入失败不应影响主流程
            Log::error('[Notify] save log failed: ' . $e->getMessage());
            return null;
        }
    }
}
