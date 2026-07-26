<?php

namespace App\Services\Notification\Channels;

use App\Components\Curl;
use App\Components\Helpers;
use App\Services\Notification\Level;
use App\Services\Notification\Notification;
use App\Services\Notification\NotificationLogger;
use Log;

/**
 * Telegram Bot 告警渠道
 *
 * - 凭证来自配置: is_telegram / telegram_bot_token / telegram_chat_id
 * - minLevel 默认 ERROR (仅推送系统故障级告警); 可由配置 telegram_min_level 调整
 *
 * @package App\Services\Notification\Channels
 */
class TelegramChannel implements ChannelInterface
{
    const NAME     = 'telegram';
    const LOG_TYPE = 3; // email_log.type: 3 = Telegram

    /** @return string */
    public function name()
    {
        return self::NAME;
    }

    /**
     * 读取 telegram 配置子数组, 保证所有键存在 (兜底默认值)
     * @return array
     */
    private function telegramConfig()
    {
        $c = Helpers::systemConfig();
        $t = isset($c['telegram']) && is_array($c['telegram']) ? $c['telegram'] : [];

        return [
            'enabled'   => isset($t['enabled']) ? $t['enabled'] : false,
            'bot_token' => isset($t['bot_token']) ? $t['bot_token'] : '',
            'chat_id'   => isset($t['chat_id']) ? $t['chat_id'] : '',
            'min_level' => isset($t['min_level']) ? $t['min_level'] : 'error',
        ];
    }

    /** @return bool */
    public function enabled()
    {
        $t = $this->telegramConfig();

        return !empty($t['enabled'])
            && !empty($t['bot_token'])
            && !empty($t['chat_id']);
    }

    /** @return int */
    public function minLevel()
    {
        $t = $this->telegramConfig();

        return Level::fromName($t['min_level']);
    }

    /**
     * @param Notification $notification
     * @return bool
     */
    public function send(Notification $notification)
    {
        $t        = $this->telegramConfig();
        $botToken = $t['bot_token'];
        $chatId   = $t['chat_id'];

        // 与历史 Components/Telegram 行为一致: Markdown, 标题加粗
        $message = "*" . $notification->getTitle() . "*\n\n" . $notification->getContent();
        $url     = "https://api.telegram.org/bot{$botToken}/sendMessage";

        try {
            $response = Curl::send($url, [
                'chat_id'    => $chatId,
                'text'       => $message,
                'parse_mode' => 'Markdown',
            ]);
            $result = json_decode($response);

            if (is_object($result) && isset($result->ok) && $result->ok) {
                $this->logger()->log($notification, self::LOG_TYPE, self::NAME, 1);
                return true;
            }

            $err = (is_object($result) && isset($result->description)) ? $result->description : 'unknown error';
            $this->logger()->log($notification, self::LOG_TYPE, self::NAME, 0, $err);
            Log::error(sprintf('[Notify][telegram] send failed: %s | title=%s', $err, $notification->getTitle()));
            return false;
        } catch (\Exception $e) {
            $this->logger()->log($notification, self::LOG_TYPE, self::NAME, 0, $e->getMessage());
            Log::error('[Notify][telegram] exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @return NotificationLogger
     */
    private function logger()
    {
        return app(NotificationLogger::class);
    }
}
