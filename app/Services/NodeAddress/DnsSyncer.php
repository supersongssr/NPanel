<?php

namespace App\Services\NodeAddress;

use Illuminate\Support\Facades\Log;
use App\Components\Helpers;
use App\Components\DNS\CloudflareProvider;
use App\Http\Models\SsNode;
use App\Http\Models\DnsRecord;
use App\Services\DnsRecordCleanupService;

/**
 * DNS 记录同步模块 (Cloudflare).
 *
 * ──────────────────────────────────────────────────────────────────
 * 与 NodeAddressService 同属"节点地址 + DNS 解析"统一模块.
 * 职责: 在 Cloudflare 上创建 / 更新 / 删除 A/AAAA 记录, 使 host 解析到节点 IP.
 *
 * 行为 (无全局开关, resolve_dns 端点统一处理 address + host):
 *   - 主节点 (is_clone == 0): 连接地址恒为 IP → 删除残留记录后跳过 (不解析).
 *   - ipv6 节点 (server 含 `ipv6n`): 直连 ipv6 → 删除残留记录后跳过 (不解析).
 *   - clone ipv4 节点 (server 不含 ipv6n): 创建 A 记录解析连接域名 (n{cloneid}.domain) 到节点 IPv4.
 *
 * 即整个集群只有 clone 的 ipv4 节点需要 DNS 记录, 主节点 + ipv6 节点直连, 降低
 * DNS 解析数量. 节点是 ipv4 还是 ipv6 由 register 写入的 server 前缀判定 (n/ipv6n,
 * 不靠 ip 缺失), 每个节点同时存储物理节点的 ip+ipv6. 只需域名能解析出节点 IP 即可, 不校验
 * DNS 记录的 root domain 是否与 TLS host (clusterHost) 一致 (连接域名与 TLS host 解耦, domain-fronting).
 *
 * register 不调用本模块 (register 不碰 DNS, address 原生 = IP); 由 resolve_dns 端点 /
 * 定时清理调用. host/sni (clusterHost) 仅作 TLS 身份, 不创建 DNS 记录.
 *
 * 入口: syncCluster($mainNodeId, $force)  →  返回 [results, stats].
 * ──────────────────────────────────────────────────────────────────
 */
class DnsSyncer
{
    const DEFAULT_RECORDS_LIMIT = 180;

    /** 单节点最长处理秒数 (日志阈值). */
    private $maxPerNode = 60;

    /** 整个集群全局超时秒数. */
    private $globalTimeout = 90;

    /** @var array parsed domain pool cache */
    private $parsedPool;

    /** @var CloudflareProvider[] 按根域名缓存的 provider (独立 token) */
    private $providerCache = [];

    /**
     * 同步整个集群 (主节点 + 所有 clone) 的 DNS 记录 (resolve_dns 端点统一入口).
     *
     * 无全局开关: 仅 clone ipv4 节点 (server 不含 ipv6n) 创建 A 记录, 主节点 + ipv6 节点跳过 (直连).
     *
     * @param  int  $mainNodeId 主节点 ID
     * @param  bool $force      强制重新对账 (忽略本地缓存)
     * @return array ['results' => [...], 'stats' => [...], 'elapsed_s' => float]
     */
    public function syncCluster($mainNodeId, $force = false)
    {
        set_time_limit(120);
        $startTime = microtime(true);

        $mainNode = SsNode::find($mainNodeId);
        if (!$mainNode) {
            return $this->errorResult('Node not found');
        }

        $nodeIdsRaw = $mainNode->node_ids ?: (string) $mainNodeId;
        $nodeIds = array_map('intval', array_filter(explode(',', $nodeIdsRaw), 'strlen'));
        $nodeIds = array_values(array_unique($nodeIds));

        if (empty($nodeIds)) {
            return $this->errorResult('No node_ids found');
        }

        $this->parsedPool = Helpers::parseDomainPool(Helpers::systemConfig());

        Log::debug('[DnsSyncer] syncCluster start', [
            'main_node_id' => (int) $mainNodeId,
            'node_ids' => $nodeIds,
            'force' => $force,
        ]);

        $globalDeadline = microtime(true) + $this->globalTimeout;
        $stats = $this->emptyStats();
        $results = [];

        foreach ($nodeIds as $idx => $id) {
            if (microtime(true) > $globalDeadline) {
                $remaining = array_slice($nodeIds, $idx);
                Log::warning('[DnsSyncer] global timeout, aborting', ['remaining' => $remaining]);
                foreach ($remaining as $skipId) {
                    $results[] = ['action' => 'skipped_timeout', 'success' => false, 'node_id' => $skipId, 'message' => 'Global timeout'];
                    $stats['skipped']++;
                }
                break;
            }
            $this->syncNode($id, $force, $results, $stats);
        }

        $elapsed = round(microtime(true) - $startTime, 3);
        Log::info('[DnsSyncer] syncCluster complete', [
            'main_node_id' => (int) $mainNodeId,
            'total_nodes' => count($nodeIds),
            'stats' => $stats,
            'elapsed_s' => $elapsed,
        ]);

        return [
            'status' => $this->allSuccess($results) ? 'success' : 'error',
            'results' => $results,
            'stats' => $stats,
            'elapsed_s' => $elapsed,
            'message' => $this->allSuccess($results) ? 'DNS cluster synchronized' : 'Some DNS operations failed',
        ];
    }

    /**
     * 同步单个节点的 DNS 记录.
     *
     * 规则 (无全局开关):
     *   - 主节点 (is_clone=0) / ipv6 节点 (server 含 ipv6n): 直连 (IP / ipv6), 删除残留记录后跳过.
     *   - clone ipv4 节点 (server 不含 ipv6n): 创建 A 记录解析连接域名 (n{cloneid}.domain) 到节点 IPv4.
     *
     * @param int    $nodeId
     * @param bool   $force
     * @param array  $results 输出参数, 追加结果
     * @param array  $stats   输出参数, 累加统计
     */
    private function syncNode($nodeId, $force, array &$results, array &$stats)
    {
        $nodeStart = microtime(true);
        $node = SsNode::find($nodeId);
        if (!$node) {
            Log::warning("[DnsSyncer] node {$nodeId} not found, skipping");
            $stats['skipped']++;
            return;
        }

        // ipv6 节点 (server 含 ipv6n): 始终直连 ipv6, 不做 host 解析 → 无需 DNS 记录.
        // (清理可能残留的旧记录后跳过; ipv6 节点不走 host, 域名解析对其无意义)
        if (NodeAddressService::isIpv6Node($node)) {
            $this->deleteNodeRecords($node, $results, $stats);
            Log::debug("[DnsSyncer] node {$nodeId} ipv6-only → direct connect, no DNS record");
            return;
        }

        // 主节点 (is_clone == 0): host/sni 复用机制下连接地址恒为 IP, 不创建 DNS 记录.
        // (清理可能残留的旧记录后跳过; 仅 clone 的 ipv4 节点转域名, 降低 DNS 解析数量)
        if (NodeAddressService::isMainNode($node)) {
            $this->deleteNodeRecords($node, $results, $stats);
            Log::debug("[DnsSyncer] node {$nodeId} main (is_clone=0) → direct IP, no DNS record");
            return;
        }

        // clone ipv4 节点 (server 不含 ipv6n): 创建 A 记录解析连接域名到节点 IPv4.
        // host/sni (clusterHost) 仅作 TLS 身份, 不创建 DNS 记录 (连接域名与 TLS host 解耦).
        if (!$node->server) {
            Log::warning("[DnsSyncer] node {$nodeId} empty server");
            $stats['skipped']++;
            return;
        }
        $serverParts = $this->parseServerField($node->server);
        $targetRootDomain = $serverParts ? $serverParts['root_domain'] : '';
        if (!$targetRootDomain) {
            Log::warning("[DnsSyncer] invalid server for node {$nodeId}", ['server' => $node->server]);
            $stats['skipped']++;
            return;
        }
        $poolMeta = $this->parsedPool['domainPool'][$targetRootDomain] ?? null;
        $zoneId = $this->getZoneId($poolMeta ?: [], $targetRootDomain);
        if (!$zoneId) {
            $results[] = ['action' => 'skipped_no_zone', 'success' => false, 'node_id' => $nodeId, 'type' => null];
            $stats['failed']++;
            return;
        }
        if (!$node->ip) {
            Log::debug("[DnsSyncer] clone node {$nodeId} has no IPv4, skip");
            $stats['skipped']++;
            return;
        }

        // proxied: 取域名池配置 (默认 false = 灰云直连源站).
        $proxied = (bool) (is_array($poolMeta) && !empty($poolMeta['proxied']));

        $provider = $this->providerForDomain($targetRootDomain);

        Log::debug("[DnsSyncer] syncNode {$nodeId}", [
            'root_domain' => $targetRootDomain,
            'proxied' => $proxied,
            'has_independent_token' => is_array($poolMeta) && !empty($poolMeta['cf_token']),
        ]);

        $blueprints = $this->buildBlueprints($node, $targetRootDomain, $zoneId, $proxied);
        foreach ($blueprints as $blueprint) {
            $bpResult = $this->reconcileThreeWay($node->id, $blueprint, $provider, $force);
            $results[] = $bpResult;
            $this->tally($bpResult, $stats);
        }

        // 清理孤儿记录 (类型不匹配)
        $this->cleanupOrphans($node, $blueprints, $results, $stats);

        $nodeElapsed = round(microtime(true) - $nodeStart, 3);
        if ($nodeElapsed > $this->maxPerNode) {
            Log::warning("[DnsSyncer] node {$nodeId} slow, took {$nodeElapsed}s");
        }
    }

    /**
     * 删除节点所有 DNS 记录 (Cloudflare 远端 + 本地).
     * 用于主节点 / ipv6 节点 (直连, 无需 DNS 记录) 清理残留, 以及节点回收.
     */
    private function deleteNodeRecords($node, array &$results, array &$stats)
    {
        $nid = $node->id;
        $records = DnsRecord::where('node_id', $nid)->get();
        if ($records->isEmpty()) {
            $stats['skipped']++;
            return;
        }
        foreach ($records as $record) {
            $fqdn = $record->subdomain . '.' . $record->root_domain;
            if (!empty($record->cf_record_id)) {
                $zoneId = $this->getZoneId(
                    $this->parsedPool['domainPool'][$record->root_domain] ?? [],
                    $record->root_domain
                );
                if ($zoneId) {
                    $provider = $this->providerForDomain($record->root_domain);
                    if (!$provider->deleteRecord($record->cf_record_id, $zoneId)) {
                        Log::warning("[DnsSyncer] CF delete failed for node {$nid}", ['fqdn' => $fqdn, 'cf_id' => $record->cf_record_id]);
                        $stats['failed']++;
                        continue; // 保留本地记录等待重试
                    }
                } else {
                    Log::warning("[DnsSyncer] no zone_id for {$record->root_domain}, CF record may leak", ['node_id' => $nid]);
                }
            }
            $record->delete();
            $stats['deleted']++;
            $results[] = ['action' => 'deleted_ip_mode', 'success' => true, 'node_id' => $nid, 'type' => $record->record_type, 'fqdn' => $fqdn, 'proxied' => false];
        }
    }

    /**
     * 构建 A 记录蓝图 (clone ipv4 节点).
     *
     * 主节点 / ipv6 节点在 syncNode 已提前跳过 (不建记录), 此处只会为
     * clone ipv4 节点 (server 不含 ipv6n) 构建 A 记录.
     */
    private function buildBlueprints($node, $rootDomain, $zoneId, $proxied)
    {
        $blueprints = [];
        if ($node->ip) {
            $blueprints[] = [
                'type' => 'A', 'subdomain' => 'n' . $node->id,
                'content' => $node->ip, 'root_domain' => $rootDomain,
                'zone_id' => $zoneId, 'proxied' => $proxied,
            ];
        }
        return $blueprints;
    }

    /**
     * 清理类型不匹配的孤儿记录.
     */
    private function cleanupOrphans($node, array $blueprints, array &$results, array &$stats)
    {
        $expectedTypes = array_column($blueprints, 'type');
        $orphans = DnsRecord::where('node_id', $node->id)
            ->whereNotIn('record_type', $expectedTypes)
            ->get();
        foreach ($orphans as $orphan) {
            $orphanProvider = $this->providerForDomain($orphan->root_domain);
            $orphanZoneId = $this->getZoneId(
                $this->parsedPool['domainPool'][$orphan->root_domain] ?? [],
                $orphan->root_domain
            );
            if ($orphanZoneId) {
                $orphanProvider->deleteRecord($orphan->cf_record_id, $orphanZoneId);
            }
            $orphan->delete();
            $results[] = ['action' => 'deleted_orphan', 'success' => true, 'node_id' => $node->id, 'type' => $orphan->record_type];
            $stats['deleted']++;
        }
    }

    /**
     * 三方对账 (本地 / Cloudflare / 蓝图), 创建或更新记录.
     */
    private function reconcileThreeWay($nodeId, $blueprint, CloudflareProvider $provider, $force = false)
    {
        $fqdn = $blueprint['subdomain'] . '.' . $blueprint['root_domain'];
        $expectedProxied = $blueprint['proxied'] ?? false;

        $localRecord = DnsRecord::where('node_id', $nodeId)
            ->where('record_type', $blueprint['type'])
            ->first();

        // Scene A: 缓存命中 (无变化)
        if (!$force && $localRecord) {
            $isMatch = $localRecord->subdomain === $blueprint['subdomain']
                && $localRecord->root_domain === $blueprint['root_domain']
                && $localRecord->ip_addr === $blueprint['content']
                && (bool) $localRecord->proxied === $expectedProxied;
            if ($isMatch) {
                return ['action' => 'no_change', 'success' => true, 'node_id' => $nodeId, 'type' => $blueprint['type'], 'fqdn' => $fqdn, 'proxied' => $expectedProxied, 'message' => 'Cache hit'];
            }
        }

        // Scene B: 域名变化 → 删旧重建
        if ($localRecord && ($localRecord->subdomain !== $blueprint['subdomain'] || $localRecord->root_domain !== $blueprint['root_domain'])) {
            $oldZoneId = $this->getZoneIdForRecord($localRecord);
            if ($oldZoneId) {
                $oldProvider = $this->providerForDomain($localRecord->root_domain);
                $oldProvider->deleteRecord($localRecord->cf_record_id, $oldZoneId);
            }
            $localRecord->delete();
            $localRecord = null;
        }

        // Scene C: 创建
        if (!$localRecord) {
            $cfResult = $provider->createRecord(
                $blueprint['root_domain'], $blueprint['subdomain'], $blueprint['content'],
                $blueprint['type'], $blueprint['zone_id'], $expectedProxied
            );
            if (!$cfResult) {
                // 采纳已存在的 CF 记录 (本地缓存丢失但 CF 还在)
                $existingCf = $provider->getRecordByNameAndType(
                    $blueprint['root_domain'], $blueprint['subdomain'], $blueprint['type'], $blueprint['zone_id']
                );
                if ($existingCf && !empty($existingCf['id'])) {
                    $updateOk = $provider->updateRecordById(
                        $existingCf['id'], $blueprint['root_domain'], $blueprint['subdomain'],
                        $blueprint['content'], $blueprint['type'], $blueprint['zone_id'], $expectedProxied
                    );
                    if ($updateOk) {
                        DnsRecord::create([
                            'node_id' => $nodeId, 'root_domain' => $blueprint['root_domain'],
                            'subdomain' => $blueprint['subdomain'], 'record_type' => $blueprint['type'],
                            'ip_addr' => $blueprint['content'], 'cf_record_id' => $existingCf['id'],
                            'proxied' => $expectedProxied,
                        ]);
                        return ['action' => 'adopted', 'success' => true, 'node_id' => $nodeId, 'type' => $blueprint['type'], 'fqdn' => $fqdn, 'cf_id' => $existingCf['id'], 'proxied' => $expectedProxied];
                    }
                }
                Log::error('[DnsSyncer] create failed', ['node_id' => $nodeId, 'fqdn' => $fqdn]);
                return ['action' => 'create_failed', 'success' => false, 'node_id' => $nodeId, 'type' => $blueprint['type'], 'fqdn' => $fqdn];
            }
            if (empty($cfResult['id'])) {
                return ['action' => 'create_failed', 'success' => false, 'node_id' => $nodeId, 'type' => $blueprint['type'], 'fqdn' => $fqdn, 'message' => 'CF no record ID'];
            }
            DnsRecord::create([
                'node_id' => $nodeId, 'root_domain' => $blueprint['root_domain'],
                'subdomain' => $blueprint['subdomain'], 'record_type' => $blueprint['type'],
                'ip_addr' => $blueprint['content'], 'cf_record_id' => $cfResult['id'],
                'proxied' => $expectedProxied,
            ]);
            return ['action' => 'created', 'success' => true, 'node_id' => $nodeId, 'type' => $blueprint['type'], 'fqdn' => $fqdn, 'cf_id' => $cfResult['id'], 'proxied' => $expectedProxied];
        }

        // Scene D: IP / proxied 变化 → 更新
        if (empty($localRecord->cf_record_id)) {
            $localRecord->delete();
            return ['action' => 'update_failed', 'success' => false, 'node_id' => $nodeId, 'type' => $blueprint['type'], 'fqdn' => $fqdn, 'message' => 'Missing cf_record_id'];
        }
        $ipChanged = $localRecord->ip_addr !== $blueprint['content'];
        $proxiedChanged = (bool) $localRecord->proxied !== $expectedProxied;
        $cfResult = $provider->updateRecordById(
            $localRecord->cf_record_id, $blueprint['root_domain'], $blueprint['subdomain'],
            $blueprint['content'], $blueprint['type'], $blueprint['zone_id'], $expectedProxied
        );
        if (!$cfResult) {
            $localRecord->delete();
            return ['action' => 'update_failed', 'success' => false, 'node_id' => $nodeId, 'type' => $blueprint['type'], 'fqdn' => $fqdn];
        }
        $localRecord->ip_addr = $blueprint['content'];
        $localRecord->proxied = $expectedProxied;
        $localRecord->save();
        $actionLabel = ($proxiedChanged && !$ipChanged) ? 'updated_proxied' : 'updated_ip';
        return ['action' => $actionLabel, 'success' => true, 'node_id' => $nodeId, 'type' => $blueprint['type'], 'fqdn' => $fqdn, 'proxied' => $expectedProxied];
    }

    /**
     * 安全清理单个节点的所有 DNS 记录 (节点回收时用).
     * CDN 记录用独立 token 删除, 避免远端孤儿.
     *
     * @param int    $nodeId
     * @param string $context 日志标签
     */
    public function cleanupNodeRecords($nodeId, $context = 'DnsSyncer')
    {
        try {
            $this->parsedPool = Helpers::parseDomainPool(Helpers::systemConfig());
            $domainPool = $this->parsedPool['domainPool'];

            if (empty($domainPool)) {
                DnsRecord::where('node_id', $nodeId)->delete();
                return;
            }
            $records = DnsRecord::where('node_id', $nodeId)
                ->whereIn('record_type', ['A', 'AAAA'])
                ->get();
            if ($records->isEmpty()) {
                return;
            }

            $cleanupService = new DnsRecordCleanupService();
            $failed = 0;
            foreach ($records as $record) {
                $provider = $this->providerForDomain($record->root_domain);
                $result = $cleanupService->cleanupRecord($record, $provider, $domainPool, false, $context);
                if ($result['success']) {
                    $record->delete();
                } else {
                    $failed++;
                }
            }
            if ($failed > 0) {
                Log::warning("[DnsSyncer] cleanup: some CF deletes failed for node {$nodeId}", ['context' => $context, 'failed' => $failed]);
                // 节点正在回收, 强制删本地记录避免阻塞
                DnsRecord::where('node_id', $nodeId)->whereIn('record_type', ['A', 'AAAA'])->delete();
            }
        } catch (\Exception $e) {
            Log::error("[DnsSyncer] cleanup exception for node {$nodeId}: " . $e->getMessage());
            DnsRecord::where('node_id', $nodeId)->delete();
        }
    }

    // ---------- 内部辅助 ----------

    /**
     * 为指定根域名解析 CloudflareProvider (CDN 域名用独立 cf_token).
     */
    private function providerForDomain($rootDomain)
    {
        if (isset($this->providerCache[$rootDomain])) {
            return $this->providerCache[$rootDomain];
        }
        $meta = $this->parsedPool['domainPool'][$rootDomain] ?? [];
        if (is_array($meta) && !empty($meta['cdn']) && !empty($meta['cf_token'])) {
            $provider = CloudflareProvider::withToken($meta['cf_token']);
        } else {
            $provider = app(CloudflareProvider::class);
        }
        $this->providerCache[$rootDomain] = $provider;
        return $provider;
    }

    private function getZoneId(array $meta, $domain = 'unknown')
    {
        $zoneId = $meta['zone_id'] ?? null;
        if (!$zoneId) {
            Log::error("[DnsSyncer] 未能从配置池找到域名 {$domain} 的 Zone ID");
        }
        return $zoneId;
    }

    private function getZoneIdForRecord($dnsRecord)
    {
        return $this->getZoneId(
            $this->parsedPool['domainPool'][$dnsRecord->root_domain] ?? [],
            $dnsRecord->root_domain
        );
    }

    private function parseServerField($server)
    {
        if (!$server || !strpos($server, '.')) {
            return null;
        }
        $parts = explode('.', $server, 2);
        return ['subdomain' => $parts[0], 'root_domain' => $parts[1]];
    }

    private function emptyStats()
    {
        return ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'failed' => 0, 'no_change' => 0];
    }

    private function tally(array $bpResult, array &$stats)
    {
        $action = $bpResult['action'] ?? 'unknown';
        if (isset($stats[$action])) {
            $stats[$action]++;
        } elseif ($action === 'adopted') {
            $stats['created']++;
        } elseif (!($bpResult['success'] ?? true)) {
            $stats['failed']++;
        }
    }

    private function allSuccess(array $results)
    {
        if (empty($results)) {
            return true;
        }
        foreach ($results as $r) {
            if (!($r['success'] ?? true)) {
                return false;
            }
        }
        return true;
    }

    private function errorResult($message)
    {
        return ['status' => 'error', 'message' => $message, 'results' => [], 'stats' => $this->emptyStats(), 'elapsed_s' => 0];
    }
}
