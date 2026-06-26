<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Components\DNS\CloudflareProvider;
use App\Components\Helpers;
use App\Http\Models\DnsRecord;
use App\Http\Models\SsNode;
use App\Services\DnsRecordCleanupService;

/**
 * 测试 AutoDeleteExpiredDns 功能的命令
 *
 * 测试场景：
 *   1. 构造测试数据（假过期节点 + DNS 记录）
 *   2. 执行 dry-run 验证预览
 *   3. 模拟 CF 返回 404（已远端删除）场景
 *   4. 模拟 CF 返回 429（限流）场景 — 本地记录必须保留
 *   5. 模拟无 zone_id 场景 — 本地记录必须保留
 *   6. 模拟心跳恢复场景 — 不应被清理
 *   7. 清理测试数据
 */
class TestAutoDeleteExpiredDns extends Command
{
    protected $signature = 'test:autoDeleteExpiredDns';
    protected $description = '模拟测试 AutoDeleteExpiredDns 各场景';

    // 测试用的固定 ID 区间,避免碰撞真实数据
    const TEST_NODE_MIN = 9900;
    const TEST_NODE_MAX = 9999;

    public function handle()
    {
        $this->info('========================================');
        $this->info('  AutoDeleteExpiredDns 模拟测试');
        $this->info('========================================');
        $this->info('');

        $passed = 0;
        $failed = 0;

        // 准备: 清理可能残留的测试数据
        $this->cleanupTestData();

        // ---------- 测试 1: DnsRecordCleanupService - CF 成功删除 ----------
        $this->info('--- 测试 1: CF 成功删除 → 本地记录应被删除 ---');
        if ($this->testCfSuccessDelete()) {
            $passed++;
            $this->info('  ✅ PASS');
        } else {
            $failed++;
            $this->error('  ❌ FAIL');
        }

        // ---------- 测试 2: DnsRecordCleanupService - CF 404 ----------
        $this->info('--- 测试 2: CF 返回 404 → 视为已删除,本地记录应被删除 ---');
        if ($this->testCf404Delete()) {
            $passed++;
            $this->info('  ✅ PASS');
        } else {
            $failed++;
            $this->error('  ❌ FAIL');
        }

        // ---------- 测试 3: DnsRecordCleanupService - CF 429 限流 ----------
        $this->info('--- 测试 3: CF 返回 429 限流 → 本地记录必须保留 ---');
        if ($this->testCf429Keep()) {
            $passed++;
            $this->info('  ✅ PASS');
        } else {
            $failed++;
            $this->error('  ❌ FAIL');
        }

        // ---------- 测试 4: 无 zone_id ----------
        $this->info('--- 测试 4: 无 zone_id → 本地记录必须保留 ---');
        if ($this->testNoZoneId()) {
            $passed++;
            $this->info('  ✅ PASS');
        } else {
            $failed++;
            $this->error('  ❌ FAIL');
        }

        // ---------- 测试 5: 无 cf_record_id ----------
        $this->info('--- 测试 5: 无 cf_record_id → 本地记录必须保留 ---');
        if ($this->testNoCfRecordId()) {
            $passed++;
            $this->info('  ✅ PASS');
        } else {
            $failed++;
            $this->error('  ❌ FAIL');
        }

        // ---------- 测试 6: 心跳恢复的节点不应被清理 ----------
        $this->info('--- 测试 6: 心跳恢复的节点 → 不应被清理 ---');
        if ($this->testHeartbeatRecovery()) {
            $passed++;
            $this->info('  ✅ PASS');
        } else {
            $failed++;
            $this->error('  ❌ FAIL');
        }

        // ---------- 测试 7: dry-run 不删除任何数据 ----------
        $this->info('--- 测试 7: dry-run → 不调用 CF API 也不删 DB ---');
        if ($this->testDryRun()) {
            $passed++;
            $this->info('  ✅ PASS');
        } else {
            $failed++;
            $this->error('  ❌ FAIL');
        }

        // ---------- 测试 8: 非法记录类型不处理 ----------
        $this->info('--- 测试 8: 非A/AAAA记录 → 跳过不处理 ---');
        if ($this->testNonTargetType()) {
            $passed++;
            $this->info('  ✅ PASS');
        } else {
            $failed++;
            $this->error('  ❌ FAIL');
        }

        // ---------- 测试 9: 独立 Token 的 CDN 域名必须用对应 Provider ----------
        $this->info('--- 测试 9: CDN 域名 (cdn:true + cf_token) → resolveProvider 返回独立 Token Provider ---');
        if ($this->testResolveProviderCdnToken()) {
            $passed++;
            $this->info('  ✅ PASS');
        } else {
            $failed++;
            $this->error('  ❌ FAIL');
        }

        // 清理
        $this->cleanupTestData();

        $this->info('');
        $this->info('========================================');
        $this->info("  结果: {$passed} 通过, {$failed} 失败");
        $this->info('========================================');

        return $failed > 0 ? 1 : 0;
    }

    // ========== 测试实现 ==========

    private function testCfSuccessDelete()
    {
        $nodeId = self::TEST_NODE_MIN + 1;
        $this->createTestNode($nodeId, 'expired');
        $record = $this->createTestDnsRecord($nodeId, 'n' . $nodeId, 'A', 'cf-success-' . $nodeId);

        $mock = $this->createMockProvider(array(
            'success' => true,
            'not_found' => false,
            'rate_limited' => false,
            'http_code' => 200,
            'error' => '',
        ));

        $service = new DnsRecordCleanupService();
        $domainPool = $this->getTestDomainPool();
        $result = $service->cleanupRecord($record, $mock, $domainPool, false, 'Test1');

        $localExists = DnsRecord::where('id', $record->id)->exists();
        // success=true → 本地记录应可安全删除, 由调用方删除
        // cleanupRecord 不直接 delete, 返回 success=true 后由调用方 delete
        $ok = $result['success'] === true && $result['action'] === 'deleted';
        // 模拟调用方行为: 删除本地
        if ($result['success']) {
            $record->delete();
        }
        $localExistsAfter = DnsRecord::where('id', $record->id)->exists();

        $this->line("    action={$result['action']} success=" . var_export($result['success'], true) . " local_exists_after=" . var_export($localExistsAfter, true));
        return $ok && !$localExistsAfter;
    }

    private function testCf404Delete()
    {
        $nodeId = self::TEST_NODE_MIN + 2;
        $this->createTestNode($nodeId, 'expired');
        $record = $this->createTestDnsRecord($nodeId, 'n' . $nodeId, 'AAAA', 'cf-404-' . $nodeId);

        $mock = $this->createMockProvider(array(
            'success' => true,
            'not_found' => true,
            'rate_limited' => false,
            'http_code' => 404,
            'error' => '',
        ));

        $service = new DnsRecordCleanupService();
        $domainPool = $this->getTestDomainPool();
        $result = $service->cleanupRecord($record, $mock, $domainPool, false, 'Test2');

        $ok = $result['success'] === true && $result['action'] === 'deleted_404';
        if ($result['success']) {
            $record->delete();
        }
        $localExistsAfter = DnsRecord::where('id', $record->id)->exists();

        $this->line("    action={$result['action']} success=" . var_export($result['success'], true) . " local_exists_after=" . var_export($localExistsAfter, true));
        return $ok && !$localExistsAfter;
    }

    private function testCf429Keep()
    {
        $nodeId = self::TEST_NODE_MIN + 3;
        $this->createTestNode($nodeId, 'expired');
        $record = $this->createTestDnsRecord($nodeId, 'n' . $nodeId, 'A', 'cf-429-' . $nodeId);

        $mock = $this->createMockProvider(array(
            'success' => false,
            'not_found' => false,
            'rate_limited' => true,
            'http_code' => 429,
            'error' => 'Rate limited by Cloudflare',
        ));

        $service = new DnsRecordCleanupService();
        $domainPool = $this->getTestDomainPool();
        $result = $service->cleanupRecord($record, $mock, $domainPool, false, 'Test3');

        // failure → 调用方不应删除本地记录
        $ok = $result['success'] === false && $result['action'] === 'failed_rate_limited';
        $localExists = DnsRecord::where('id', $record->id)->exists();

        $this->line("    action={$result['action']} success=" . var_export($result['success'], true) . " local_exists=" . var_export($localExists, true));
        return $ok && $localExists;
    }

    private function testNoZoneId()
    {
        $nodeId = self::TEST_NODE_MIN + 4;
        $this->createTestNode($nodeId, 'expired');
        $record = $this->createTestDnsRecord($nodeId, 'n' . $nodeId, 'A', 'cf-nozone-' . $nodeId, 'unknown-domain.com');

        $mock = $this->createMockProvider(array(
            'success' => true,
            'not_found' => false,
            'rate_limited' => false,
            'http_code' => 200,
            'error' => '',
        ));

        $service = new DnsRecordCleanupService();
        $domainPool = $this->getTestDomainPool(); // 不含 unknown-domain.com
        $result = $service->cleanupRecord($record, $mock, $domainPool, false, 'Test4');

        $ok = $result['success'] === false && $result['action'] === 'skipped_no_zone';
        $localExists = DnsRecord::where('id', $record->id)->exists();

        $this->line("    action={$result['action']} success=" . var_export($result['success'], true) . " local_exists=" . var_export($localExists, true));
        return $ok && $localExists;
    }

    private function testNoCfRecordId()
    {
        $nodeId = self::TEST_NODE_MIN + 5;
        $this->createTestNode($nodeId, 'expired');
        $record = $this->createTestDnsRecord($nodeId, 'n' . $nodeId, 'A', '');

        $mock = $this->createMockProvider(array(
            'success' => true,
            'not_found' => false,
            'rate_limited' => false,
            'http_code' => 200,
            'error' => '',
        ));

        $service = new DnsRecordCleanupService();
        $domainPool = $this->getTestDomainPool();
        $result = $service->cleanupRecord($record, $mock, $domainPool, false, 'Test5');

        $ok = $result['success'] === false && $result['action'] === 'skipped_no_cf_id';
        $localExists = DnsRecord::where('id', $record->id)->exists();

        $this->line("    action={$result['action']} success=" . var_export($result['success'], true) . " local_exists=" . var_export($localExists, true));
        return $ok && $localExists;
    }

    private function testHeartbeatRecovery()
    {
        $nodeId = self::TEST_NODE_MIN + 6;
        // 创建一个"已恢复"的节点(heartbeat_at = now)
        $this->createTestNode($nodeId, 'active');
        $record = $this->createTestDnsRecord($nodeId, 'n' . $nodeId, 'A', 'cf-alive-' . $nodeId);

        // 用 artisan 命令的 chunk 逻辑来验证: 恢复心跳的节点不会被处理
        $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));

        // 检查此节点是否会被过期查询选中
        $selected = SsNode::where('id', $nodeId)
            ->where(function ($query) use ($cutoff) {
                $query->where('heartbeat_at', '<', $cutoff)
                      ->orWhere(function ($q) use ($cutoff) {
                          $q->whereNull('heartbeat_at')
                            ->where('created_at', '<', $cutoff);
                      });
            })
            ->exists();

        $localExists = DnsRecord::where('id', $record->id)->exists();

        $this->line("    node_selected_by_expired_query=" . var_export($selected, true) . " local_exists=" . var_export($localExists, true));
        return !$selected && $localExists;
    }

    private function testDryRun()
    {
        $nodeId = self::TEST_NODE_MIN + 7;
        $this->createTestNode($nodeId, 'expired');
        $record = $this->createTestDnsRecord($nodeId, 'n' . $nodeId, 'A', 'cf-dryrun-' . $nodeId);

        // dry-run: Mock 不应被调用, 但即使传入了也不会被实际调用
        $service = new DnsRecordCleanupService();
        $domainPool = $this->getTestDomainPool();

        // 用一个会失败的 mock, 如果 dry-run 没跳过就会导致 fail
        $mock = $this->createMockProvider(array(
            'success' => false,
            'not_found' => false,
            'rate_limited' => false,
            'http_code' => 500,
            'error' => 'Should not be called in dry-run',
        ));

        $result = $service->cleanupRecord($record, $mock, $domainPool, true, 'Test7');

        $ok = $result['success'] === true && $result['action'] === 'will_delete';
        $localExists = DnsRecord::where('id', $record->id)->exists();

        $this->line("    action={$result['action']} success=" . var_export($result['success'], true) . " local_exists=" . var_export($localExists, true));
        return $ok && $localExists;
    }

    private function testNonTargetType()
    {
        $nodeId = self::TEST_NODE_MIN + 8;
        $this->createTestNode($nodeId, 'expired');
        $record = $this->createTestDnsRecord($nodeId, 'txt' . $nodeId, 'TXT', 'cf-txt-' . $nodeId);

        $mock = $this->createMockProvider(array(
            'success' => true,
            'not_found' => false,
            'rate_limited' => false,
            'http_code' => 200,
            'error' => '',
        ));

        $service = new DnsRecordCleanupService();
        $domainPool = $this->getTestDomainPool();
        $result = $service->cleanupRecord($record, $mock, $domainPool, false, 'Test8');

        $ok = $result['success'] === false && $result['action'] === 'skipped_type';
        $localExists = DnsRecord::where('id', $record->id)->exists();

        $this->line("    action={$result['action']} success=" . var_export($result['success'], true) . " local_exists=" . var_export($localExists, true));
        return $ok && $localExists;
    }

    // ========== 辅助方法 ==========

    private function createTestNode($nodeId, $type)
    {
        $heartbeatAt = null;
        $createdAt = date('Y-m-d H:i:s', strtotime('-31 days'));

        if ($type === 'active') {
            $heartbeatAt = date('Y-m-d H:i:s');
            $createdAt = date('Y-m-d H:i:s');
        }
        // expired: heartbeat_at = null, created_at = 31 days ago

        DB::table('ss_node')->updateOrInsert(
            array('id' => $nodeId),
            array(
                'name' => 'TEST-NODE-' . $nodeId,
                'status' => 0,
                'heartbeat_at' => $heartbeatAt,
                'created_at' => $createdAt,
                'updated_at' => date('Y-m-d H:i:s'),
            )
        );
    }

    private function createTestDnsRecord($nodeId, $subdomain, $type, $cfRecordId, $rootDomain = null)
    {
        if ($rootDomain === null) {
            $rootDomain = 'ssmail.win';
        }

        // Delete any existing test record first
        DnsRecord::where('node_id', $nodeId)->where('subdomain', $subdomain)->delete();

        return DnsRecord::create(array(
            'node_id' => $nodeId,
            'root_domain' => $rootDomain,
            'subdomain' => $subdomain,
            'record_type' => $type,
            'ip_addr' => '127.0.0.1',
            'cf_record_id' => $cfRecordId,
            'proxied' => false,
        ));
    }

    private function getTestDomainPool()
    {
        return array(
            'ssmail.win' => array(
                'zone_id' => 'test-zone-id-ssmail',
                'records_limit' => 1000,
            ),
            'freessr.bid' => array(
                'zone_id' => 'test-zone-id-freessr',
                'records_limit' => 180,
            ),
        );
    }

    /**
     * Create a mock CloudflareProvider that returns a fixed status from deleteRecordWithStatus.
     */
    private function createMockProvider($fixedStatus)
    {
        return new class($fixedStatus) extends CloudflareProvider {
            private $fixedStatus;

            public function __construct($fixedStatus)
            {
                // Do NOT call parent constructor (reads env)
                $this->fixedStatus = $fixedStatus;
                $this->token = 'mock-token';
                $this->email = '';
                $this->apiKey = '';
            }

            public function deleteRecordWithStatus($cfRecordId, $zoneId = null)
            {
                return $this->fixedStatus;
            }
        };
    }

    /**
     * 测试 DnsRecordCleanupService::resolveProvider():
     *   - CDN 域名 (cdn:true + cf_token) → 返回带独立 Token 的 Provider
     *   - 普通域名 → 返回使用全局 Token 的 Provider
     *   - 未配置域名 → 返回全局 Provider
     *
     * 这验证了 AutoDeleteExpiredDns / DnsSyncer 删除 CDN 域名记录时会用对 Token.
     */
    private function testResolveProviderCdnToken()
    {
        $cdnToken = 'independent-cdn-token-' . uniqid();
        $pool = array(
            'normal.com' => array('zone_id' => 'zone-normal', 'cdn' => false),
            'cdn.xyz'    => array('zone_id' => 'zone-cdn', 'cdn' => true, 'cf_token' => $cdnToken),
        );

        $cdnProvider = DnsRecordCleanupService::resolveProvider('cdn.xyz', $pool);
        $normalProvider = DnsRecordCleanupService::resolveProvider('normal.com', $pool);
        $missingProvider = DnsRecordCleanupService::resolveProvider('not.in.pool', $pool);

        $cdnTokenActual = $this->readProviderToken($cdnProvider);
        $normalTokenActual = $this->readProviderToken($normalProvider);
        $globalToken = env('CLOUDFLARE_TOKEN');

        $cdnMatched = $cdnTokenActual === $cdnToken;
        $normalIsGlobal = ($globalToken !== false && $normalTokenActual === $globalToken);
        $missingOk = ($missingProvider instanceof CloudflareProvider);

        $this->line('    cdn_token_matched=' . var_export($cdnMatched, true)
            . ' normal_uses_global=' . var_export($normalIsGlobal, true)
            . ' missing_is_provider=' . var_export($missingOk, true));

        return $cdnMatched && $normalIsGlobal && $missingOk;
    }

    /**
     * 读取 CloudflareProvider 的 protected $token (测试用反射).
     */
    private function readProviderToken(CloudflareProvider $provider)
    {
        $ref = new \ReflectionProperty(CloudflareProvider::class, 'token');
        $ref->setAccessible(true);
        return $ref->getValue($provider);
    }

    private function cleanupTestData()
    {
        DnsRecord::where('node_id', '>=', self::TEST_NODE_MIN)
            ->where('node_id', '<=', self::TEST_NODE_MAX)
            ->delete();

        DB::table('ss_node')
            ->where('id', '>=', self::TEST_NODE_MIN)
            ->where('id', '<=', self::TEST_NODE_MAX)
            ->delete();
    }
}
