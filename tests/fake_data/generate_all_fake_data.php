<?php
/**
 * 生成所有假数据 (用户 + 节点)
 *
 * where: tests/fake_data/generate_all_fake_data.php
 * why: 便捷脚本,一次性生成所有测试数据
 * how: 调用用户和节点生成脚本
 * must: 必须在 .env APP_ENV=test 时才能使用
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 检查环境
if (env('APP_ENV') !== 'test') {
    die("Error: This script can only be run in test environment (APP_ENV=test).\n");
}

echo "=== Fake Data Generator ===\n";
echo "Environment: " . env('APP_ENV') . "\n\n";

$scriptDir = __DIR__;

// 生成假用户
echo "[1/2] Generating fake users...\n";
echo "============================\n";
require $scriptDir . '/generate_fake_users.php';

echo "\n";

// 生成假节点
echo "[2/2] Generating fake nodes...\n";
echo "============================\n";
require $scriptDir . '/generate_fake_nodes.php';

echo "\n";
echo "=== All Done! ===\n";
echo "All fake data has been generated successfully.\n";
