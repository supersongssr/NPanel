#!/usr/bin/env php
<?php
/**
 * 测试订阅格式输出
 *
 * 测试 sing-box JSON 和 Clash YAML 格式的订阅生成
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// 检查是否在测试环境
if (config('app.env') !== 'test') {
    echo "错误: 此脚本只能在 APP_ENV=test 环境下运行\n";
    exit(1);
}

use App\Http\Models\User;
use App\Http\Models\UserSubscribe;
use App\Http\Controllers\SubscribeController;

echo "========================================\n";
echo "测试订阅格式输出\n";
echo "========================================\n\n";

// 获取一个测试用户
$subscribe = UserSubscribe::where('status', 1)->first();

if (!$subscribe) {
    echo "错误: 没有找到启用的订阅码\n";
    exit(1);
}

$user = User::where('id', $subscribe->user_id)
    ->where('status', 1)
    ->where('enable', 1)
    ->first();

if (!$user) {
    echo "错误: 没有找到启用的用户\n";
    exit(1);
}

echo "测试用户: {$user->username} (ID: {$user->id})\n";
echo "订阅码: {$subscribe->code}\n";
echo "用户等级: {$user->level}\n";
echo "节点组: {$user->node_group}\n\n";

// 创建控制器实例
$controller = new SubscribeController();

// 使用反射调用私有方法进行测试
$reflection = new ReflectionClass($controller);

// 测试 sing-box 格式
echo "========================================\n";
echo "测试 sing-box JSON 格式\n";
echo "========================================\n";

try {
    $generateDirectSubscribe = $reflection->getMethod('generateDirectSubscribe');
    $generateDirectSubscribe->setAccessible(true);

    $singboxResult = $generateDirectSubscribe->invoke($controller, 'singbox', $subscribe, []);

    if ($singboxResult) {
        echo "✓ sing-box 配置生成成功\n";
        echo "输出长度: " . strlen($singboxResult) . " 字节\n\n";

        // 验证 JSON 格式
        $json = json_decode($singboxResult, true);
        if ($json === null) {
            echo "✗ JSON 格式验证失败: " . json_last_error_msg() . "\n";
        } else {
            echo "✓ JSON 格式验证通过\n";
            echo "✓ 包含 " . count($json['outbounds'] ?? []) . " 个 outbounds\n";
            echo "✓ 包含 " . count($json['inbounds'] ?? []) . " 个 inbounds\n\n";
        }

        // 保存到文件用于检查
        $singboxFile = __DIR__ . '/test_output_singbox.json';
        file_put_contents($singboxFile, $singboxResult);
        echo "✓ sing-box 配置已保存到: {$singboxFile}\n\n";

        // 显示前 50 行
        $lines = explode("\n", $singboxResult);
        echo "前 50 行预览:\n";
        echo str_repeat('-', 80) . "\n";
        echo implode("\n", array_slice($lines, 0, 50)) . "\n";
        echo str_repeat('-', 80) . "\n\n";

    } else {
        echo "✗ sing-box 配置生成失败\n";
    }
} catch (Exception $e) {
    echo "✗ sing-box 测试异常: " . $e->getMessage() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
}

// 测试 Clash 格式
echo "\n========================================\n";
echo "测试 Clash YAML 格式\n";
echo "========================================\n";

try {
    $generateDirectSubscribe = $reflection->getMethod('generateDirectSubscribe');
    $generateDirectSubscribe->setAccessible(true);

    $clashResult = $generateDirectSubscribe->invoke($controller, 'clash', $subscribe, []);

    if ($clashResult) {
        echo "✓ Clash 配置生成成功\n";
        echo "输出长度: " . strlen($clashResult) . " 字节\n\n";

        // 验证 YAML 格式基本语法
        if (preg_match('/^[\s\w:#\-\.\/\\\n\r\*\[\]\{\}\(\),@\$!=\'"|]+$/', $clashResult)) {
            echo "✓ YAML 格式基本验证通过\n";
        } else {
            echo "⚠ YAML 格式可能包含非法字符\n";
        }

        // 统计代理数量
        if (preg_match('/^- name:/m', $clashResult, $matches)) {
            $proxyCount = preg_match_all('/^- name:/m', $clashResult);
            echo "✓ 包含 {$proxyCount} 个代理节点\n";
        }

        // 保存到文件用于检查
        $clashFile = __DIR__ . '/test_output_clash.yaml';
        file_put_contents($clashFile, $clashResult);
        echo "✓ Clash 配置已保存到: {$clashFile}\n\n";

        // 显示前 50 行
        $lines = explode("\n", $clashResult);
        echo "前 50 行预览:\n";
        echo str_repeat('-', 80) . "\n";
        echo implode("\n", array_slice($lines, 0, 50)) . "\n";
        echo str_repeat('-', 80) . "\n\n";

    } else {
        echo "✗ Clash 配置生成失败\n";
    }
} catch (Exception $e) {
    echo "✗ Clash 测试异常: " . $e->getMessage() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
}

echo "\n========================================\n";
echo "测试完成\n";
echo "========================================\n";
