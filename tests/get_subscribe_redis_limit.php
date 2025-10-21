<?php

/**
 * 获取订阅码在 Redis 中的 limit 值
 * 
 * 使用方法:
 * php get_subscribe_redis_limit.php [订阅码]
 * 
 * 示例:
 * php get_subscribe_redis_limit.php abc123def456
 */

// 引入 Laravel 框架
require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Redis;

try {
    // 初始化 Laravel 应用
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    // 获取命令行参数
    $code = $argv[1] ?? null;
    
    if (empty($code)) {
        echo "错误：请提供订阅码\n";
        echo "使用方法: php get_subscribe_redis_limit.php [订阅码]\n";
        echo "示例: php get_subscribe_redis_limit.php abc123def456\n";
        exit(1);
    }

    echo "正在查询订阅码: {$code}\n";
    echo str_repeat("=", 50) . "\n";

    // Redis 键名
    $subscribeKey = 'subscribe_limit:' . $code;
    $ipKey = 'subscribe_ip:' . $code;
    
    // 15分钟限制键名
    $fifteenMinuteKey = $subscribeKey . ':15m';
    // 1小时限制键名
    $oneHourKey = $subscribeKey . ':1h';

    try {
        // 检查 Redis 连接
        Redis::ping();
        echo "✓ Redis 连接成功\n\n";
    } catch (\Exception $e) {
        echo "✗ Redis 连接失败: " . $e->getMessage() . "\n";
        exit(1);
    }

    // 获取15分钟限制信息
    echo "📊 15分钟限制 (最多20次):\n";
    $fifteenMinuteCount = Redis::get($fifteenMinuteKey);
    $fifteenMinuteTTL = Redis::ttl($fifteenMinuteKey);
    
    if ($fifteenMinuteCount === null || $fifteenMinuteCount == false) {
        echo "  状态: 无数据 (可能未开始或已过期)\n";
        echo "  当前计数: 0/20\n";
        echo "  剩余时间: 已过期\n";
    } else {
        $fifteenMinuteCount = intval($fifteenMinuteCount);
        echo "  状态: 活跃\n";
        echo "  当前计数: {$fifteenMinuteCount}/20\n";
        echo "  剩余时间: " . ($fifteenMinuteTTL > 0 ? $fifteenMinuteTTL . " 秒" : "已过期") . "\n";
        echo "  进度: " . round(($fifteenMinuteCount / 20) * 100, 1) . "%\n";
    }
    echo "\n";

    // 获取1小时限制信息
    echo "📊 1小时限制 (最多30次):\n";
    $oneHourCount = Redis::get($oneHourKey);
    $oneHourTTL = Redis::ttl($oneHourKey);
    
    if ($oneHourCount === null  || $fifteenMinuteCount == false) {
        echo "  状态: 无数据 (可能未开始或已过期)\n";
        echo "  当前计数: 0/30\n";
        echo "  剩余时间: 已过期\n";
    } else {
        $oneHourCount = intval($oneHourCount);
        echo "  状态: 活跃\n";
        echo "  当前计数: {$oneHourCount}/30\n";
        echo "  剩余时间: " . ($oneHourTTL > 0 ? $oneHourTTL . " 秒" : "已过期") . "\n";
        echo "  进度: " . round(($oneHourCount / 30) * 100, 1) . "%\n";
    }
    echo "\n";

    // 获取IP限制信息
    echo "📊 IP限制 (最多15个不同IP):\n";
    $ipCount = Redis::sCard($ipKey);
    $ipTTL = Redis::ttl($ipKey);
    
    if ($ipCount === false || $ipCount == 0) {
        echo "  状态: 无数据 (可能未开始或已过期)\n";
        echo "  当前IP数量: 0/15\n";
        echo "  剩余时间: 已过期\n";
    } else {
        echo "  状态: 活跃\n";
        echo "  当前IP数量: {$ipCount}/15\n";
        echo "  剩余时间: " . ($ipTTL > 0 ? $ipTTL . " 秒" : "已过期") . "\n";
        echo "  进度: " . round(($ipCount / 15) * 100, 1) . "%\n";
        
        // 获取所有IP列表
        $ipList = Redis::sMembers($ipKey);
        if (!empty($ipList)) {
            echo "  IP列表:\n";
            foreach ($ipList as $ip) {
                echo "    - {$ip}\n";
            }
        }
    }
    echo "\n";

    // 总结状态
    echo "📋 限制状态总结:\n";
    
    // 15分钟状态
    if ($fifteenMinuteCount !== false && $fifteenMinuteCount >= 20) {
        echo "  ❌ 15分钟限制已超额 (将被拒绝)\n";
    } elseif ($fifteenMinuteCount !== false) {
        echo "  ⚠️  15分钟限制: " . (20 - $fifteenMinuteCount) . " 次剩余\n";
    } else {
        echo "  ✅ 15分钟限制: 无限制\n";
    }
    
    // 1小时状态
    if ($oneHourCount !== false && $oneHourCount >= 30) {
        echo "  ❌ 1小时限制已超额 (将被拒绝)\n";
    } elseif ($oneHourCount !== false) {
        echo "  ⚠️  1小时限制: " . (30 - $oneHourCount) . " 次剩余\n";
    } else {
        echo "  ✅ 1小时限制: 无限制\n";
    }
    
    // IP限制状态
    if ($ipCount >= 15) {
        echo "  ❌ IP限制已超额 (将被拒绝)\n";
    } elseif ($ipCount > 0) {
        echo "  ⚠️  IP限制: " . (15 - $ipCount) . " 个IP剩余\n";
    } else {
        echo "  ✅ IP限制: 无限制\n";
    }

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "查询完成！\n";

} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
