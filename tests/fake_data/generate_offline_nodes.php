<?php
/**
 * 生成离线假节点数据脚本 (使用 Laravel 框架)
 *
 * where: tests/fake_data/generate_offline_nodes.php
 * why: 生成 heartbeat_at 在一年前且 status=0 的节点, 用于测试后端离线节点功能
 * how: 使用 Laravel Eloquent 模型生成假节点, heartbeat_at 设置为一年前, status 设置为 0
 * must: 必须在 .env APP_ENV=test 时才能使用
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Models\SsNode;

// 检查环境
if (!app()->environment('test')) {
    die("Error: This script can only be run in test environment (APP_ENV=test).\n");
}

// 检查是否已存在测试节点
$existingTestNodes = SsNode::where('name', 'like', 'Offline Node %')->count();
if ($existingTestNodes > 0) {
    echo "Warning: Found {$existingTestNodes} existing offline test nodes.\n";
    echo "Do you want to delete them first? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    if (trim(strtolower($line)) === 'yes') {
        SsNode::where('name', 'like', 'Offline Node %')->delete();
        echo "Old offline test nodes deleted.\n";
    } else {
        echo "Operation cancelled.\n";
        exit(0);
    }
}

// 节点配置数据
$nodeTypes = [1, 2, 3, 4]; // 1-SS、2-V2、3-vless、4-trojan
$countryCodes = ['us', 'jp', 'kr', 'sg', 'hk', 'tw', 'de', 'gb', 'ca', 'au'];
$methods = ['aes-128-gcm', 'aes-256-gcm', 'chacha20-ietf-poly1305'];
$v2Nets = ['tcp', 'ws', 'grpc'];
$v2Types = ['none', 'http', 'srtp'];

// 生成假节点数据
$count = 10;
$now = date('Y-m-d H:i:s');
$oneYearAgo = date('Y-m-d H:i:s', strtotime('-1 year'));

echo "Generating {$count} offline fake nodes (status=0, heartbeat_at 1 year ago)...\n";

for ($i = 1; $i <= $count; $i++) {
    $type = $nodeTypes[array_rand($nodeTypes)];
    $countryCode = $countryCodes[array_rand($countryCodes)];
    $method = $methods[array_rand($methods)];

    $nodes[] = [
        'type' => $type,
        'name' => 'Offline Node ' . $i,
        'group_id' => 1,
        'country_code' => $countryCode,
        'server' => 'offline-node' . $i . '.test.example.com',
        'ip' => '192.168.3.' . (100 + $i),
        'ipv6' => '',
        'desc' => 'Offline test node ' . $i . ' (status=0, heartbeat 1 year ago)',
        'method' => $method,
        'protocol' => 'origin',
        'protocol_param' => '',
        'obfs' => 'plain',
        'obfs_param' => '',
        'traffic_rate' => 1.0,
        'bandwidth' => rand(100, 1000),
        'traffic' => 1099511627776, // 1TB
        'traffic_limit' => 1099511627776, // 1TB
        'monitor_url' => '',
        'is_subscribe' => 1,
        'is_nat' => 0,
        'is_transit' => 0,
        'ssh_port' => 22,
        'is_tcp_check' => 1,
        'compatible' => 0,
        'single' => 0,
        'sort' => $i,
        'status' => 0, // 关键: 设置为离线状态
        // V2Ray 配置
        'v2_alter_id' => 16,
        'v2_port' => 443 + $i,
        'v2_method' => 'aes-128-gcm',
        'v2_net' => $v2Nets[array_rand($v2Nets)],
        'v2_type' => $v2Types[array_rand($v2Types)],
        'v2_host' => '',
        'v2_path' => '/offline-node' . $i,
        'v2_tls' => rand(0, 2),
        'v2_insider_port' => 10550 + $i,
        'v2_outsider_port' => 443,
        // 新增字段
        'level' => rand(1, 4),
        'node_group' => rand(1, 2),
        'node_cost' => rand(1, 10),
        'node_online' => rand(0, 100),
        'node_onload' => rand(1, 10) / 10,
        'traffic_used' => rand(0, 500000000000),
        'traffic_left' => rand(500000000000, 1099511627776),
        'traffic_used_daily' => rand(0, 50000000000),
        'traffic_left_daily' => rand(50000000000, 1099511627776),
        'is_clone' => 0,
        'node_unlock' => '',
        'info' => 'Offline test node - status=0, last heartbeat 1 year ago',
        'v2_flow' => '',
        'v2_sni' => '',
        'v2_alpn' => '',
        'v2_encryption' => 'none',
        'node_uuid' => '',
        'heartbeat_at' => $oneYearAgo, // 关键: 设置为一年前
        'v2_mode' => '',
        'v2_servicename' => '',
        'v2_fp' => '',
        'v2_cdn' => '',
        'v2_cdn_ip' => '',
        'created_at' => $oneYearAgo, // 创建时间也设为一年前
        'updated_at' => $oneYearAgo, // 更新时间也设为一年前
    ];
}

// 批量插入节点 (使用 Laravel Eloquent)
SsNode::insert($nodes);
echo "Created {$count} offline fake nodes (status=0, heartbeat_at 1 year ago).\n";

echo "\n=== Summary ===\n";
echo "Total offline nodes created: {$count}\n";
echo "Node names: Offline Node 1 to Offline Node {$count}\n";
echo "Status: 0 (offline)\n";
echo "Heartbeat timestamp: {$oneYearAgo}\n";
echo "Countries: " . implode(', ', $countryCodes) . "\n";
echo "Types: SS, V2Ray, VLESS, Trojan\n";
echo "\nDone!\n";
