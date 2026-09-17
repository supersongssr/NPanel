<?php
/**
 * QA Test: 自动删除无心跳节点 DNS (AutoDeleteExpiredDns) — CDN 独立 Token + 可配阈值
 *
 * 背景:
 *   DNS 模块已独立: CDN 域名 (node_domain_pool 中 cdn:true + cf_token) 使用独立
 *   Cloudflare Token, 与全局 CLOUDFLARE_TOKEN 隔离 (防封号). 早期 AutoDeleteExpiredDns
 *   用单一全局 Token 删除所有记录, 导致 CDN 域名记录因鉴权失败而永远残留.
 *
 * 本测试 (全部 dry-run / 单元级, 不调用真实 CF API, 安全):
 *   1. resolveProvider: CDN 域名 → 独立 Token Provider; 普通域名 → 全局 Token Provider.
 *   2. 阈值优先级: --days > dns_expire_days 配置 > 默认 30 (DEFAULT_EXPIRE_DAYS).
 *   3. end-to-end dry-run: CDN 域名记录被遍历处理 (不跳过), 输出含其 fqdn.
 *
 * Run: podman exec php7-npanel php tests/test_auto_delete_expired_dns.php
 *      (需 APP_ENV=test; 设 QA_VERBOSE=1 打印 dry-run 输出)
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Components\DNS\CloudflareProvider;
use App\Console\Commands\AutoDeleteExpiredDns;
use App\Http\Models\Config;
use App\Http\Models\DnsRecord;
use App\Services\DnsRecordCleanupService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

if (env('APP_ENV') !== 'test') {
    fwrite(STDERR, "ABORT: APP_ENV must be 'test'\n");
    exit(2);
}

$passed = 0;
$failed = 0;
function assert_test($name, $cond, $detail = '')
{
    global $passed, $failed;
    if ($cond) { $passed++; echo "  PASS: {$name}\n"; }
    else { $failed++; echo "  FAIL: {$name}" . ($detail ? " -- {$detail}" : "") . "\n"; }
}

function readToken(CloudflareProvider $provider)
{
    $ref = new \ReflectionProperty(CloudflareProvider::class, 'token');
    $ref->setAccessible(true);
    return $ref->getValue($provider);
}

/** 运行 artisan 命令并返回输出 (捕获). */
function runDryRun($days = null)
{
    $tokens = ['--dry-run' => true];
    if ($days !== null) {
        $tokens['--days'] = (string) $days;
    }
    // 直接实例化命令 + ArrayInput (此 Laravel 版本的 Artisan::call 走 ArgvInput, 不支持关联选项)
    $command = app(AutoDeleteExpiredDns::class);
    $command->setLaravel(app());
    $output = new BufferedOutput();
    $command->run(new ArrayInput($tokens), $output);
    return $output->fetch();
}

function cutoffDays($output)
{
    if (preg_match('/Cutoff:.*\((\d+) days\)/', $output, $m)) {
        return (int) $m[1];
    }
    return null;
}

// 文件侧 dns_expire_days 有效值 (与 Helpers::systemConfig 同源: config.default.php ←
// .config.php 覆盖; DB 非白名单不参与), 供阈值断言对照
function config_file_dns_expire_days()
{
    $conf = \App\Components\Helpers::systemConfig();
    if (isset($conf['dns_expire_days']) && (int) $conf['dns_expire_days'] >= 1) {
        return (int) $conf['dns_expire_days'];
    }
    return (new \ReflectionClass(AutoDeleteExpiredDns::class))->getConstant('DEFAULT_EXPIRE_DAYS');
}

// 测试数据 ID 区间 (与 TestAutoDeleteExpiredDns 一致, 避开真实数据)
const TEST_NODE_ID = 9950;

function cleanupTestData()
{
    DnsRecord::where('node_id', TEST_NODE_ID)->delete();
    DB::table('ss_node')->where('id', TEST_NODE_ID)->delete();
}

echo "=== AutoDeleteExpiredDns QA (CDN 独立 Token + 可配阈值) ===\n\n";

$defaultDays = (new \ReflectionClass(AutoDeleteExpiredDns::class))->getConstant('DEFAULT_EXPIRE_DAYS');

// 备份可能存在的 dns_expire_days 配置, 测试中临时操作
$backupExpireRow = Config::where('name', 'dns_expire_days')->first();
$backupValue = $backupExpireRow ? $backupExpireRow->value : null;
Config::where('name', 'dns_expire_days')->delete();
cleanupTestData();

try {
    // ---------- 测试 1: resolveProvider Token 选择 ----------
    echo "[1] resolveProvider Token 选择 (cf_token 与 cdn 正交解耦)\n";
    $pool = json_decode(Config::where('name', 'node_domain_pool')->value('value'), true) ?: [];
    $cdnDomain = null;
    $cdnTokenExpected = null;
    $normalDomain = null;
    foreach ($pool as $dom => $meta) {
        if (!is_array($meta)) {
            continue;
        }
        if ($cdnDomain === null && !empty($meta['cdn']) && !empty($meta['cf_token'])) {
            $cdnDomain = $dom;
            $cdnTokenExpected = $meta['cf_token'];
        } elseif ($normalDomain === null && empty($meta['cdn'])) {
            $normalDomain = $dom;
        }
    }

    // 人造池额外验证: 非 CDN 域名配了 cf_token 也必须用独立 Token (跨账号场景)
    $xaccDomain = 'xacc.qa.test';
    $xaccTokenExpected = 'fake-xacc-token-' . uniqid();
    if ($cdnDomain === null) {
        // 域名池里没有 CDN 域名时, 用人造池验证逻辑
        $fakeToken = 'fake-cdn-token-' . uniqid();
        $cdnDomain = 'cdn.qa.test';
        $cdnTokenExpected = $fakeToken;
        $normalDomain = 'normal.qa.test';
        $pool = [
            $normalDomain => ['zone_id' => 'z-n', 'cdn' => false],
            $cdnDomain    => ['zone_id' => 'z-c', 'cdn' => true, 'cf_token' => $fakeToken],
            $xaccDomain   => ['zone_id' => 'z-x', 'cdn' => false, 'cf_token' => $xaccTokenExpected],
        ];
        echo "    (域名池无 CDN 域名, 用人造池验证)\n";
    } else {
        // 真实池里也补一条跨账号普通域名做验证
        $pool[$xaccDomain] = ['zone_id' => 'z-x', 'cdn' => false, 'cf_token' => $xaccTokenExpected];
        echo "    CDN 域名={$cdnDomain} 普通域名={$normalDomain}\n";
    }

    $cdnProv = DnsRecordCleanupService::resolveProvider($cdnDomain, $pool);
    $normalProv = DnsRecordCleanupService::resolveProvider($normalDomain, $pool);
    $missingProv = DnsRecordCleanupService::resolveProvider('not.in.pool', $pool);
    $xaccProv = DnsRecordCleanupService::resolveProvider($xaccDomain, $pool);
    $globalToken = env('CLOUDFLARE_TOKEN');

    assert_test('CDN 域名 resolveProvider 返回独立 Token', readToken($cdnProv) === $cdnTokenExpected,
        'got=' . substr((string) readToken($cdnProv), 0, 8));
    // 解耦验证: 非 CDN 域名配了 cf_token 也必须用独立 Token (跨 CF 账号场景)
    assert_test('非CDN 域名 (cf_token) resolveProvider 返回独立 Token', readToken($xaccProv) === $xaccTokenExpected,
        'got=' . substr((string) readToken($xaccProv), 0, 8));
    if ($globalToken) {
        assert_test('普通域名 resolveProvider 使用全局 Token', readToken($normalProv) === $globalToken);
    } else {
        assert_test('普通域名 resolveProvider 返回 Provider 实例', $normalProv instanceof CloudflareProvider);
    }
    assert_test('未配置域名 resolveProvider 返回全局 Provider', $missingProv instanceof CloudflareProvider);

    // ---------- 测试 2: 阈值优先级 (--days > dns_expire_days > 默认 30) ----------
    echo "\n[2] 阈值优先级\n";

    // 2a: 无 --days 且无 dns_expire_days 配置 → 默认值 (DEFAULT_EXPIRE_DAYS=30)
    $d = cutoffDays(runDryRun());
    assert_test("无参数时阈值=默认 {$defaultDays}", $d === $defaultDays, 'got=' . ($d ?? 'none'));

    // 2b: 写 DB config dns_expire_days=15, 无 --days → 阈值仍为文件配置值 30.
    //    Helpers::systemConfig 仅对 dynamicConfigKeys 白名单
    //    (traffic_record_group1/2) 读 DB, dns_expire_days 属静态配置,
    //    DB 行不生效 (防运维误改 DB 绕过 config.default.php/.config.php).
    Config::create(['name' => 'dns_expire_days', 'value' => '15']);
    $d = cutoffDays(runDryRun());
    $fileDays = (int) config_file_dns_expire_days();
    assert_test("DB 行 dns_expire_days=15 非白名单被忽略 → 文件配置 {$fileDays}", $d === $fileDays, 'got=' . ($d ?? 'none'));

    // 2c: 同时有配置 + --days=40 → --days 优先
    $d = cutoffDays(runDryRun(40));
    assert_test('--days=40 优先于 dns_expire_days=15', $d === 40, 'got=' . ($d ?? 'none'));

    // 2d: DB 行改非法值 (0) 同样被忽略 → 仍用文件配置值
    Config::where('name', 'dns_expire_days')->update(['value' => '0']);
    $d = cutoffDays(runDryRun());
    assert_test("DB 行 dns_expire_days=0 非法亦被忽略 → 文件配置 {$fileDays}", $d === $fileDays, 'got=' . ($d ?? 'none'));

    // 清掉配置, 用 --days 控制阈值做端到端
    Config::where('name', 'dns_expire_days')->delete();

    // ---------- 测试 3: end-to-end dry-run 处理 CDN 域名记录 ----------
    echo "\n[3] dry-run 遍历 CDN 域名记录 (不跳过)\n";

    // 构造一个 40 天前心跳的测试节点 + 一条 CDN 域名 DNS 记录
    $expiredHeartbeat = date('Y-m-d H:i:s', strtotime('-40 days'));
    DB::table('ss_node')->updateOrInsert(
        ['id' => TEST_NODE_ID],
        [
            'name'         => 'QA-EXPIRED-CDN-' . TEST_NODE_ID,
            'status'       => 0,
            'heartbeat_at' => $expiredHeartbeat,
            'created_at'   => $expiredHeartbeat,
            'updated_at'   => date('Y-m-d H:i:s'),
        ]
    );
    DnsRecord::where('node_id', TEST_NODE_ID)->delete();
    DnsRecord::create([
        'node_id'      => TEST_NODE_ID,
        'root_domain'  => $cdnDomain,
        'subdomain'    => 'n' . TEST_NODE_ID,
        'record_type'  => 'A',
        'ip_addr'      => '127.0.0.1',
        'cf_record_id' => 'qa-dryrun-cdn-' . TEST_NODE_ID,
        'proxied'      => false,
    ]);
    $cdnFqdn = 'n' . TEST_NODE_ID . '.' . $cdnDomain;

    $e2eOut = runDryRun(30);

    assert_test("dry-run 输出包含 CDN 记录 {$cdnFqdn}", strpos($e2eOut, $cdnFqdn) !== false,
        'CDN 记录未被遍历 (可能仍用全局 Token 被跳过)');
    assert_test('dry-run 未真实删除 (记录仍在)', DnsRecord::where('node_id', TEST_NODE_ID)->exists());

    if (getenv('QA_VERBOSE')) {
        echo "\n--- dry-run output (excerpt) ---\n";
        echo substr($e2eOut, 0, 2000) . "\n";
    }
} finally {
    // 恢复配置 + 清理测试数据
    Config::where('name', 'dns_expire_days')->delete();
    if ($backupValue !== null) {
        Config::create(['name' => 'dns_expire_days', 'value' => $backupValue]);
    }
    cleanupTestData();
}

echo "\n=== 结果: {$passed} 通过, {$failed} 失败 ===\n";
exit($failed > 0 ? 1 : 0);
