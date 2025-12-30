<?php
/**
 * 生成假用户数据脚本 (使用 Laravel 框架)
 *
 * where: tests/fake_data/generate_fake_users.php
 * why: 测试站点需要假用户数据
 * how: 使用 Laravel Eloquent 模型和 Hash facade 生成假用户
 * must: 必须在 .env APP_ENV=test 时才能使用
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Models\User;
use App\Http\Models\UserSubscribe;
use Illuminate\Support\Facades\Hash;

// 检查环境
if (app()->environment('production')) {
    die("Error: This script can only be run in test environment (APP_ENV=test).\n");
}

// 检查是否已存在测试用户
$existingTestUsers = User::where('username', 'like', 'test_user_%')->count();
if ($existingTestUsers > 0) {
    echo "Warning: Found {$existingTestUsers} existing test users.\n";
    echo "Do you want to delete them first? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    if (trim(strtolower($line)) === 'yes') {
        User::where('username', 'like', 'test_user_%')->delete();
        UserSubscribe::whereHas('user', function($q) {
            $q->where('username', 'like', 'test_user_%');
        })->delete();
        echo "Old test users deleted.\n";
    } else {
        echo "Operation cancelled.\n";
        exit(0);
    }
}

// 生成假用户数据
$count = 100;
$users = [];
$now = date('Y-m-d H:i:s');

echo "Generating {$count} fake users...\n";

for ($i = 1; $i <= $count; $i++) {
    $username = 'test_user_' . str_pad($i, 3, '0', STR_PAD_LEFT);
    $port = 10000 + $i;
    $passwd = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
    $vmessId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $users[] = [
        'username' => $username,
        'password' => Hash::make('password123'), // 使用 Laravel Hash facade
        'port' => $port,
        'passwd' => $passwd,
        'vmess_id' => $vmessId,
        'transfer_enable' => 1099511627776, // 1TB
        'u' => rand(0, 10000000000),
        'd' => rand(0, 10000000000),
        't' => rand(0, time()),
        'enable' => 1,
        'method' => 'aes-256-gcm',
        'protocol' => 'origin',
        'obfs' => 'plain',
        'speed_limit_per_con' => 10737418240, // 10GB
        'speed_limit_per_user' => 10737418240, // 10GB
        'gender' => rand(0, 1),
        'wechat' => '',
        'qq' => '',
        'usage' => rand(1, 4),
        'pay_way' => rand(0, 4),
        'balance' => rand(0, 100000), // 0-1000元 (单位分)
        'enable_time' => date('Y-m-d', strtotime('-' . rand(1, 365) . ' days')),
        'expire_time' => date('Y-m-d', strtotime('+' . rand(30, 365) . ' days')),
        'ban_time' => 0,
        'remark' => 'Test user ' . $i,
        'level' => rand(1, 4),
        'is_admin' => 0,
        'reg_ip' => '127.0.0.1',
        'last_login' => rand(0, time()),
        'referral_uid' => 0,
        'traffic_reset_day' => 0,
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ];
}

// 批量插入用户 (使用 Laravel Eloquent)
User::insert($users);
echo "Created {$count} fake users.\n";

// 为每个用户创建订阅记录
$users = User::where('username', 'like', 'test_user_%')->get();
$subscribes = [];
foreach ($users as $user) {
    $subscribes[] = [
        'user_id' => $user->id,
        'code' => substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 5),
        'times' => rand(0, 100),
        'times_today' => rand(0, 10),
        'status' => 1,
        'ban_time' => 0,
        'ban_desc' => '',
        'created_at' => $now,
        'updated_at' => $now,
    ];
}

UserSubscribe::insert($subscribes);
echo "Created " . count($subscribes) . " fake user subscribes.\n";

echo "\n=== Summary ===\n";
echo "Total fake users created: {$count}\n";
echo "Total subscribes created: " . count($subscribes) . "\n";
echo "Username format: test_user_001 to test_user_{$count}\n";
echo "Default password: password123\n";
echo "\nDone!\n";
