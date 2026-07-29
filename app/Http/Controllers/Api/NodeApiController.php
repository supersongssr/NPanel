<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Models\SsNode;
use App\Http\Models\DnsRecord;
use App\Components\Helpers;
use App\Components\NodeTrafficResetStore;
use App\Components\DNS\CloudflareProvider;
use App\Services\DnsRecordCleanupService;
use App\Services\NodeAddress\NodeAddressService;

class NodeApiController extends Controller
{
    const DEFAULT_RECORDS_LIMIT = 180;

    /**
     * register 时只负责节点身份 (ID / ip+ipv6 / 协议预置). server 域名前缀编码 ip 栈信息:
     *   - 客户端连接地址: NodeAddressService::resolveAddress() (订阅时惰性)
     *   - DNS 记录同步: DnsSyncer::syncCluster() (resolve_dns 端点)
     *
     * server = address 标志 (register 之后, DNS 处理之前已确定, 唯一定义节点是 ipv4 还是 ipv6):
     *   - 按 [ipv4×N, ipv6×N] 槽位展开 (ipv4 先, ipv6 后). 如 v2_name=xhttp (3 协议):
     *       main=ipv4, clone1=ipv4, clone2=ipv4, clone3=ipv6, clone4=ipv6, clone5=ipv6.
     *   - 每个节点 (主节点 + clone) 同时存储物理节点的 ip (IPv4) 与 ipv6 (IPv6), 不再互斥.
     *   - ipv6 节点的连接地址 (server) = 原生 ipv6 字面量 (ipv6 DNS 解析不可靠, 直连 IP);
     *     ipv4 节点 server = IP (主节点, 直连) / `n{id}` clone (resolve_dns 建 A 记录解析到节点 IP).
     *     isIpv6Node / resolveAddress
     *     据此判定 (server 含 ':' 即 ipv6; 兼容旧数据 server 含 `ipv6n` 标识).
     *   - ipv6 节点连接地址始终为 ipv6 (resolveAddress 恒返回 ipv6), 不做 host 解析;
     *     仅 ipv4 节点在 dns/cdn 模式下由 DnsSyncer 转为 host 解析.
     */

    /**
     * 随机 path 缓存: 单次 register() 调用内生成, 主节点与所有 clone 共用,
     * 保证 nginx / xray 下发与订阅 path 完全一致. request-scoped (每请求新实例).
     */
    private $clusterPaths = [];

    /**
     * xhttp-verify 模式的随机校验 token (UUID v4) 缓存:
     * 单次 register() 调用内生成, 主节点与所有 clone 共用同一 UUID v4.
     * 对应客户端请求头 Xhttp-Verify, nginx 层据此过滤.
     * 仅当 v2_name === 'xhttp-verify' 时生成; 否则为 null (不启用校验).
     * (使用自定义 header Xhttp-Verify, 避免被客户端自动改写 User-Agent.)
     */
    private $clusterXhttpVerify = null;

    /**
     * vision-reality 模式的 REALITY X25519 密钥对 + shortId 缓存:
     * 单次 register() 调用内生成, 主节点与所有 clone 共用同一组 (同一物理节点).
     *   - private: 服务端 privateKey (xray config)
     *   - public:  客户端 pbk (订阅)
     *   - shortId: 两端共用
     * 仅当 v2_name 属于 REALITY_V2_NAMES (vision-reality / vision-reality-min-firefox /
     * xhttp-reality-minClientVer / xhttp-reality-min-firefox) 时生成; 否则为 null (非 REALITY 模式).
     * privateKey 永不进入订阅, pbk 永不进入 xray config (存储在独立 DB 列).
     */
    /**
     * xhttp-cdn / xhttp-cdn-hy2 模式的 ECH 下载规格缓存:
     * 单次 register() 调用内解析, 主节点与所有 clone 共用同一规格 (集群级, 仅依赖 root_domain).
     * 形如 `ech.{root_domain}+udp://1.1.1.1` (域名池 meta.ech 可覆盖), 仅写入 xhttp 槽位
     * (hy2 不经过 CF, 不使用 ECH). 非 CDN 模式为 null.
     */
    private $clusterEch = null;

    /**
     * xhttp-split 模式的下行域名 (dl_host) 缓存:
     * 单次 register() 调用内生成一次, 主节点与所有 clone 共用同一个 (同一物理节点).
     * 形如 `{rand8}d{mainid}.{rootDomain}` — 与上行 host ({rand8}n{id} / {rand8}ipv6n{id})
     * 不同 (独立随机前缀 + 'd' 标记), 但同一 rootDomain (复用泛域名证书 + 同一 nginx server_name).
     * 下行域名仅供客户端 downloadSettings (TLS SNI + HTTP Host); 下行直连 IP, 无需 DNS.
     * 仅当 v2_name === 'xhttp-split' 时生成; 否则为 null (非 split 模式).
     */
    private $clusterDlHost = null;

    public function __construct()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    private function parseDomainPool($sysConf)
    {
        return Helpers::parseDomainPool($sysConf);
    }

    public function listAll(Request $request)
    {
        // Token 验证: Bearer Header 优先, fallback 到 ?token= 查询参数
        $token = null;
        $header = $request->header('Authorization', '');
        if (stripos($header, 'Bearer ') === 0) {
            $token = substr($header, 7);
        }
        if (!$token) {
            $token = $request->get('token');
        }
        if (!$token || $token !== env('API_TOKEN')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: invalid or missing token',
            ], 401);
        }

        $nodes = SsNode::orderBy('id', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'count'  => $nodes->count(),
            'data'   => $nodes,
        ]);
    }

    private function getDomainLimit(array $meta)
    {
        return isset($meta["records_limit"])
            ? (int) $meta["records_limit"]
            : self::DEFAULT_RECORDS_LIMIT;
    }

    // ------------------------------------------------------------------
    // 节点地址解析已迁移到独立模块 NodeAddressService:
    //   isCdnNode / resolveNodeAddress / resolveMode / resolveCdnIp ...
    // 以及 CF 优选 IP 池 OptimizedIpPool (CSV 来源).
    // register / applyV2Preset 不再分配 CDN IP, 保持节点注册系统纯净.
    // ------------------------------------------------------------------

    public function applyId(Request $request)
    {
        $nodeIp = $request->input("node_ip");
        $nodeIpv6 = $request->input("node_ipv6");

        $cutoff = date("Y-m-d H:i:s", strtotime("-32 days"));
        $node = SsNode::where(function ($query) use ($cutoff) {
            $query
                ->where("heartbeat_at", "<", $cutoff)
                ->orWhere(function ($q) use ($cutoff) {
                    $q->whereNull("heartbeat_at")->where(
                        "created_at",
                        "<",
                        $cutoff,
                    );
                });
        })
            ->orderBy("heartbeat_at", "desc")
            ->first();

        $recycled = false;
        if ($node) {
            $recycled = true;
            $this->safeCleanupNodeDns($node->id, 'applyId');
            // 回收身份清零: 旧 traffic_used + 旧重置时间记录必须清除.
            // 否则若 applyId 后 register 未跟上 (节点中途崩溃), 该 ID 残留 is_clone=0 +
            // 旧 traffic_used>0 + 旧重置时间, 会触发 32 天安全网误判为"流量卡死"
            // 而强制清零 + 误告警. register 跟上时会重新填充, 不受影响.
            $node->traffic_used = 0;
            NodeTrafficResetStore::forget($node->id);
        } else {
            $node = new SsNode();
        }

        $node->name = "New Node " . ($nodeIp ?: $nodeIpv6);
        $node->ip = $nodeIp ?: "";
        $node->ipv6 = $nodeIpv6 ?: "";
        $node->status = 0;
        $node->is_clone = 0;
        $node->save();

        Log::info("[Node API] ID 分配成功", [
            "ip" => $request->ip(),
            "node_id" => $node->id,
            "assigned_ip" => $node->ip,
            "assigned_ipv6" => $node->ipv6,
            "recycled" => $recycled,
        ]);

        return response()->json(["node_id" => $node->id]);
    }

    /**
     * POST /api/node/unlock_check
     * 节点独立上报 IP 服务解锁检测结果（由 unlockCheck.sh 调用）
     */
    public function unlockCheck(Request $request)
    {
        // Token 验证: Bearer Header 优先, fallback 到 ?token= 查询参数
        $token = null;
        $header = $request->header('Authorization', '');
        if (stripos($header, 'Bearer ') === 0) {
            $token = substr($header, 7);
        }
        if (!$token) {
            $token = $request->get('token');
        }
        if (!$token || $token !== env('API_TOKEN')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $nodeId = $request->input('node_id');
        if (empty($nodeId)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'missing_node_id',
            ], 400);
        }

        $node = SsNode::find($nodeId);
        if (!$node) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Node not found',
            ], 404);
        }

        $unlockServices = [
            'netflix', 'disney', 'chatgpt', 'claude', 'tiktok',
            'bilibili', 'iqiyi', 'bahamut', 'mewatch', 'bing',
            'google_scholar', 'notebooklm',
        ];
        $unlockData = [];
        foreach ($unlockServices as $service) {
            $val = $request->input('unlock_' . $service);
            if ($val !== null && $val !== '') {
                $unlockData[$service] = $val;
            }
        }

        if (empty($unlockData)) {
            return response()->json([
                'status'  => 'success',
                'message' => 'no_data',
            ]);
        }

        $node->node_unlock = urldecode(http_build_query($unlockData));
        $node->save();

        return response()->json([
            'status'  => 'success',
            'updated' => array_keys($unlockData),
        ]);
    }

    public function register(Request $request)
    {
        $nodeId = $request->input("node_id");

        $node = SsNode::find($nodeId);
        if (!$node) {
            return response()->json(
                ["status" => "error", "message" => "Node not found"],
                404,
            );
        }

        // Reset all v2 fields to clean defaults before applying new config,
        // preventing stale values (e.g. leftover v2_flow) from previous registrations.
        $this->resetNodeToDefaults($node);

        $sysConf = Helpers::systemConfig();

        $nodeMemory = (float) $request->input(
            "node_memory",
            $request->input("memory", 0),
        );

        $defaultV2Name = $sysConf["node_default_v2_name"] ?? "vision-curvePreferences";

        // 上报的 v2_name 缺失或不在合法集合内时, 回落到默认.
        $v2Name = $request->input("v2_name");
        if (!isset(self::V2_PROTOCOL_SLOTS[$v2Name])) {
            $v2Name = $defaultV2Name;
        }

        // PQ 模式准入 (xhttp-pq / vision-reality-pq): nginx ssl_ecdh_curve
        // X25519MLKEM768 要求 OpenSSL >= 3.5. 节点 openssl 不满足 (含未上报 / unknown)
        // 时, 不报错, 降级到默认 v2_name.
        // 注意: 为保留后量子防护, node_default_v2_name 宜为 vision-curvePreferences 变体
        // (其 PQ 曲线由 xray Go TLS curvePreferences 强制, 与 openssl 无关).
        $reportedOpenssl = $request->has("node_openssl")
            ? $request->input("node_openssl")
            : null;
        if (in_array($v2Name, self::PQ_V2_NAMES, true)
            && !$this->opensslSupportsPQ($reportedOpenssl)
        ) {
            Log::warning(
                "[Node API] {$v2Name} 降级: OpenSSL 不支持 X25519MLKEM768, 回退 "
                    . $defaultV2Name,
                [
                    "node_id" => $nodeId,
                    "node_openssl" => $reportedOpenssl ?: "(unreported)",
                ],
            );
            $v2Name = $defaultV2Name;
        }

        $nodePort = (int) $request->input("node_port", 443);

        $rootDomain = $this->resolveDomainAffinity(
            $request->input("root_domain"),
            $sysConf,
            // CDN 域名亲和: 仅当节点意图走 CDN (v2_name=xhttp-cdn) 时选 cdn:true 域名
            // (独立 CDN 根域名). 其余情况选普通域名.
            NodeAddressService::isCdnV2Name($v2Name),
        );

        // 集群共用 host/sni: {random8}n{mainid}.{rootDomain}.
        // 随机 8 位前缀 (UUID 取前 8 位) 替代固定 "n" 前缀, 降低 SNI/host 特征被识别风险;
        // 主节点与所有 clone 共用同一 host/sni, clone 不再各自生成 host/sni, 降低 DNS 解析数量.
        // (clone 的连接地址 address 仍按节点独立, 由 DNS 模块解析; host/sni 仅作 TLS 身份.)
        $clusterHost = $this->buildClusterHost($node->id, $rootDomain);

        $nodeCost = (float) $request->input("node_cost", 0);
        $clientLevel = $request->input("node_level");
        $mainLevel =
            $clientLevel !== null
                ? (int) $clientLevel
                : max(1, (int) floor($nodeCost));

        // --- Store both IPv4 and IPv6 (address 标志由 register 槽位写入) ---
        // 每个节点同时存储物理节点的 ip (IPv4) 与 ipv6 (IPv6), 不再做单栈互斥.
        // “是 ipv4 还是 ipv6” 由 register 后写入的 address 标志判定, 不靠 ip 缺失推断.
        $reportedIp = $request->input("node_ip");
        $reportedIpv6 = $request->input("node_ipv6");
        $node->ip = $reportedIp ?: $node->ip;
        $node->ipv6 = $reportedIpv6 ?: $node->ipv6;
        Log::debug("[Node API] register: store both stacks", [
            "node_id" => $nodeId,
            "ip" => $node->ip,
            "ipv6" => $node->ipv6,
            "reported_ip" => $reportedIp,
            "reported_ipv6" => $reportedIpv6,
        ]);

        // --- Standardized naming: {CountryCode}-{City} ---
        $countryCode = strtoupper($request->input("node_country_code", ""));
        $city = $request->input("node_city", "");
        if ($countryCode && $city) {
            $node->name = $countryCode . "-" . $city;
        } elseif ($countryCode) {
            $node->name = $countryCode;
        }

        // --- Mirror-overwrite: use node value if reported, reset to default otherwise ---
        // This prevents recycled nodes from carrying stale config from previous owners.
        $node->v2_name = $v2Name;
        $node->node_rxtx = $request->input(
            "node_rxtx",
            $request->input(
                "node_rxtx_mode",
                $request->input("billing_mode", "rxtx"),
            ),
        );
        $node->node_cpu =
            $request->has("node_cpu") || $request->has("cpu")
                ? $request->input("node_cpu", $request->input("cpu"))
                : null;
        $node->node_memory = $nodeMemory;
        $node->node_disk =
            $request->has("node_disk") || $request->has("disk")
                ? $request->input("node_disk", $request->input("disk"))
                : null;
        // 注: node_os / node_openssl 仅从 register 请求读取用于 xhttp-pq 准入判断 ($reportedOpenssl),
        // 不落库 (暂不改动 ss_node 表结构). 见上方降级逻辑.
        $node->bandwidth = (int) $request->input(
            "node_bandwidth",
            $request->input("bandwidth", 100),
        );

        $node->info = $request->input("node_info", "");
        $node->level = $mainLevel;
        $node->node_group = $request->input("node_group", 2);
        $node->node_cost = $nodeCost;
        $node->traffic_limit =
            $request->input("node_traffic_limit", 1000) * 1024 * 1024 * 1024;
        $node->reset_day = (int) $request->input("node_traffic_resetday", 1);
        $node->sort = $request->input("node_sort", 0);
        $node->traffic_rate = $request->input("node_traffic_rate", 1.0);
        $node->country_code = strtolower(
            $request->input("node_country_code", "un"),
        );
        $node->node_country = $request->has("node_country")
            ? $request->input("node_country")
            : null;
        $node->node_city = $request->has("node_city")
            ? $request->input("node_city")
            : null;
        $node->status = 1;

        // --- Node-driven traffic sync ---
        // Trust node-reported cached traffic (prevents traffic loss on re-install)
        $nodeTrafficUsed = $request->input("traffic_used");
        $node->traffic_used =
            $nodeTrafficUsed !== null ? (float) $nodeTrafficUsed : 0;

        // Initialize last_raw_total from raw_rx/tx so the first status() call
        // computes incremental = 0 instead of the full NIC counter.
        $initRx = (float) $request->input("raw_rx", 0);
        $initTx = (float) $request->input("raw_tx", 0);
        if ($node->node_rxtx == "rxtx") {
            $node->last_raw_total = ($initRx + $initTx) / 2;
        } else {
            $node->last_raw_total = $initTx;
        }

        // 流量重置时间基线 (Redis, 非关键参数不入库): register 是节点活跃计费的起点,
        // 设为当前时间作为 AutoResetNodeTraffic 32 天安全网的计时基线.
        // 后续正常月度重置会持续刷新该记录.
        NodeTrafficResetStore::set($node->id, date("Y-m-d H:i:s"));

        // --- Mirror-overwrite: reset unreported fields to defaults ---
        // Ensures recycled nodes carry no stale config from previous owners.
        $node->traffic_used_daily = 0;
        $node->traffic_left_daily = 0;
        $node->server_uptime = 0;
        $node->heartbeat_at = null;

        $node->save();

        // --- Fission matrix: build protocol × IP slots ---
        // 槽位顺序: [ipv4×N, ipv6×N] (ipv4 先, ipv6 后). 因此主节点与靠前的 clone 为 ipv4,
        // 靠后的 clone 为 ipv6. 如 v2_name=xhttp (3 协议): main/clone1/clone2=ipv4, clone3/4/5=ipv6.
        $protocols = $this->expandProtocols($v2Name);

        $ips = [];
        if ($node->ip) {
            $ips[] = ["type" => "ipv4", "addr" => $node->ip];
        }
        if ($node->ipv6) {
            $ips[] = ["type" => "ipv6", "addr" => $node->ipv6];
        }

        $slots = [];
        foreach ($ips as $ipInfo) {
            foreach ($protocols as $protocol) {
                $slots[] = [
                    "protocol" => $protocol,
                    "ip_type" => $ipInfo["type"],
                    "addr" => $ipInfo["addr"],
                ];
            }
        }

        $totalTarget = count($slots);
        if ($totalTarget === 0) {
            // 无可用 IP 槽位的退化场景: server/host/sni 统一为 clusterHost.
            $node->server = $clusterHost;
            $node->v2_host = $clusterHost;
            $node->v2_sni = $clusterHost;
            $node->node_ids = (string) $node->id;
            $node->save();
            return response()->json([
                "status" => "success",
                "node_id" => $node->id,
                "clone_node_ids" => [],
                "node_ids" => (string) $node->id,
                "root_domain" => $rootDomain,
                "v2_name" => $v2Name,
                "node_port" => $nodePort,
                // 回传 ip/ipv6 (IP fate 互斥后的规范值), 供节点端 node.json 缓存,
                // 用于安装/重装时判断节点 IP 是否变动
                "ip" => $node->ip,
                "ipv6" => $node->ipv6,
            ]);
        }

        // --- Node-reported node_ids: prefer reusing the same clone IDs ---
        $reportedNodeIds = $request->input("node_ids");
        $reportedCloneIds = [];
        if ($reportedNodeIds) {
            $parsed = array_map("intval", explode(",", $reportedNodeIds));
            foreach ($parsed as $rid) {
                if ($rid !== (int) $nodeId) {
                    $reportedCloneIds[] = $rid;
                }
            }
        }

        $existingClones = SsNode::where("is_clone", $nodeId)
            ->get()
            ->values();
        $cutoff = date("Y-m-d H:i:s", strtotime("-32 days"));

        $deadNodes = SsNode::where(function ($query) use ($cutoff) {
            $query
                ->where("heartbeat_at", "<", $cutoff)
                ->orWhere(function ($q) use ($cutoff) {
                    $q->whereNull("heartbeat_at")->where(
                        "created_at",
                        "<",
                        $cutoff,
                    );
                });
        })
            ->where("id", "!=", $nodeId)
            ->where("is_clone", "!=", $nodeId)
            ->orderBy("heartbeat_at", "desc")
            ->get();
        $deadIdx = 0;

        $cloneIds = [];

        // 随机 path: register 阶段一次性生成, 主节点与所有 clone 共用同一组随机 path,
        // 保证 nginx / xray 下发与订阅 path 完全一致 (避免固定 path 被封锁).
        $this->clusterPaths = $this->generateClusterPaths($protocols);

        // xhttp-verify 模式: 生成集群共用随机 UUID v4 校验 token.
        // 仅此模式启用, 普通 xhttp / xhttp-hy2 等不启用校验.
        if ($v2Name === "xhttp-verify") {
            $this->clusterXhttpVerify = Helpers::genRandomUuid();
        } else {
            $this->clusterXhttpVerify = null;
        }

        // vision-reality 模式: 生成集群共用的 per-node X25519 密钥对 + shortId.
        //   private -> xray 服务端 privateKey (仅 config() 读取)
        //   public  -> 订阅 pbk (仅订阅层读取)
        //   shortId -> 两端共用 (xray shortIds / 订阅 sid)
        // 主节点与所有 clone 共用同一组 (同一物理节点, host/sni 相同, reality 偷自己证书).
        // 用 PHP libsodium 生成, 与 `xray x25519` 输出完全兼容.
        if (in_array($v2Name, self::REALITY_V2_NAMES, true)) {
            $this->clusterReality = $this->generateRealityKeys();
        } else {
            $this->clusterReality = null;
        }

        // xhttp-cdn / xhttp-cdn-hy2 模式: 解析集群共用的 ECH 下载规格 (隐藏真实 SNI).
        // 集群级 (仅依赖 root_domain), 主节点与所有 clone 共用; 域名池 meta.ech 可覆盖默认.
        // 仅 xhttp 槽位使用 (CF CDN 通路); hy2 槽位不走 CF, v2_ech 保持 null (见 applyV2Preset).
        if (NodeAddressService::isCdnV2Name($v2Name)) {
            $this->clusterEch = $this->resolveClusterEch($rootDomain, $sysConf);
        } else {
            $this->clusterEch = null;
        }

        // xhttp-split 模式: 生成集群共用的下行域名 (dl_host) — 与上行 host 不同
        // (独立随机前缀 + 'd' 标记), 但同一 rootDomain (复用泛域名证书 + 同一 nginx server_name).
        // 主节点生成一次, 所有 clone 复用. 下行域名仅供客户端 downloadSettings (SNI + Host),
        // 下行直连 IP (v2_xhttp_dl_add), 无需 DNS. (服务端 xray/nginx 不读取此项.)
        if ($v2Name === "xhttp-split") {
            $this->clusterDlHost = $this->buildDownloadHost($node->id, $rootDomain);
        } else {
            $this->clusterDlHost = null;
        }

        $mainSlot = array_shift($slots);
        $mainIsIpv6 = $mainSlot["ip_type"] === "ipv6";
        // clusterHost (域名) 始终作为 v2_host/v2_sni (TLS 身份, applyV2Preset 第 7 参写入),
        // 与连接地址 (server) 解耦. main 节点 server = 连接地址 = IP (直连, 不解析 DNS):
        //   ipv4 slot → IPv4 (server = ip);  ipv6 slot → 原生 ipv6 字面量 (server = ipv6).
        // 主节点 server 恒为 IP → DnsSyncer 跳过主节点 (isMainNode), 不建 DNS 记录;
        // clusterHost 仅作 TLS SNI/Host, 无需 DNS 解析 (降低集群 DNS 解析数量).
        if ($mainIsIpv6) {
            $clusterHost = $this->buildClusterHost($node->id, $rootDomain, true);
            $node->server = $node->ipv6 ?: $clusterHost;
        } else {
            $node->server = $node->ip ?: $clusterHost;
        }
        $this->applyV2Preset(
            $node,
            $mainSlot["protocol"],
            $rootDomain,
            $mainIsIpv6,
            $v2Name,
            $nodePort,
            $clusterHost,
        );
        $node->save();
        $allNodeIds = [$node->id];

        foreach ($slots as $i => $slot) {
            $isIpv6 = $slot["ip_type"] === "ipv6";

            if ($i < $existingClones->count()) {
                $clone = $existingClones[$i];
                $this->resetNodeToDefaults($clone);
                $clone->name = $node->name;
                $clone->v2_name = $v2Name;
                // resetNodeToDefaults 把 is_clone 清零, 这里必须恢复指向主节点,
                // 否则 clone 会被误判为主节点 (is_clone=0) 导致 DNS/地址逻辑异常.
                $clone->is_clone = $nodeId;
                $clone->node_rxtx = $node->node_rxtx;
                // clone 与主节点同一物理机, 同时存储物理节点的 ip+ipv6 (不做单栈互斥).
                // 是 ipv4 还是 ipv6 由 server 前缀 (n/ipv6n) 判定, 不靠 ip 缺失.
                $clone->ip = $node->ip ?: "";
                $clone->ipv6 = $node->ipv6 ?: "";
                $clone->level = rand($mainLevel, min(5, $mainLevel + 2));
                $clone->node_group = $node->node_group;
                $clone->traffic_rate = $node->traffic_rate;
                $clone->status = 1;
                // clone 连接地址 (server): ipv4 = 连接域名 n{id}.domain (resolve_dns 创建 A 记录);
                // ipv6 = 原生 ipv6 字面量 (ipv6 DNS 解析不可靠, 直连 IP). host/sni 复用 clusterHost.
                $clone->server = $isIpv6
                    ? ($node->ipv6 ?: "")
                    : "n" . $clone->id . "." . $rootDomain;
                $this->applyV2Preset(
                    $clone,
                    $slot["protocol"],
                    $rootDomain,
                    $isIpv6,
                    $v2Name,
                    $nodePort,
                    $clusterHost
                );
                $clone->save();
            } else {
                $clone = null;
                if ($deadIdx < $deadNodes->count()) {
                    $clone = $deadNodes[$deadIdx++];
                    $this->resetNodeToDefaults($clone);
                    $this->safeCleanupNodeDns($clone->id, 'register.deadClone');
                } else {
                    $clone = new SsNode();
                }

                $clone->name = $node->name;
                $clone->v2_name = $v2Name;
                $clone->is_clone = $nodeId;
                $clone->node_rxtx = $node->node_rxtx;
                // clone 与主节点同一物理机, 同时存储物理节点的 ip+ipv6 (不做单栈互斥).
                // 是 ipv4 还是 ipv6 由 server 前缀 (n/ipv6n) 判定, 不靠 ip 缺失.
                $clone->ip = $node->ip ?: "";
                $clone->ipv6 = $node->ipv6 ?: "";
                $clone->level = rand($mainLevel, min(5, $mainLevel + 2));
                $clone->node_group = $node->node_group;
                $clone->traffic_rate = $node->traffic_rate;
                $clone->status = 1;
                $clone->save();

                // clone 连接地址 (server): ipv4 = 连接域名 n{id}.domain (resolve_dns 创建 A 记录);
                // ipv6 = 原生 ipv6 字面量 (ipv6 DNS 解析不可靠, 直连 IP). host/sni 复用 clusterHost.
                $clone->server = $isIpv6
                    ? ($node->ipv6 ?: "")
                    : "n" . $clone->id . "." . $rootDomain;
                $this->applyV2Preset(
                    $clone,
                    $slot["protocol"],
                    $rootDomain,
                    $isIpv6,
                    $v2Name,
                    $nodePort,
                    $clusterHost
                );
                $clone->save();
            }

            $cloneIds[] = $clone->id;
            $allNodeIds[] = $clone->id;
        }

        // Recycle excess old clones that were not reused
        if ($existingClones->count() > count($slots)) {
            foreach ($existingClones->slice(count($slots)) as $excess) {
                $excess->is_clone = 0;
                $excess->status = 0;
                $excess->save();
                $this->safeCleanupNodeDns($excess->id, 'register.excessClone');
            }
        }

        $nodeIdsStr = implode(",", $allNodeIds);
        $node->node_ids = $nodeIdsStr;
        $node->save();

        Log::info("[Node API] 节点注册成功", [
            "node_id" => $node->id,
            "name" => $node->name,
            "protocol_group" => $v2Name,
            "domain_decision" => $rootDomain,
            "node_ids" => $allNodeIds,
            "total_nodes_generated" => count($allNodeIds),
            "reported_node_ids" => $reportedCloneIds,
            "ip" => $node->ip,
            "ipv6" => $node->ipv6,
        ]);

        // 回传 ip/ipv6 (IP fate 互斥 + 主节点单栈锁定后的规范值),
        // 供节点端 node.json 缓存, 用于安装/重装时判断节点 IP 是否变动
        return response()->json([
            "status" => "success",
            "node_id" => $node->id,
            "clone_node_ids" => $cloneIds,
            "node_ids" => $nodeIdsStr,
            "root_domain" => $rootDomain,
            "v2_name" => $v2Name,
            "node_port" => $nodePort,
            "ip" => $node->ip,
            "ipv6" => $node->ipv6,
        ]);
    }

    private function resetNodeToDefaults($node)
    {
        // Identity (preserved: id, created_at, updated_at)
        $node->name = "";
        $node->v2_name = "";

        // Service type
        $node->type = 0;

        // Grouping & location
        $node->group_id = 0;
        $node->node_group = 1;
        $node->country_code = "un";
        $node->node_country = null;
        $node->node_city = null;

        // Server
        $node->server = "";
        $node->ip = "";
        $node->ipv6 = "";
        $node->desc = "";
        $node->ssh_port = 22;

        // SS legacy fields
        $node->method = "aes-256-cfb";
        $node->protocol = "origin";
        $node->protocol_param = "";
        $node->obfs = "plain";
        $node->obfs_param = "";

        // Traffic & bandwidth
        $node->traffic_rate = 1.0;
        $node->bandwidth = 100;
        $node->traffic = 1000;
        $node->traffic_limit = 1099511627776;
        $node->traffic_lasthour = 0;
        $node->traffic_lastday = 0;
        $node->traffic_used = 0;
        $node->traffic_left = 0;
        $node->traffic_used_daily = 0;
        $node->traffic_left_daily = 0;
        $node->last_raw_total = 0;

        // Feature flags
        $node->is_subscribe = 1;
        $node->is_nat = 0;
        $node->is_transit = 0;
        $node->is_tcp_check = 1;
        $node->compatible = 0;
        $node->single = 0;
        $node->single_force = 0;
        $node->single_port = "";
        $node->single_passwd = "";
        $node->single_method = "";
        $node->single_protocol = "";
        $node->single_obfs = "";

        // Status & metrics
        $node->sort = 0;
        $node->level = 1;
        $node->status = 0;
        $node->node_cost = 0;
        $node->node_online = 0;
        $node->node_onload = 0;
        $node->node_health = 1;
        $node->reset_day = 1;
        $node->heartbeat_at = null;
        $node->server_uptime = 0;
        $node->server_total_traffic = 0;

        // Hardware
        $node->node_cpu = null;
        $node->node_memory = null;
        $node->node_disk = null;

        // Billing
        $node->node_rxtx = null;

        // Unlock & info
        $node->node_unlock = "";
        $node->info = "";
        $node->monitor_url = null;
        $node->node_uuid = null;

        // Clone / fission
        $node->is_clone = 0;
        $node->node_ids = null;

        // V2Ray — all reset to blank/zero
        $node->v2_net = "";
        $node->v2_tls = 0;
        $node->v2_port = 0;
        $node->v2_hop_ports = null;
        $node->v2_flow = null;
        $node->v2_fp = "";
        $node->v2_method = "";
        $node->v2_encryption = "";
        $node->v2_alter_id = 0;
        $node->v2_type = "";
        $node->v2_host = "";
        $node->v2_sni = null;
        $node->v2_path = "";
        $node->v2_xhttp_verify = null;
        $node->v2_alpn = null;
        $node->v2_mode = null;
        $node->v2_servicename = null;
        $node->v2_cdn = "";
        $node->v2_cdn_ip = "";
        // xhttp-cdn / xhttp-cdn-hy2 的 ECH 下载规格: 回收节点时清空, 防止旧值残留.
        $node->v2_ech = null;
        // xhttp-split 的下行域名 / 下行地址: 回收节点时清空, 防止旧值残留.
        $node->v2_xhttp_dl_host = null;
        $node->v2_xhttp_dl_add = null;
        $node->v2_insider_port = 0;
        $node->v2_outsider_port = 0;

        // vision-reality: REALITY X25519 密钥对 + shortId (配套, register 时生成).
        // 回收节点时清空, 防止旧密钥残留.
        $node->v2_reality_pbk = null;
        $node->v2_reality_private = null;
        $node->v2_reality_sid = null;
    }

    /**
     * xhttp-pq 后量子准入阈值.
     *   PQ_MIN_OPENSSL — X25519MLKEM768 首个支持的 OpenSSL 版本 (3.5.0), 节点 openssl 须 >= 此值.
     *                    不满足 (含未上报/unknown) 时降级到配置项 node_default_v2_name
     *                    (默认 vision-curvePreferences, 其 PQ 曲线由 xray Go TLS curvePreferences
     *                    强制, 与 openssl 无关, 即便节点 OpenSSL 旧版仍保留后量子防护).
     */
    const PQ_MIN_OPENSSL = "3.5";

    const V2_PRESETS = [
        "vision" => [
            "type" => 3,
            "v2_net" => "tcp",
            "v2_tls" => 1,
            "v2_port" => 443,
            "v2_flow" => "xtls-rprx-vision",
            "v2_fp" => "firefox",
            "v2_method" => "none",
            "v2_encryption" => "none",
            "v2_alter_id" => 0,
            "v2_type" => "none",
        ],
        "xhttp" => [
            "type" => 3,
            "v2_net" => "xhttp",
            "v2_tls" => 1,
            "v2_port" => 443,
            "v2_method" => "none",
            "v2_encryption" => "none",
            "v2_alter_id" => 0,
            "v2_type" => "none",
            "v2_mode" => "auto",
            "v2_alpn" => "h2,http/1.1",
            "v2_fp" => "firefox",
        ],
        "ws" => [
            "type" => 2,
            "v2_net" => "ws",
            "v2_tls" => 1,
            "v2_port" => 443,
            "v2_method" => "auto",
            "v2_encryption" => "none",
            "v2_alter_id" => 0,
            "v2_type" => "none",
            "v2_fp" => "firefox",
        ],
        "grpc" => [
            "type" => 3,
            "v2_net" => "grpc",
            "v2_tls" => 1,
            "v2_port" => 443,
            "v2_method" => "none",
            "v2_encryption" => "none",
            "v2_alter_id" => 0,
            "v2_type" => "none",
            "v2_mode" => "multi",
            "v2_alpn" => "h2",
            "v2_fp" => "firefox",
        ],
        "hy2" => [
            "type" => 5,
            "v2_net" => "hysteria2",
            "v2_tls" => 1,
            "v2_port" => 443,
            "v2_method" => "none",
            "v2_encryption" => "none",
            "v2_alter_id" => 0,
            "v2_type" => "none",
            "v2_alpn" => "h3",
        ],
    ];

    /**
     * 后量子 (PQ) 模式的 v2_name 集合.
     * 这些模式在 nginx 层锁定 ssl_ecdh_curve X25519MLKEM768 (仅 TLS 1.3),
     * 要求节点 OpenSSL >= PQ_MIN_OPENSSL (3.5). 不满足时 register 降级到
     * node_default_v2_name (默认 vision-curvePreferences, PQ 曲线改由 xray 强制).
     * 新增 nginx 层 PQ 变体需在此登记.
     */
    const PQ_V2_NAMES = [
        "xhttp-pq",
        "vision-reality-pq",
    ];

    /**
     * REALITY (偷自己) 模式的 v2_name 集合.
     * 这些模式共享 REALITY 行为: register 时生成 X25519 密钥对 + shortId,
     * applyV2Preset 写入 reality 覆写标记 (v2_fp=firefox + vision flow),
     * 订阅层据此输出 security=reality + pbk/sid.
     * 新增 reality 变体 (如 vision-reality-min-firefox) 需在此登记.
     */
    const REALITY_V2_NAMES = [
        "vision-reality",
        "vision-reality-min-firefox",
        "vision-reality-pq",
        "xhttp-reality-minClientVer",
        "xhttp-reality-min-firefox",
    ];

    const V2_PROTOCOL_SLOTS = [
        "vision-hy2"        => ["vision", "vision", "hy2"],
        "vision"           => ["vision", "vision", "vision"],
        // vision-curvePreferences: VLESS + XTLS-Vision + TLS, 服务端 tlsSettings 锁死
        // curvePreferences:["x25519mlkem768"] (后量子混合密钥交换 X25519+ML-KEM-768).
        // GFW 旧栈主动探测 (Go<1.23 / 老 OpenSSL) 不支持该曲线, 握手即被拒 (alert 40),
        // 无法坐实节点为代理 -> 不升级 IP 封锁 (密码学硬门槛, 非指纹伪装).
        // 协议槽同 vision (inbound tag=proxy-vision); curvePreferences 是模板内静态字段,
        // 渲染器只做占位符替换, 该字段原样进入下发配置. 客户端需新版 uTLS (广播 PQ 曲线).
        "vision-curvePreferences"     => ["vision", "vision", "vision"],
        // vision-curvePreferences-hy2: 在 vision-curvePreferences 基线上叠加 hysteria2.
        // 实验目的: vision-curvePreferences 单独被墙率最低 (~1.8%), 加 hy2 后对比验证
        // hy2 是否会导致节点被封锁. curvePreferences 仅作用于 vision 槽位 (proxy-vision),
        // hy2 槽位 (proxy-hy2) 不加 (隔离 hy2 这个变量). 协议槽同 vision-hy2.
        "vision-curvePreferences-hy2" => ["vision", "vision", "hy2"],
        // vision-reality: VLESS + XTLS-Vision + REALITY (偷自己). xray 监听 443,
        // realitySettings.dest=127.0.0.1:8443 偷本机 nginx 8443 泛域名证书完成 TLS 握手;
        // 非法探测/普通浏览器透明回落到 nginx 8443 的 AriaNg 伪装站.
        // 协议槽复用 vision (inbound tag=proxy-vision), reality 行为由 v2_name +
        // v2_reality_* 列 + applyV2Preset 内的 reality 覆写标记 (不新增协议键).
        "vision-reality"    => ["vision", "vision", "vision"],
        // vision-reality-min-firefox: vision-reality 基线 + realitySettings 锁死
        // fingerprint=firefox + minClientVer=26.3.27. 强制最低 xray 客户端版本,
        // 避免低版本 reality 实现的指纹/握手缺陷导致节点被墙.
        // 协议槽同 vision-reality (inbound tag=proxy-vision), 差异仅在模板内
        // realitySettings 的两个静态字段 (minClientVer / fingerprint), 渲染器只做
        // 占位符替换, 这两个字段原样进入下发配置.
        "vision-reality-min-firefox" => ["vision", "vision", "vision"],
        // vision-reality-pq: vision-reality (reality 偷本地 nginx 8443) 的后量子变体.
        // 实验目的: 验证 reality 偷本地时, 若被偷的 nginx 8443 只允许 X25519MLKEM768
        // 后量子曲线 (ssl_ecdh_curve + ssl_protocols TLSv1.3), reality 的 TLS 握手
        // (xray Go TLS 作为 client 连 dest=127.0.0.1:8443) 会怎样 — Go TLS < 1.23 /
        // OpenSSL < 3.5 不支持该组, 握手失败 (alert 40), 节点可能无法工作.
        // xray 这层与 vision-reality 完全一致 (security=reality, dest=8443, vision flow),
        // PQ 锁仅在 nginx 8443 (vision-reality-pq.conf); 协议槽同 vision-reality.
        // 前置: 节点 OpenSSL >= 3.5 (register 据 node_openssl 校验; 不满足则降级到
        // node_default_v2_name, PQ 改由 xray curvePreferences 强制).
        "vision-reality-pq" => ["vision", "vision", "vision"],
        // xhttp-reality-minClientVer: VLESS + xhttp 传输 + REALITY (偷自己). xray 监听 443,
        // realitySettings.dest=127.0.0.1:8443 偷本机 nginx 8443 泛域名证书完成 TLS 握手;
        // xhttp 传输由 xray 在 reality 隧道内直接处理 (nginx 不反代 path, 仅作伪装站).
        // 与 vision-reality 区别: 传输是 xhttp (无 flow), inbound tag=proxy-xhttp.
        // minClientVer=26.3.1 写在模板 realitySettings, 限制最低 xray 客户端版本
        // (低版本 reality 实现的指纹/握手缺陷会导致节点被墙).
        "xhttp-reality-minClientVer" => ["xhttp", "xhttp", "xhttp"],
        // xhttp-reality-min-firefox: xhttp-reality-minClientVer 基线 +
        // realitySettings 锁死 fingerprint=firefox + minClientVer=26.3.27.
        // 强制 firefox uTLS 指纹 + 最低 xray 客户端版本, 避免低版本 reality 实现 /
        // 非 firefox 指纹的握手特征导致节点被墙.
        // 协议槽同 xhttp-reality-minClientVer (inbound tag=proxy-xhttp), 差异仅在
        // 模板内 realitySettings 的两个静态字段 (fingerprint / minClientVer).
        "xhttp-reality-min-firefox" => ["xhttp", "xhttp", "xhttp"],
        // vision-no-fallback: VLESS + XTLS-Vision + TLS, 但 xray inbound 删除 fallbacks 字段.
        // 非代理 TLS 流量 (浏览器 / 主动探测) 在 xray 层直接失败, 不回落到 nginx 伪装站.
        // 实验目的: 测试代理路径不对时直接返回 error 会怎样 (vs vision 的 fallback 到 AriaNg).
        // 协议槽同 vision (inbound tag=proxy-vision); 差异仅在 vision-no-fallback.json 模板内.
        "vision-no-fallback" => ["vision", "vision", "vision"],
        "xhttp-hy2"         => ["xhttp", "xhttp", "hy2"],
        "xhttp"            => ["xhttp", "xhttp", "xhttp"],
        "xhttp-ws-grpc"     => ["xhttp", "ws", "grpc"],
        "xhttp-hy2-ws-grpc" => ["xhttp", "hy2", "ws", "grpc"],
        // xhttp-verify: 仅 xhttp, 但 nginx 层强制校验客户端请求头 Xhttp-Verify (随机 UUID v4),
        // 提前过滤主动探测 / 低版本客户端, 避免 xray 直接暴露.
        // (用自定义 header, 避免被客户端自动改写 User-Agent.)
        "xhttp-verify"       => ["xhttp", "xhttp", "xhttp"],
        // xhttp-cdn: 仅 xhttp, 走 Cloudflare CDN. 独立 CF Token + 独立 CDN 根域名,
        // 灰云 DNS (不 proxied), 客户端连 CF 优选 IP, SNI 锁定域名. 隔离防封号.
        "xhttp-cdn"          => ["xhttp"],
        // xhttp-cdn-hy2: xhttp (TCP 走 CF CDN 绕过封锁) + hy2 (UDP 直连, 利用未被墙的 UDP).
        // 专为「TCP 被墙但 UDP 仍通」的节点: xhttp 走 CF 优选 IP, hy2 直连节点 IP.
        // 仅 xhttp 槽位走 CDN (isCdnNode 排除 hysteria2 传输); hy2 槽位始终直连.
        "xhttp-cdn-hy2"       => ["xhttp", "xhttp", "hy2"],
        // xhttp-nginx-444: 仅 xhttp, 但 nginx 层 location / 直接 return 444 (切断 TCP),
        // 不提供 AriaNg 伪装站. 路径不对的请求 / 主动探测一律被 nginx drop, 不返回任何响应.
        // 实验目的: 测试 nginx 层路径校验失败直接切断 TCP (vs xhttp 的回落到 AriaNg).
        // xray inbound 与普通 xhttp 完全一致; 差异仅在 xhttp-nginx-444.conf 模板内.
        "xhttp-nginx-444"    => ["xhttp", "xhttp", "xhttp"],
        // xhttp-split: 仅 xhttp, 但客户端上下行走两条不同的 xhttp 流 (downloadSettings).
        // 上行走 host/sni 域名 (普通 xhttp), 下行走 [独立域名 dl_host + 真实 IP] (直连),
        // 打破 GFW 对单一域名 / 单一连接上下行特征的关联检测.
        // 服务端与普通 xhttp 完全一致 (split 是纯客户端 downloadSettings 概念):
        //   - xray inbound mode=auto 同时处理上行 POST 与下行流
        //   - nginx 单 location /{path} 反代两条流到同一 inbound
        "xhttp-split"         => ["xhttp", "xhttp", "xhttp"],
        // xhttp-pq: 仅 xhttp, 但 nginx 层 ssl_ecdh_curve 锁死 X25519MLKEM768 +
        // ssl_protocols 锁死 TLSv1.3. X25519MLKEM768 = X25519+ML-KEM-768 混合后量子组,
        // 仅 TLS 1.3 定义; nginx 仅接受该 key share, GFW 旧栈 (Go<1.23/OpenSSL<3.5) 主动探测
        // 握手即失败 (alert 40), 无法坐实节点为代理 -> 密码学硬门槛防封 (非指纹伪装).
        // 实验目的: 测试 xhttp 在 nginx 锁 PQ 曲线时是否可防封 (模拟 vision-curvePreferences,
        // 但 PQ 曲线锁从 xray curvePreferences 上移到 nginx ssl_ecdh_curve).
        // 前置: 节点 OpenSSL >= 3.5 (register 据上报 node_openssl 校验; 不满足则降级
        // 到配置项 node_default_v2_name (默认 vision-curvePreferences), PQ 改由 xray 强制).
        // 协议槽同 xhttp (inbound tag=proxy-xhttp); xray 这层 (security:none) 与普通 xhttp
        // 完全一致, TLS 由 nginx 终结; 差异仅在 xhttp-pq.conf 模板内.
        "xhttp-pq"            => ["xhttp", "xhttp", "xhttp"],
    ];

    private function expandProtocols($v2Name)
    {
        if (isset(self::V2_PROTOCOL_SLOTS[$v2Name])) {
            return self::V2_PROTOCOL_SLOTS[$v2Name];
        }
        return explode("-", $v2Name);
    }

    /**
     * 判断节点 OpenSSL 是否支持后量子混合组 X25519MLKEM768 (须 >= PQ_MIN_OPENSSL).
     * node_openssl 格式为 "主.次" (如 "3.5"), 由 proxyInstall.sh ProbeOpenSSL 上报.
     * 未上报 / unknown / 无法解析 -> false (保守判定不支持, 触发 xhttp-pq 降级).
     */
    private function opensslSupportsPQ($version)
    {
        $v = trim((string) $version);
        if ($v === "" || strtolower($v) === "unknown") {
            return false;
        }
        // 仅取开头的主.次版本号, 忽略后续 (如 "3.5.0 (beta)" -> "3.5").
        if (!preg_match('/^\d+\.\d+/', $v, $m)) {
            return false;
        }
        return version_compare($m[0], self::PQ_MIN_OPENSSL, ">=");
    }

    private function applyV2Preset(
        $node,
        $protocol,
        $rootDomain,
        $isIpv6,
        $modeName = "",
        $nodePort = 443,
        $clusterHost = null
    ) {
        $preset = self::V2_PRESETS[$protocol] ?? null;
        if (!$preset) {
            Log::warning("[Node API] 无 v2 预设配置", [
                "protocol" => $protocol,
            ]);
            return;
        }

        foreach ($preset as $field => $value) {
            $node->{$field} = $value;
        }

        // Override v2_port with custom node_port (default 443)
        $node->v2_port = $nodePort;

        // Port override: in vision mode, ws and grpc are proxied by nginx on 2053
        $expandedMode = $this->expandProtocols($modeName);
        if (in_array("vision", $expandedMode)) {
            if ($protocol === "ws" || $protocol === "grpc") {
                $node->v2_port = 2053;
            }
        }

        // host/sni: 整个集群 (主节点 + clone) 共用 clusterHost ({random8}n{mainid}.domain),
        // clone 不再各自生成 host/sni, 降低 DNS 解析数量 + SNI/host 特征被识别风险.
        // (clone 连接地址仍按节点独立, 由 DNS 模块解析; host/sni 仅作 TLS 身份.)
        // 缺失 clusterHost 时回退到节点 server (向后兼容旧调用方).
        $hostValue = $clusterHost ?: $node->server;
        $node->v2_host = $hostValue;
        $node->v2_sni = $hostValue;

        // 注: xhttp-cdn 模式的 CF 优选 IP 分配已迁移到 NodeAddressService 模块.
        // register / applyV2Preset 不再写 v2_cdn / v2_cdn_ip, 保持节点注册系统纯净
        // (register 时 address 原生 = IP; CDN 地址在订阅时由模块从 CSV 情性解析).
        // SNI 锁定 = server 域名 (订阅层 applySniPrefix 对 CDN 节点跳过加前缀).

        // 随机 path: 优先使用 register 阶段生成的随机 path (主节点与 clone 共用同一组),
        // 缺失时回退到原版固定 path (向后兼容). 写入 v2_path / v2_servicename 后,
        // nginx / xray 下发与订阅均直接读取, 保证三者 path 完全一致.
        if ($protocol === "ws") {
            $node->v2_path = isset($this->clusterPaths["ws"])
                ? $this->clusterPaths["ws"]
                : "srp-ws";
        }
        if ($protocol === "grpc") {
            $grpcPath = isset($this->clusterPaths["grpc"])
                ? $this->clusterPaths["grpc"]
                : "srp-grpc";
            $node->v2_path = $grpcPath;
            $node->v2_servicename = $grpcPath;
        }
        if ($protocol === "xhttp") {
            $node->v2_path = isset($this->clusterPaths["xhttp"])
                ? $this->clusterPaths["xhttp"]
                : "srp-xhttp";
            // xhttp-verify 模式: 写入集群共用随机 UUID v4 token (主节点与 clone 一致).
            // 其他模式保持空 (不启用 nginx 层 Xhttp-Verify 校验).
            $node->v2_xhttp_verify = $this->clusterXhttpVerify;

            // xhttp-split 模式: 写入集群共用下行域名 (dl_host, 主节点与 clone 一致) +
            // 下行真实 IP (dl_add, 按 IP 半区区分). 仅订阅层读取 -> downloadSettings.
            // 服务端 (xray/nginx) 不读取这两列 — split 是纯客户端概念.
            if ($modeName === "xhttp-split") {
                $node->v2_xhttp_dl_host = $this->clusterDlHost;
                // dl_add: ipv6 半区 clone / ipv6-only 节点用 ipv6; 其余用 ipv4 (fallback ipv6).
                // (下行连接必须抵达本物理节点, 故按本槽位 IP 栈选真实 IP; 裸写, 不加方括号)
                $node->v2_xhttp_dl_add = $isIpv6
                    ? ($node->ipv6 ?: $node->ip ?: "")
                    : ($node->ip ?: $node->ipv6 ?: "");
            }
        }

        // vision-reality 模式: reality 是 TLS 变体 (偷自己证书), 与普通 vision
        // (tls + 自有证书文件) 区分. reality 行为由 v2_name='vision-reality' +
        // v2_reality_* 列标记 (v2_tls 仍存 1 = TLS 层启用, 不引入新魔法值).
        //   - v2_fp 强制 firefox: 限制客户端必须用 uTLS 指纹 (reality 抗 GFW 主动探测的核心伪装层).
        //   - v2_flow 保持 xtls-rprx-vision (vision 流控, reality 要求 tcp 传输).
        // REALITY 密钥: 集群共用 (主节点与 clone 同一物理节点), 从 clusterReality 写入
        // 三列. private 仅 config() 读 -> xray, pbk 仅订阅层读 -> 客户端, sid 两端共用.
        // xhttp-cdn-hy2 模式: 走 CF CDN (被墙节点场景), 强制 firefox uTLS 指纹
        // (与 vision-reality 同理: 抗 GFW 主动探测的核心伪装层). 写入 v2_fp 后,
        // 订阅层 (VLESS URI / sing-box / clash) 统一以 v2_fp 为准输出 client-fingerprint.
        if ($modeName === "xhttp-cdn-hy2") {
            $node->v2_fp = "firefox";
        }

        // xhttp-cdn / xhttp-cdn-hy2 的 xhttp 槽位: 写入 ECH 下载规格 (隐藏真实 SNI, 抗域名封锁).
        // hy2 槽位不经过 CF CDN (CF 无法中继 UDP), 不使用 ECH (v2_ech 保持 null).
        // 值形如 `ech.{root_domain}+udp://1.1.1.1`:
        //   - 前段 ech.{root_domain} = 发布 ECHConfigList 的 DNS HTTPS 记录域名
        //   - 后段 udp://1.1.1.1      = 拉取该记录用的干净 DNS 解析器 (避开被墙/被污染的本地解析)
        // 域名池 meta.ech 可覆盖该默认值 (见 resolveClusterEch).
        if (in_array($modeName, ['xhttp-cdn', 'xhttp-cdn-hy2'], true) && $protocol === 'xhttp') {
            $node->v2_ech = $this->clusterEch;
        }

        if (in_array($modeName, self::REALITY_V2_NAMES, true)) {
            $node->v2_tls = 1;
            $node->v2_fp = "firefox";
            // xtls-rprx-vision flow 仅适用于 vision (tcp) 传输; xhttp 传输无 flow
            // (xhttp-reality-minClientVer 走 xhttp preset, v2_flow 保持 null).
            if ($protocol === "vision") {
                $node->v2_flow = "xtls-rprx-vision";
            }
            $node->v2_reality_pbk = $this->clusterReality
                ? $this->clusterReality["public"]
                : null;
            $node->v2_reality_private = $this->clusterReality
                ? $this->clusterReality["private"]
                : null;
            $node->v2_reality_sid = $this->clusterReality
                ? $this->clusterReality["shortId"]
                : null;
        }
    }

    /**
     * 构建集群共用的 host/sni: {random8}n{mainNodeId}.{rootDomain} (ipv4) 或
     * {random8}ipv6n{mainNodeId}.{rootDomain} (ipv6).
     *
     * 随机 8 位前缀取自 UUID v4 的前 8 位 hex ([0-9a-f]), 替代旧的固定 "n" 前缀,
     * 降低 SNI/host 特征被 GFW 识别追踪的可能性. ipv6 主节点额外插入 `ipv6n` 标识,
     * 使 server subdomain 含 ipv6n → isIpv6Node 判定为 ipv6 (server = address 标志).
     * 主节点与所有 clone 共用同一 host/sni, clone 不再各自生成 host/sni
     * —— 这是降低 DNS 解析数量的核心.
     *
     * (host/sni 仅作 TLS 身份, 不需要单独的 DNS 记录; clone 的连接地址由 DNS 模块
     *  按节点独立解析, 与 host/sni 解耦.)
     *
     * @param  int    $mainNodeId 主节点 ID
     * @param  string $rootDomain 根域名
     * @param  bool   $isIpv6     主节点是否 ipv6 slot (true 则插入 ipv6n 标识)
     * @return string 例如 "a1b2c3d4n123.example.com" / "a1b2c3d4ipv6n123.example.com"
     */
    private function buildClusterHost($mainNodeId, $rootDomain, $isIpv6 = false)
    {
        $random8 = substr(str_replace('-', '', Helpers::genRandomUuid()), 0, 8);
        return $random8 . ($isIpv6 ? 'ipv6n' : 'n') . $mainNodeId . '.' . $rootDomain;
    }

    /**
     * 构建集群共用的下行域名 (dl_host): {random8}d{mainNodeId}.{rootDomain}.
     *
     * 用于 xhttp-split (上下行分离) 模式: 下行流走独立域名 (downloadSettings 的 TLS SNI /
     * HTTP Host), 与上行 host ({random8}n{id} / {random8}ipv6n{id}) 区分, 打破 GFW 对单一
     * 域名上下行特征的关联检测.
     *   - 独立的 8 位随机前缀 (与上行 host 前缀独立, 防扫描)
     *   - 'd' 标记 (vs 上行的 'n'/'ipv6n') 结构性保证上下行两域永不相等
     *   - 同一 rootDomain: 复用泛域名证书 + 同一 nginx server_name (泛域名匹配)
     * 主节点与所有 clone 共用同一 dl_host (同一物理节点), 下行直连 IP, dl_host 无需 DNS.
     *
     * @param  int    $mainNodeId 主节点 ID (集群共用一个 dl_host)
     * @param  string $rootDomain 根域名 (与上行 host 同源)
     * @return string 例如 "a1b2c3d4d123.example.com"
     */
    private function buildDownloadHost($mainNodeId, $rootDomain)
    {
        $random8 = substr(str_replace('-', '', Helpers::genRandomUuid()), 0, 8);
        return $random8 . 'd' . $mainNodeId . '.' . $rootDomain;
    }

    /**
     * 为本次 register 生成分流协议的随机 path.
     * 只为使用 path / serviceName 的协议 (xhttp / ws / grpc) 生成;
     * vision / hy2 不需要 path, 跳过.
     * 整个集群 (主节点 + clone) 共用同一组 path, 保证 nginx / xray / 订阅一致.
     *
     * @param  array $protocols  expandProtocols() 的结果
     * @return array  例如 ['xhttp'=>'<32hex>', 'ws'=>'<32hex>', 'grpc'=>'<32hex>']
     */
    private function generateClusterPaths(array $protocols)
    {
        $paths = [];
        foreach (array_unique($protocols) as $proto) {
            if ($proto === "xhttp" || $proto === "ws" || $proto === "grpc") {
                $paths[$proto] = Helpers::genRandomPath();
            }
        }
        return $paths;
    }

    /**
     * 从节点所属集群解析分流 path (xhttp / ws / grpc).
     * 主节点与所有 clone 共用同一组 path, 任一节点都能解析出完整三组.
     * 缺失 (无对应协议的节点 / 空值) 时回退到原版固定 path, 保证向后兼容
     * (旧节点 v2_path="srp-xhttp" 或为空均能正常工作).
     *
     * @param  \App\Http\Models\SsNode $node
     * @return array  ['xhttp'=>path, 'ws'=>path, 'grpc'=>servicename]
     */
    private function resolveClusterPaths($node)
    {
        $paths = [
            "xhttp" => "srp-xhttp",
            "ws" => "srp-ws",
            "grpc" => "srp-grpc",
        ];

        // 定位集群主节点: clone 通过 is_clone 指向主节点, 且只有主节点持有完整 node_ids.
        $main = $node->is_clone > 0 ? SsNode::find($node->is_clone) : $node;
        if (!$main) {
            $main = $node;
        }

        $nodeIdsRaw = $main->node_ids ?: (string) $main->id;
        $nodeIds = array_map(
            "intval",
            array_filter(explode(",", $nodeIdsRaw), "strlen"),
        );
        if (!in_array((int) $main->id, $nodeIds, true)) {
            $nodeIds[] = (int) $main->id;
        }

        foreach (SsNode::whereIn("id", $nodeIds)->get() as $c) {
            $net = $c->v2_net ?: "";
            if ($net === "xhttp" && $c->v2_path) {
                $paths["xhttp"] = $c->v2_path;
            } elseif ($net === "ws" && $c->v2_path) {
                $paths["ws"] = $c->v2_path;
            } elseif ($net === "grpc") {
                $svc = $c->v2_servicename ?: $c->v2_path;
                if ($svc) {
                    $paths["grpc"] = $svc;
                }
            }
        }

        return $paths;
    }

    /**
     * 从节点所属集群解析 xhttp-verify 模式的随机校验 token (UUID v4).
     * 主节点与所有 clone 共用同一 token, 任一 xhttp 节点都能解析出.
     * 返回 null 表示该集群未启用 xhttp-verify 模式 (不进行 Xhttp-Verify 校验).
     *
     * @param  \App\Http\Models\SsNode $node
     * @return string|null  UUID v4 或 null
     */
    private function resolveClusterXhttpVerify($node)
    {
        // 定位集群主节点: clone 通过 is_clone 指向主节点.
        $main = $node->is_clone > 0 ? SsNode::find($node->is_clone) : $node;
        if (!$main) {
            $main = $node;
        }

        $nodeIdsRaw = $main->node_ids ?: (string) $main->id;
        $nodeIds = array_map(
            "intval",
            array_filter(explode(",", $nodeIdsRaw), "strlen"),
        );
        if (!in_array((int) $main->id, $nodeIds, true)) {
            $nodeIds[] = (int) $main->id;
        }

        foreach (SsNode::whereIn("id", $nodeIds)->get() as $c) {
            if ($c->v2_net === "xhttp" && $c->v2_xhttp_verify) {
                return $c->v2_xhttp_verify;
            }
        }

        return null;
    }

    /**
     * 生成 per-node REALITY 密钥对 + shortId (配套三件套).
     *
     * 用 PHP libsodium 生成标准 X25519 密钥对 (与 `xray x25519` 输出完全兼容).
     *   - private: 32 字节原始标量, base64url 无填充 (43 字符) -> xray 服务端 privateKey
     *   - public:  32 字节曲线点, base64url 无填充 (43 字符) -> 客户端订阅 pbk
     *   - shortId: 8 hex 字符 (4 字节) -> 两端共用 (xray shortIds / 订阅 sid)
     *
     * @return array {private:string, public:string, shortId:string}
     */
    private function generateRealityKeys()
    {
        $kp = sodium_crypto_box_keypair();
        return [
            "private" => $this->base64UrlEncode(sodium_crypto_box_secretkey($kp)),
            "public"  => $this->base64UrlEncode(sodium_crypto_box_publickey($kp)),
            "shortId" => bin2hex(random_bytes(4)),
        ];
    }

    /**
     * base64url 编码 (无填充). xray reality 密钥的标准编码.
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    /**
     * 从节点所属集群解析 REALITY 密钥 (private/public/shortId).
     *
     * register 时一次性生成并写入主节点 + 所有 clone (同一物理节点共用同一组),
     * 故任一节点都能解析出. 优先读当前节点自身, 缺失时扫描集群兄弟节点兑底
     * (兼容旧数据 / 单节点重建场景). 非 vision-reality 集群返回空数组.
     *
     * @param  \App\Http\Models\SsNode $node
     * @return array  {private:string, public:string, shortId:string}; 无则空数组
     */
    private function resolveClusterReality($node)
    {
        // 优先读当前节点自身 (register 写入所有节点, 通常都有).
        if ($node->v2_reality_private && $node->v2_reality_pbk) {
            return [
                "private" => $node->v2_reality_private,
                "public"  => $node->v2_reality_pbk,
                "shortId" => $node->v2_reality_sid ?: "",
            ];
        }

        // 定位集群主节点: clone 通过 is_clone 指向主节点.
        $main = $node->is_clone > 0 ? SsNode::find($node->is_clone) : $node;
        if (!$main) {
            $main = $node;
        }

        $nodeIdsRaw = $main->node_ids ?: (string) $main->id;
        $nodeIds = array_map(
            "intval",
            array_filter(explode(",", $nodeIdsRaw), "strlen"),
        );
        if (!in_array((int) $main->id, $nodeIds, true)) {
            $nodeIds[] = (int) $main->id;
        }

        foreach (SsNode::whereIn("id", $nodeIds)->get() as $c) {
            if ($c->v2_reality_private && $c->v2_reality_pbk) {
                return [
                    "private" => $c->v2_reality_private,
                    "public"  => $c->v2_reality_pbk,
                    "shortId" => $c->v2_reality_sid ?: "",
                ];
            }
        }

        return [];
    }

    /**
     * 解析集群共用的 ECH 下载规格 (xhttp-cdn / xhttp-cdn-hy2 模式).
     *
     * 集群级: 仅依赖 root_domain, 主节点与所有 clone 共用同一规格.
     *   - 域名池 node_domain_pool 中该域名的 meta.ech 非空 → 用该自定义值;
     *   - 否则默认 `ech.{root_domain}+udp://1.1.1.1`.
     *
     * 格式 `<域名>+<DNS解析器URL>`:
     *   - 前段 ech.{root_domain} = 发布 ECHConfigList 的 DNS HTTPS 记录域名;
     *   - 后段 udp://1.1.1.1      = 拉取该记录用的干净 DNS 解析器 (避开被墙/被污染的本地解析).
     *
     * @param  string $rootDomain CDN 根域名 (已由 resolveDomainAffinity 选定)
     * @param  array  $sysConf    Helpers::systemConfig() 结果
     * @return string|null        ECH 规格; 无 rootDomain 时返回 null
     */
    private function resolveClusterEch($rootDomain, $sysConf)
    {
        if (empty($rootDomain)) {
            return null;
        }

        $parsed = $this->parseDomainPool($sysConf);
        $domainPool = $parsed["domainPool"];
        $meta = isset($domainPool[$rootDomain]) ? $domainPool[$rootDomain] : [];
        if (is_array($meta) && !empty($meta["ech"])) {
            return $meta["ech"];
        }

        // 默认: ech.{root_domain}+udp://1.1.1.1
        return "ech." . $rootDomain . "+udp://1.1.1.1";
    }

    private function v2NetToInboundTag($v2Net)
    {
        static $map = [
            "xhttp" => "proxy-xhttp",
            "hysteria2" => "proxy-hy2",
            "ws" => "proxy-ws",
            "grpc" => "proxy-grpc",
            "tcp" => "proxy-vision",
        ];
        return $map[$v2Net] ?? "proxy-" . $v2Net;
    }

    private function resolveDomainAffinity($reportedDomain, $sysConf, $cdnOnly = false)
    {
        $parsed = $this->parseDomainPool($sysConf);
        $domainPool = $parsed["domainPool"];
        $primaryDomain = $parsed["primaryDomain"];

        // CDN 模式: 仅在带 cdn:true 标记的域名中选择 (独立根域名, 隔离防封)
        if ($cdnOnly) {
            $cdnDomains = [];
            foreach ($domainPool as $domainName => $meta) {
                if (is_array($meta) && !empty($meta["cdn"])) {
                    $cdnDomains[$domainName] = $meta;
                }
            }
            if (empty($cdnDomains)) {
                Log::error(
                    "[Node API] resolveDomainAffinity: CDN 模式未找到 cdn:true 的域名池配置, 请在 node_domain_pool 中添加 CDN 域名 (含 zone_id; cf_token 可选, 跨账号时填写)",
                );
                return $primaryDomain;
            }
            $domainPool = $cdnDomains;
        }

        if ($reportedDomain) {
            $limit = $this->getDomainLimit($domainPool[$reportedDomain] ?? []);
            $count = DnsRecord::where("root_domain", $reportedDomain)->count();
            if ($count < $limit) {
                return $reportedDomain;
            }
        }

        foreach ($domainPool as $domainName => $meta) {
            $limit = $this->getDomainLimit($meta);
            $count = DnsRecord::where("root_domain", $domainName)->count();
            if ($count < $limit) {
                return $domainName;
            }
        }

        return $primaryDomain;
    }

    public function resolveDns(Request $request)
    {
        // DNS 记录同步已迁移到独立模块 NodeAddress\DnsSyncer (resolve_dns 统一入口).
        // 控制器仅做参数校验 + 委托, 不再含任何 CF / DNS 对账逻辑.
        // 无全局开关: DnsSyncer 仅对 clone ipv4 节点创建 A 记录 (连接域名→IP),
        // 主节点 + ipv6 节点直连 (跳过, 清理残留记录).
        $mainNodeId = $request->input("node_id");
        $force = (bool) $request->input("force", false);

        if (!$mainNodeId || !is_numeric($mainNodeId)) {
            Log::error("[Node API] resolveDns: invalid node_id", ["node_id" => $mainNodeId]);
            return response()->json(["status" => "error", "message" => "Invalid node_id"], 400);
        }

        $syncer = new \App\Services\NodeAddress\DnsSyncer();
        $result = $syncer->syncCluster((int) $mainNodeId, $force);
        $code = ($result["status"] === "error" && ($result["message"] ?? "") === "Node not found") ? 404 : 200;
        return response()->json($result, $code);
    }

    /**
     * 安全清理节点 DNS 记录 (节点回收时用). 委托给 DnsSyncer.
     */
    private function safeCleanupNodeDns($nodeId, $context = 'NodeApi')
    {
        $syncer = new \App\Services\NodeAddress\DnsSyncer();
        $syncer->cleanupNodeRecords($nodeId, 'NodeApi.' . $context);
    }


    private function parseServerField($server)
    {
        if (!$server || !strpos($server, ".")) {
            return null;
        }
        // server 可能是 IP (主节点 server=IP), IPv4 含 '.' 会被误当域名切分,
        // 必须排除 → 返回 null, 让调用方回退到 v2_sni (clusterHost 域名) 解析 root_domain.
        if (filter_var($server, FILTER_VALIDATE_IP) !== false) {
            return null;
        }
        $parts = explode(".", $server, 2);
        return ["subdomain" => $parts[0], "root_domain" => $parts[1]];
    }

    public function config(Request $request)
    {
        $nodeId = $request->input("node_id");
        $node = SsNode::find($nodeId);
        if (!$node) {
            return response()->json(
                ["status" => "error", "message" => "Node not found"],
                404,
            );
        }
        if ($node->status == 0) {
            return response()->json(
                ["status" => "error", "message" => "Node is offline"],
                403,
            );
        }

        // --- Template selection: strictly via v2_name ---
        $v2Name = $node->v2_name ?: "xhttp-hy2";
        if (!isset(self::V2_PROTOCOL_SLOTS[$v2Name])) {
            $v2Name = "xhttp-hy2";
        }
        $templatePath = resource_path("templates/xray/{$v2Name}.json");
        if (!file_exists($templatePath)) {
            return response()->json(
                ["status" => "error", "message" => "Template not found"],
                500,
            );
        }

        $rawTemplate = json_decode(file_get_contents($templatePath));
        $config = json_decode(json_encode($rawTemplate), true);

        $sysConf = Helpers::systemConfig();
        $fallbackHost = $sysConf["node_fallback_host"] ?? "npanel-nav.freessr.bid";

        $serverParts = $this->parseServerField($node->server);
        // server 非域名 (主节点 server=IP / ipv6 节点 server 为原生 ipv6 字面量) 时,
        // 从 v2_sni (clusterHost 域名) 解析 root_domain, 保证 xray/nginx 的证书路径 /
        // server_name 仍取自集群根域名.
        if (!$serverParts) {
            $serverParts = $this->parseServerField($node->v2_sni);
        }
        $nodeDomain = $serverParts
            ? $serverParts["root_domain"]
            : $node->server;

        $expanded = $this->expandProtocols($v2Name);
        $inboundTags = array_values(array_unique(array_map(function ($proto) {
            return "proxy-" . $proto;
        }, $expanded)));

        // 随机 path: 从集群节点解析, 保证 xray 下发与 nginx / 订阅 path 完全一致.
        // 缺失时回退到原版固定 path (srp-*), 向后兼容旧节点.
        $paths = $this->resolveClusterPaths($node);

        // vision-reality: per-node REALITY 密钥从集群解析.
        //   private+sid -> xray realitySettings, serverName -> 节点自身域名 (偷自己).
        // 非 vision-reality 节点 resolveClusterReality 返回空数组, 占位符赋空串,
        // 而模板不含这些占位符, str_replace 是空操作, 安全.
        $reality = $this->resolveClusterReality($node);

        $vars = [
            "__v2Fallback__" => "127.0.0.1",
            "__nodeDomain__" => $nodeDomain,
            "__HYSTERIA_URL__" => "http://" . $fallbackHost . ":80",
            "__xhttpPath__" => $paths["xhttp"],
            "__wsPath__" => $paths["ws"],
            "__v2ServiceName__" => $paths["grpc"],
            "__xhttpVerify__" => $this->resolveClusterXhttpVerify($node),
            "__xhttpPort__" => 10013,
            "__wsPort__" => 10011,
            "__grpcPort__" => 10012,
            "__hy2Port__" => (int) ($node->v2_port ?: 443),
            "__visionPort__" => (int) ($node->v2_port ?: 443),
            "__httpProxyHost__" => $fallbackHost,
            "__dbHost__" => env("DB_REMOTE_HOST", env("DB_HOST", "127.0.0.1")),
            "__dbUser__" => env("DB_USERNAME", "root"),
            "__dbPassword__" => env("DB_PASSWORD", ""),
            "__dbName__" => env("DB_DATABASE", "npanel"),
            // REALITY 占位符 (仅 vision-reality.json 模板使用):
            //   privateKey/shortId 仅服务端持有 (从 DB 列读);
            //   serverName 偷自己 = 节点完整 host (s{id}.rootDomain), 与 nginx 8443
            //   泛域名证书匹配, 且必须等于客户端 SNI (订阅层 applySniPrefix 对 reality 跳过加前缀).
            "__realityPrivateKey__" => isset($reality["private"]) ? $reality["private"] : "",
            "__realityShortId__" => isset($reality["shortId"]) ? $reality["shortId"] : "",
            // reality serverNames 必须等于客户端 SNI (v2_sni); host/sni 复用后 clone 的
            // v2_sni = clusterHost ≠ server, 故取 v2_sni 而非 server, 否则 reality 握手失败.
            "__realityServerName__" => $node->v2_sni ?: ($node->server ?: $nodeDomain),
        ];

        $config = $this->injectVariables($config, $vars);

        // --- Clone node: keep only the matching inbound ---
        if ($node->is_clone > 0) {
            $keepTag = $this->v2NetToInboundTag($node->v2_net);
            $config["inbounds"] = array_values(
                array_filter($config["inbounds"], function ($ib) use (
                    $keepTag
                ) {
                    return ($ib["tag"] ?? "") === "api" ||
                        ($ib["tag"] ?? "") === $keepTag;
                }),
            );
            $inboundTags = [$keepTag];
            $expanded = [$keepTag];
        }

        if (isset($config["ssrpanel"])) {
            $config["ssrpanel"]["nodeId"] = (int) $node->id;
            $config["ssrpanel"]["user"]["inboundTags"] = $inboundTags;

            if (in_array("proxy-vision", $inboundTags)) {
                $config["ssrpanel"]["user"]["flows"] = [
                    "proxy-vision" => "xtls-rprx-vision",
                ];
            } else {
                unset($config["ssrpanel"]["user"]["flows"]);
            }
        }

        if ($node->node_unlock) {
            $this->applyUnlocks($config, $node->node_unlock);
        }

        // --- HY2 Direct IP Override ---
        // Derive protocol from v2_net (NOT v2_name)
        $v2Net = $node->v2_net ?: "";
        if ($v2Net === "hysteria2") {
            $directIp = $node->ip ?: $node->ipv6;
            if ($directIp) {
                Log::debug(
                    "[Node API] config: HY2 detected via v2_net, overriding server to direct IP",
                    [
                        "node_id" => $node->id,
                        "v2_net" => $v2Net,
                        "direct_ip" => $directIp,
                        "original_server" => $node->server,
                    ],
                );
                $this->overrideServerToIp($config, $directIp);
            } else {
                Log::warning(
                    "[Node API] config: HY2 detected but node has no IP/IPv6",
                    [
                        "node_id" => $node->id,
                    ],
                );
            }
        }

        $config = $this->restoreJsonObjectTypes($config, $rawTemplate);

        $logVars = $vars;
        $logVars["__dbPassword__"] = "******";
        $logVars["__realityPrivateKey__"] = "******";

        Log::debug("[Node API] 配置下发", [
            "node_id" => $node->id,
            "template" => $v2Name,
            "v2_net" => $v2Net,
            "variables" => $logVars,
            "unlocks" => $node->node_unlock
                ? count(explode(",", $node->node_unlock))
                : 0,
            "hy2_override" => $v2Net === "hysteria2",
        ]);

        return response()->json(
            $config,
            200,
            [],
            JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        );
    }

    public function nginxConfig(Request $request)
    {
        $nodeId = $request->input("node_id");
        $node = SsNode::find($nodeId);
        if (!$node) {
            return response("Node not found", 404);
        }
        if ($node->status == 0) {
            return response("Node is offline", 403);
        }

        $mainNode = $node->is_clone > 0 ? SsNode::find($node->is_clone) : $node;
        if (!$mainNode) {
            return response("Main node not found", 500);
        }

        $v2Name = $mainNode->v2_name ?: "xhttp-hy2";
        if (!isset(self::V2_PROTOCOL_SLOTS[$v2Name])) {
            $v2Name = "xhttp-hy2";
        }

        $templatePath = resource_path("templates/nginx/{$v2Name}.conf");
        if (!file_exists($templatePath)) {
            return response("Template not found", 500);
        }

        $conf = file_get_contents($templatePath);

        $serverParts = $this->parseServerField($node->server);
        // server 非域名 (主节点 server=IP / ipv6 节点 server 为原生 ipv6 字面量) 时,
        // 从 v2_sni (clusterHost 域名) 解析 root_domain, 保证 nginx 证书路径 / server_name
        // 仍取自集群根域名.
        if (!$serverParts) {
            $serverParts = $this->parseServerField($node->v2_sni);
        }
        $rootDomain = $serverParts ? $serverParts["root_domain"] : "";

        $nodePort = (int) ($mainNode->v2_port ?: 443);

        $fallbackHost = Helpers::systemConfig()["node_fallback_host"] ?? "npanel-nav.freessr.bid";

        // 随机 path: 从集群节点解析, 保证 nginx 下发与 xray / 订阅 path 完全一致.
        // 缺失时回退到原版固定 path (srp-*), 向后兼容旧节点.
        $paths = $this->resolveClusterPaths($node);

        // xhttp-verify 模式的随机 UUID v4 校验 token (集群共用);
        // 仅该模式非空, nginx 层据此校验客户端请求头 Xhttp-Verify. 其他模式为空 (不校验).
        $xhttpVerify = $this->resolveClusterXhttpVerify($node) ?: "";

        $conf = str_replace(
            [
                "__nodeDomainRegex__",
                "__nodeDomain__",
                "__xhttpPath__",
                "__xhttpVerify__",
                "__xhttpPort__",
                "__wsPath__",
                "__wsPort__",
                "__v2ServiceName__",
                "__grpcPort__",
                "__httpProxyHost__",
                "__nginxPort__",
            ],
            [
                $rootDomain,
                $rootDomain,
                $paths["xhttp"],
                $xhttpVerify,
                "10013",
                $paths["ws"],
                "10011",
                $paths["grpc"],
                "10012",
                $fallbackHost,
                (string)$nodePort,
            ],
            $conf,
        );

        Log::debug("[Node API] nginx_config 下发", [
            "node_id" => $node->id,
            "main_node_id" => $mainNode->id,
            "v2_name" => $v2Name,
            "root_domain" => $rootDomain,
        ]);

        return response($conf, 200)->header("Content-Type", "text/plain");
    }

    private function overrideServerToIp(&$config, $ip)
    {
        if (!is_array($config)) {
            return;
        }
        foreach ($config as $key => &$value) {
            if (is_array($value)) {
                $this->overrideServerToIp($value, $ip);
            } elseif (is_string($value) && $key === "server") {
                $value = $ip;
            }
        }
    }

    private function injectVariables($data, $vars)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->injectVariables($value, $vars);
            }
            return $data;
        }

        if (is_string($data)) {
            if (isset($vars[$data])) {
                return $vars[$data];
            }
            foreach ($vars as $k => $v) {
                if (is_string($v) || is_numeric($v)) {
                    $data = str_replace($k, (string) $v, $data);
                }
            }
        }
        return $data;
    }

    private function restoreJsonObjectTypes($data, $template)
    {
        if ($template instanceof \stdClass) {
            $result = [];
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $tplValue = property_exists($template, $key)
                        ? $template->$key
                        : null;
                    $result[$key] = $this->restoreJsonObjectTypes(
                        $value,
                        $tplValue,
                    );
                }
            }
            return (object) $result;
        }

        if (is_array($template)) {
            if (is_array($data)) {
                foreach ($data as $i => $value) {
                    $tplValue = isset($template[$i]) ? $template[$i] : null;
                    $data[$i] = $this->restoreJsonObjectTypes(
                        $value,
                        $tplValue,
                    );
                }
            }
            return $data;
        }

        if (is_array($data)) {
            $isAssoc =
                !empty($data) &&
                array_keys($data) !== range(0, count($data) - 1);
            if ($isAssoc) {
                $result = [];
                foreach ($data as $key => $value) {
                    $result[$key] = $this->restoreJsonObjectTypes($value, null);
                }
                return (object) $result;
            }

            foreach ($data as $i => $value) {
                $data[$i] = $this->restoreJsonObjectTypes($value, null);
            }
            return $data;
        }

        return $data;
    }

    private function applyUnlocks(&$config, $unlockStr)
    {
        $unlocks = [];
        parse_str(str_replace(",", "&", $unlockStr), $unlocks);

        $sysConf = Helpers::systemConfig();

        foreach ($unlocks as $key => $value) {
            $valLower = strtolower(trim((string) $value));
            // 匹配以 0, no, false, off 开头的字符串，说明节点无法解锁，需要下发远程解锁配置
            $isEnabled = preg_match("/^(0|no|false|off)/", $valLower) === 1;

            if ($isEnabled) {
                $service = strtolower(str_replace(["unlock", " "], "", $key));

                if ($service === "chatgpt") {
                    $service = "openai";
                }

                $templatePath = resource_path(
                    "templates/xray/unlock/{$service}.json",
                );

                if (file_exists($templatePath)) {
                    $rawTemplate = file_get_contents($templatePath);

                    $addr = $sysConf["unlock_{$service}_address"] ?? "";
                    $port = $sysConf["unlock_{$service}_port"] ?? 8388;
                    $pwd = $sysConf["unlock_{$service}_password"] ?? "";
                    $method =
                        $sysConf["unlock_{$service}_method"] ??
                        "chacha20-ietf-poly1305";

                    if (empty($addr)) {
                        Log::warning(
                            "[Node API] Unlock service {$service} enabled but address is missing in DB.",
                        );
                        continue;
                    }

                    $rawTemplate = str_replace(
                        [
                            "__address__",
                            "__port__",
                            "__password__",
                            "__method__",
                        ],
                        [$addr, (string) $port, $pwd, $method],
                        $rawTemplate,
                    );

                    $unlockData = json_decode($rawTemplate, true);
                    if ($unlockData) {
                        if (!empty($unlockData["outbounds"])) {
                            foreach ($unlockData["outbounds"] as $outbound) {
                                $config["outbounds"][] = $outbound;
                            }
                        }
                        if (!empty($unlockData["routing"]["rules"])) {
                            foreach ($unlockData["routing"]["rules"] as $rule) {
                                $config["routing"]["rules"][] = $rule;
                            }
                        }
                    }
                } else {
                    Log::warning(
                        "[Node API] Unlock service {$service} has no template, skipping geosite rule.",
                    );
                }
            }
        }
    }

    public function status(Request $request)
    {
        $nodeId = $request->input("node_id");
        $rawRx = (float) $request->input("raw_rx", 0);
        $rawTx = (float) $request->input("raw_tx", 0);

        $node = SsNode::find($nodeId);
        if (!$node) {
            return response()->json(
                ["status" => "error", "message" => "Node not found"],
                404,
            );
        }

        if ($node->node_rxtx == "rxtx") {
            $rawTotal = ($rawRx + $rawTx) / 2;
        } else {
            $rawTotal = $rawTx;
        }

        $lastRaw = $node->last_raw_total ?: 0;
        if ($lastRaw == 0) {
            $incremental = 0;
        } elseif ($rawTotal < $lastRaw) {
            $incremental = $rawTotal;
        } else {
            $incremental = $rawTotal - $lastRaw;
        }
        $node->last_raw_total = $rawTotal;

        $node->traffic_used += $incremental;

        // 同步写入剩余流量（供面板展示和旧版逻辑使用）
        $node->traffic_left = max(0, $node->traffic_limit - $node->traffic_used);
        $node->server_uptime = (int) $request->input(
            "server_uptime",
            $node->server_uptime,
        );
        $node->bandwidth =
            (int) $request->input("node_bandwidth", $node->bandwidth) +
            $node->level;
        $node->heartbeat_at = date("Y-m-d H:i:s");

        // 基于 reset_day 计算已过天数和剩余天数（remaining 不含 today）
        $resetDay = (int) $node->reset_day;
        $today = (int) date("j");
        $daysInMonth = (int) date("t");

        if ($resetDay > 0) {
            $rd = min($resetDay, $daysInMonth);
            if ($today >= $rd) {
                $daysElapsed = max(1, $today - $rd + 1);
                $daysRemaining = max(1, $daysInMonth - $today + $rd);
            } else {
                $lastMonth = (int) date("n") - 1 ?: 12;
                $lastYear = (int) date("Y") - ($lastMonth === 12 ? 1 : 0);
                $daysInLastMonth = (int) date("t", mktime(0, 0, 0, $lastMonth, 1, $lastYear));
                $daysElapsed = max(1, $daysInLastMonth - min($resetDay, $daysInLastMonth) + $today + 1);
                $daysRemaining = max(1, $rd - $today);
            }
        } else {
            $daysElapsed = max(1, $today);
            $daysRemaining = max(1, $daysInMonth - $today);
        }

        // 每日已用流量 = 已用总量 / 已过天数
        $node->traffic_used_daily = (int) ($node->traffic_used / $daysElapsed);

        // 每日剩余流量 = 剩余流量 / 剩余天数
        $node->traffic_left_daily = (int) (max(0, $node->traffic_left) / $daysRemaining);

        $avgUsed = $node->traffic_used / max($daysElapsed, 1);
        $avgRemaining = max(0, $node->traffic_left) / max($daysRemaining, 1);
        $node->node_health = $avgUsed > $avgRemaining ? 0 : 1;

        $trafficLeft = $node->traffic_limit - $node->traffic_used;
        $threshold = 120 * 1024 * 1024 * 1024; // 120GB
        if ($trafficLeft >= $threshold) {
            if ($node->status != 1) {
                Log::info("[Node API] 节点自动恢复上线", [
                    "node_id" => $nodeId,
                    "traffic_left_gb" => round($trafficLeft / 1024 / 1024 / 1024, 2),
                ]);
            }
            $node->status = 1;
        } else {
            $node->status = 0;
        }

        $node->save();

        // Sync cluster-wide state to all clone nodes.
        // clone 是同一物理机的分身, 不独立调用 status(); main 上报后必须同步
        // 集群共享状态, 否则 main 熔断下线 (status=0) 时 clone 仍显示在线,
        // 订阅层 (SubscribeController 按 status=1 过滤) 会持续下发失效 clone.
        $this->syncClusterStatus($node);

        $logData = [
            "node_id" => $nodeId,
            "raw_rx" => $rawRx,
            "raw_tx" => $rawTx,
            "incremental" => $incremental,
            "used" => $node->traffic_used,
            "limit" => $node->traffic_limit,
            "health" => $node->node_health,
        ];

        if ($node->status == 0) {
            Log::warning(
                "[Node API] 节点熔断下线",
                array_merge($logData, [
                    "reason" => "Remaining traffic < 120GB",
                    "remaining" => $node->traffic_limit - $node->traffic_used,
                ]),
            );
        } elseif ($node->node_health == 0) {
            Log::warning(
                "[Node API] 节点健康度预警",
                array_merge($logData, [
                    "reason" => "Consumption rate exceeds remaining budget",
                ]),
            );
        } else {
            Log::info("[Node API] 状态上报", $logData);
        }

        return response()->json([
            "status" => "success",
            "node_status" => $node->status,
            "traffic_used" => $node->traffic_used,
            "traffic_left" => $node->traffic_limit - $node->traffic_used,
            "traffic_used_daily" => $node->traffic_used_daily,
            "traffic_left_daily" => $node->traffic_left_daily,
        ]);
    }

    /**
     * 把 main 节点上报后的集群共享状态同步给所有 clone 节点.
     *
     * clone (is_clone > 0) 是同一物理机的协议/IP 分身, 不独立调用 status();
     * 其状态完全由 main 上报时同步. 不同步 status 会导致: main 流量耗尽熔断
     * (status=0) 后 clone 仍保持 status=1, 订阅层继续下发失效 clone 节点.
     *
     * 同步字段 (反映物理机运行状态, main 与 clone 必须一致):
     *   - heartbeat_at : 存活心跳
     *   - status       : 上线/下线 (流量 < 120GB 熔断)  ← 本次修复核心
     *   - node_health  : 健康度 (消费速率 vs 剩余预算)
     *   - bandwidth    : 物理机带宽
     *
     * 不同步:
     *   - traffic_*      : clone 不独立计费, 流量字段不参与订阅过滤.
     *   - last_raw_total : 网卡计数器增量基准, 仅 main 需要 (clone 不上报).
     */
    private function syncClusterStatus($node)
    {
        if ((int) $node->is_clone !== 0) {
            return;
        }

        SsNode::where('is_clone', $node->id)->update([
            'heartbeat_at' => $node->heartbeat_at,
            'status'       => $node->status,
            'node_health'  => $node->node_health,
            'bandwidth'    => $node->bandwidth,
        ]);
    }
}
