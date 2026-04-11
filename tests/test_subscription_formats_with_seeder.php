#!/usr/bin/env php
<?php
/**
 * 测试订阅格式输出（包含测试数据创建）
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
use App\Http\Models\SsNode;
use App\Http\Controllers\SubscribeController;

echo "========================================\n";
echo "测试订阅格式输出（含测试数据）\n";
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

// 检查是否有测试节点
$nodeCount = SsNode::where('status', 1)
    ->where('is_subscribe', 1)
    ->where('node_group', $user->node_group)
    ->where('level', '<=', $user->level)
    ->count();

if ($nodeCount == 0) {
    echo "没有找到可用的测试节点，正在创建测试节点...\n\n";

    // 创建测试节点
    $testNodes = [
        [
            'name' => '测试-VMess-WS',
            'type' => 2, // VMess
            'server' => 'vmess.test.com',
            'v2_port' => 443,
            'v2_method' => 'auto',
            'v2_alter_id' => 0,
            'v2_net' => 'ws',
            'v2_type' => '',
            'v2_host' => 'vmess.test.com',
            'v2_path' => '/vmess',
            'v2_tls' => 1,
            'v2_sni' => 'vmess.test.com',
            'v2_alpn' => 'h2,http/1.1',
            'v2_flow' => '',
            'v2_servicename' => '',
            'v2_mode' => '',
            'v2_fp' => '',
            'v2_encryption' => 'none',
            'status' => 1,
            'is_subscribe' => 1,
            'node_group' => $user->node_group,
            'level' => 0,
            'traffic_rate' => 1,
            'traffic_left_daily' => 100,
            'node_uuid' => '',
        ],
        [
            'name' => '测试-VLESS-WS',
            'type' => 3, // VLESS
            'server' => 'vless.test.com',
            'v2_port' => 443,
            'v2_method' => '',
            'v2_alter_id' => 0,
            'v2_net' => 'ws',
            'v2_type' => '',
            'v2_host' => 'vless.test.com',
            'v2_path' => '/vless',
            'v2_tls' => 1,
            'v2_sni' => 'vless.test.com',
            'v2_alpn' => 'h2,http/1.1',
            'v2_flow' => 'xtls-rprx-vision',
            'v2_servicename' => '',
            'v2_mode' => '',
            'v2_fp' => 'chrome',
            'v2_encryption' => 'none',
            'status' => 1,
            'is_subscribe' => 1,
            'node_group' => $user->node_group,
            'level' => 0,
            'traffic_rate' => 1,
            'traffic_left_daily' => 100,
            'node_uuid' => '',
        ],
        [
            'name' => '测试-Trojan-GRPC',
            'type' => 4, // Trojan
            'server' => 'trojan.test.com',
            'v2_port' => 443,
            'v2_method' => '',
            'v2_alter_id' => 0,
            'v2_net' => 'grpc',
            'v2_type' => '',
            'v2_host' => '',
            'v2_path' => '',
            'v2_tls' => 1,
            'v2_sni' => 'trojan.test.com',
            'v2_alpn' => 'h2',
            'v2_flow' => '',
            'v2_servicename' => 'trojan',
            'v2_mode' => '',
            'v2_fp' => '',
            'v2_encryption' => '',
            'status' => 1,
            'is_subscribe' => 1,
            'node_group' => $user->node_group,
            'level' => 0,
            'traffic_rate' => 1.5,
            'traffic_left_daily' => 100,
            'node_uuid' => '',
        ],
    ];

    foreach ($testNodes as $nodeData) {
        SsNode::create($nodeData);
        echo "✓ 创建测试节点: {$nodeData['name']}\n";
    }

    echo "\n";
}

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
            echo "✓ 包含 " . count($json['inbounds'] ?? []) . " 个 inbounds\n";

            // 统计代理节点
            $proxyCount = 0;
            foreach ($json['outbounds'] as $outbound) {
                if (in_array($outbound['type'] ?? '', ['vmess', 'vless', 'trojan'])) {
                    $proxyCount++;
                }
            }
            echo "✓ 包含 {$proxyCount} 个代理节点\n\n";
        }

        // 保存到文件用于检查
        $singboxFile = __DIR__ . '/test_output_singbox.json';
        file_put_contents($singboxFile, $singboxResult);
        echo "✓ sing-box 配置已保存到: {$singboxFile}\n\n";

        // 显示前 80 行
        $lines = explode("\n", $singboxResult);
        echo "前 80 行预览:\n";
        echo str_repeat('-', 80) . "\n";
        echo implode("\n", array_slice($lines, 0, 80)) . "\n";
        echo str_repeat('-', 80) . "\n\n";

    } else {
        echo "✗ sing-box 配置生成失败\n";
    }
} catch (Exception $e) {
    echo "✗ sing-box 测试异常: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
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

        // 统计代理数量
        $proxyCount = preg_match_all('/^- name:/m', $clashResult);
        echo "✓ 包含 {$proxyCount} 个代理节点\n\n";

        // 保存到文件用于检查
        $clashFile = __DIR__ . '/test_output_clash.yaml';
        file_put_contents($clashFile, $clashResult);
        echo "✓ Clash 配置已保存到: {$clashFile}\n\n";

        // 显示前 100 行
        $lines = explode("\n", $clashResult);
        echo "前 100 行预览:\n";
        echo str_repeat('-', 80) . "\n";
        echo implode("\n", array_slice($lines, 0, 100)) . "\n";
        echo str_repeat('-', 80) . "\n\n";

    } else {
        echo "✗ Clash 配置生成失败\n";
    }
} catch (Exception $e) {
    echo "✗ Clash 测试异常: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
}

// 测试 Loon 格式
echo "\n========================================\n";
echo "测试 Loon 配置格式\n";
echo "========================================\n";

try {
    $generateDirectSubscribe = $reflection->getMethod('generateDirectSubscribe');
    $generateDirectSubscribe->setAccessible(true);

    $loonResult = $generateDirectSubscribe->invoke($controller, 'loon', $subscribe, []);

    if ($loonResult) {
        echo "✓ Loon 配置生成成功\n";
        echo "输出长度: " . strlen($loonResult) . " 字节\n\n";

        // 统计代理数量
        $proxyCount = preg_match_all('/^([A-Za-z0-9_-]+)\s*=/m', $loonResult, $matches);
        echo "✓ 包含约 " . floor($proxyCount / 5) . " 个代理节点\n\n";

        // 保存到文件用于检查
        $loonFile = __DIR__ . '/test_output_loon.conf';
        file_put_contents($loonFile, $loonResult);
        echo "✓ Loon 配置已保存到: {$loonFile}\n\n";

        // 显示前 80 行
        $lines = explode("\n", $loonResult);
        echo "前 80 行预览:\n";
        echo str_repeat('-', 80) . "\n";
        echo implode("\n", array_slice($lines, 0, 80)) . "\n";
        echo str_repeat('-', 80) . "\n\n";

    } else {
        echo "✗ Loon 配置生成失败\n";
    }
} catch (Exception $e) {
    echo "✗ Loon 测试异常: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
}

// 测试 Surfboard 格式
echo "\n========================================\n";
echo "测试 Surfboard 配置格式\n";
echo "========================================\n";

try {
    $generateDirectSubscribe = $reflection->getMethod('generateDirectSubscribe');
    $generateDirectSubscribe->setAccessible(true);

    $surfboardResult = $generateDirectSubscribe->invoke($controller, 'surfboard', $subscribe, []);

    if ($surfboardResult) {
        echo "✓ Surfboard 配置生成成功\n";
        echo "输出长度: " . strlen($surfboardResult) . " 字节\n\n";

        // 验证配置格式
        if (preg_match('/^#!MANAGED-CONFIG/', $surfboardResult)) {
            echo "✓ 包含 MANAGED-CONFIG 头部\n";
        }

        // 统计代理数量
        $proxyCount = preg_match_all('/^([A-Za-z0-9_-]+)\s*=/m', $surfboardResult, $matches);
        echo "✓ 包含约 " . floor($proxyCount / 5) . " 个代理节点\n\n";

        // 保存到文件用于检查
        $surfboardFile = __DIR__ . '/test_output_surfboard.conf';
        file_put_contents($surfboardFile, $surfboardResult);
        echo "✓ Surfboard 配置已保存到: {$surfboardFile}\n\n";

        // 显示前 80 行
        $lines = explode("\n", $surfboardResult);
        echo "前 80 行预览:\n";
        echo str_repeat('-', 80) . "\n";
        echo implode("\n", array_slice($lines, 0, 80)) . "\n";
        echo str_repeat('-', 80) . "\n\n";

    } else {
        echo "✗ Surfboard 配置生成失败\n";
    }
} catch (Exception $e) {
    echo "✗ Surfboard 测试异常: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
}

echo "\n========================================\n";
echo "测试完成\n";
echo "========================================\n";
