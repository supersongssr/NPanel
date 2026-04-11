<?php

namespace App\Components;

use App\Http\Models\EmailLog;
use Log;

class Telegram
{
    /**
     * 发送 Telegram 通知
     *
     * @param string $title   消息标题
     * @param string $content 消息内容
     * @return bool
     */
    public static function send($title, $content)
    {
        $systemConfig = Helpers::systemConfig();
        
        // 检查是否启用 Telegram 通知
        if (empty($systemConfig['telegram_bot_token']) || empty($systemConfig['telegram_chat_id']) || empty($systemConfig['is_telegram'])) {
            return false;
        }

        try {
            $botToken = $systemConfig['telegram_bot_token'];
            $chatId = $systemConfig['telegram_chat_id'];
            
            // 构建消息内容
            $message = "*{$title}*\n\n{$content}";
            
            // 使用你提供的 API 格式
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $data = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ];
            
            $response = Curl::send($url, $data);
            $result = json_decode($response);
            
            if ($result && $result->ok) {
                self::addLog($title, $content);
                return true;
            } else {
                $errorMsg = $result->description ?? 'Unknown error';
                self::addLog($title, $content, 0, $errorMsg);
                Log::error("Telegram notification failed: " . $errorMsg);
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Telegram notification exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 添加 Telegram 投递日志
     *
     * @param string $title   标题
     * @param string $content 内容
     * @param int    $status  投递状态：1成功，0失败
     * @param string $error   错误信息
     * @return bool
     */
    private static function addLog($title, $content, $status = 1, $error = '')
    {
        try {
            $log = new EmailLog();
            $log->type = 3; // 3代表Telegram通知
            $log->address = 'telegram';
            $log->title = $title;
            $log->content = $content;
            $log->status = $status;
            $log->error = $error;
            
            return $log->save();
        } catch (\Exception $e) {
            Log::error("Failed to save Telegram log: " . $e->getMessage());
            return false;
        }
    }
}