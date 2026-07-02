<?php

namespace App\Services\NodeAddress;

/**
 * 节点地址解析模块 (订阅统一入口, 纯函数无副作用).
 *
 * ──────────────────────────────────────────────────────────────────
 * 设计: register 阶段按 [ipv4×N, ipv6×N] 槽位展开, 每个节点的 server 域名前缀编码了
 * ip 栈信息 (register 之后, DNS 处理之前已确定):
 *   - ipv6 节点: server = 原生 ipv6 字面量 (含 ':'). (旧格式 server = `{random8}ipv6n{id}.domain`
 *     主 / `ipv6n{id}.domain` clone — 含 `ipv6n`, 仍兼容识别.)
 *   - ipv4 节点: server = `{random8}n{id}.domain` (主) / `n{id}.domain` (clone) — 不含 ':' 也不含 `ipv6n`.
 * server 字段本身就是节点的“address”标志 (是 ipv4 还是 ipv6). 每个节点 (主+clone) 同时
 * 存储物理节点的 ip (IPv4) 与 ipv6 (IPv6), 不再通过 ip 缺失判断是否 ipv6.
 * DNS 记录的创建交给独立的 DnsSyncer 模块, 在 resolve_dns 端点统一处理
 * (把 clone ipv4 节点的连接域名解析到节点 IP). 本模块只在订阅时惰性解析
 * "客户端连接地址", 与 DNS 记录同步完全解耦.
 *
 * address 解析规则 (无全局开关, 固定行为):
 *   1. ipv6 节点 (server 含 ':' 即 ipv6, 或旧格式含 `ipv6n`) → 始终直连 ipv6 (不做 host 解析).
 *   2. CDN 节点 (v2_name=xhttp-cdn / xhttp-cdn-hy2 的 xhttp 槽位, 或 v2_cdn='cf')
 *      → CF 优选 IP (CSV 来源; CSV 空 → 降级节点 IP). CDN 走 CF 边缘, 独立于 main/clone 角色.
 *      注: xhttp-cdn-hy2 集群里的 hysteria2 槽位是 UDP 直连, 不走 CDN (CF 仅代理 HTTP/HTTPS).
 *   3. 主节点 (is_clone == 0) → 直连 IP (host/sni 复用, 不创建 DNS 记录).
 *   4. clone ipv4 节点 → 连接域名 server (n{cloneid}.domain; resolve_dns 已
 *      创建 A 记录将该域名解析到节点 IP).
 *
 * host/sni 复用: 主节点与所有 clone 共用同一 host/sni (clusterHost), 但 clone
 * 的连接 address 仍按节点独立 (n{cloneid}.domain), 与 host/sni 解耦.
 *
 * @see \App\Services\NodeAddress\OptimizedIpPool  CDN 节点的 CF 优选 IP 来源 (CSV)
 * @see \App\Services\NodeAddress\DnsSyncer       DNS 记录同步 (resolve_dns 端点)
 */
class NodeAddressService
{
    /** CDN 模式对应的 v2_name 集合 (域名亲和 + CF 优选 IP 用).
     *  - xhttp-cdn:     纯 xhttp 走 CF CDN.
     *  - xhttp-cdn-hy2: xhttp 走 CF CDN + hy2 直连 UDP; 仅 xhttp 槽位是 CDN 节点
     *                  (isCdnNode 据 v2_net 排除 hysteria2), hy2 槽位始终直连.
     */
    const CDN_V2_NAMES = ['xhttp-cdn', 'xhttp-cdn-hy2'];

    /** @return array CDN 模式的 v2_name 列表 (AutoRotateCdnIp 查询用) */
    public static function cdnV2Names()
    {
        return self::CDN_V2_NAMES;
    }

    /** @return string CDN 模式的主 v2_name (向后兼容, 等价首个) */
    public static function cdnV2Name()
    {
        return self::CDN_V2_NAMES[0];
    }

    /**
     * v2_name 是否为 CDN 模式 (整个集群意图走 CDN: 选 CDN 根域名 + xhttp 槽位解析 CF IP).
     *
     * 注意: 这是「集群级」意图标记 (register 域名亲和用). 集群内具体某个节点是否走 CDN
     * 由 isCdnNode() 进一步按 v2_net 判定 (xhttp-cdn-hy2 的 hysteria2 槽位不算 CDN 节点).
     *
     * @param  string $v2Name
     * @return bool
     */
    public static function isCdnV2Name($v2Name)
    {
        return in_array($v2Name, self::CDN_V2_NAMES, true);
    }

    /**
     * 解析 ECH 下载规格字符串 `ech.{root_domain}+udp://1.1.1.1`.
     *
     * 格式 `<域名>+<DNS解析器URL>` (register 时写入 v2_ech, 集群共用):
     *   - 前段 ech.{root_domain} = 发布 ECHConfigList 的 DNS HTTPS 记录域名;
     *   - 后段 udp://1.1.1.1      = 拉取该记录用的干净 DNS 解析器 (避开被墙/被污染的本地解析).
     * 订阅层据此向客户端下发 ECH 配置 (隐藏真实 SNI, 抗域名封锁/干扰).
     *
     * @param  mixed $ech
     * @return array|null  ['domain'=>string, 'server'=>string]; 空值/格式不对返回 null
     */
    public static function parseEchSpec($ech)
    {
        if (!is_string($ech) || $ech === '') {
            return null;
        }
        // 以第一个 '+' 为界: 前段域名, 后段 URL. (域名不含 '+', URL scheme 用 '://'.)
        $plus = strpos($ech, '+');
        if ($plus === false) {
            return null;
        }
        $domain = substr($ech, 0, $plus);
        $server = substr($ech, $plus + 1);
        if ($domain === '' || $server === '') {
            return null;
        }
        return ['domain' => $domain, 'server' => $server];
    }

    /**
     * 节点是否为 ipv6 节点 (以 server 域名前缀为准).
     *
     * server 字段本身就是节点的 address 标志 (register 之后, DNS 处理之前已确定):
     * ipv6 节点的 server 即原生 ipv6 字面量 (含 ':'); 旧格式为 server subdomain 含
     * `ipv6n` 标识 (主节点 `{random8}ipv6n{id}` / clone `ipv6n{id}`). ipv4 节点 server 为
     * 域名 (`{random8}n{id}` / `n{id}`), 不含 ':' 也不含 `ipv6n`.
     * 每个节点同时存储 ip+ipv6 (物理节点双栈), 不再通过 ip 字段缺失判断是否 ipv6.
     * ipv6 节点的连接地址始终为 ipv6, 不做 host 解析 (直连 ipv6):
     *   - resolveAddress 恒返回 ipv6; DnsSyncer 跳过 ipv6 节点 (不创建 DNS 记录).
     *
     * @param  mixed $node
     * @return bool
     */
    public static function isIpv6Node($node)
    {
        if (!$node || empty($node->server)) {
            return false;
        }
        $server = (string) $node->server;
        // 新格式: ipv6 节点 server = 原生 ipv6 字面量 (含 ':'). 域名 / ipv4 不含 ':'.
        if (strpos($server, ':') !== false) {
            return true;
        }
        // 旧格式 (向后兼容): ipv6 节点 server subdomain 含 `ipv6n` 标识.
        $subdomain = explode('.', $server, 2);
        return strpos($subdomain[0], 'ipv6n') !== false;
    }

    /**
     * 节点是否为主节点 (is_clone == 0).
     *
     * host/sni 复用机制下, 主节点与所有 clone 共用同一 host/sni; 主节点连接地址恒为 IP
     * (不创建 DNS 记录, DnsSyncer 跳过), 仅 clone 的 ipv4 节点解析为连接域名.
     * 独立节点 (无 clone) 也视为自身主节点, 同样直连 IP.
     *
     * @param  mixed $node
     * @return bool
     */
    public static function isMainNode($node)
    {
        return $node && isset($node->is_clone) && (int) $node->is_clone === 0;
    }

    /**
     * 节点是否"意图"走 CDN (历史标记 v2_name=xhttp-cdn / xhttp-cdn-hy2 的 xhttp 槽位, 或 v2_cdn=cf).
     *
     * 注意: 这是节点级判定 (resolveAddress 返回 CF IP 用). hysteria2 传输槽位永远不是
     * CDN 节点 —— hy2 是 UDP 直连, CF CDN 仅代理 HTTP/HTTPS, 无法中继 UDP.
     * 故 xhttp-cdn-hy2 集群里只有 xhttp 槽位返回 true, hy2 槽位返回 false (直连 IP).
     * DNS 记录行为由 DnsSyncer 按节点角色决定, 不由此方法决定.
     *
     * @param  mixed $node
     * @return bool
     */
    public static function isCdnNode($node)
    {
        if (!$node) {
            return false;
        }
        // hysteria2 是 UDP 直连, CF CDN 无法中继 UDP → hy2 槽位永远不走 CDN.
        // (xhttp-cdn-hy2 集群: xhttp 槽位走 CDN, hy2 槽位直连)
        if (isset($node->v2_net) && $node->v2_net === 'hysteria2') {
            return false;
        }
        if (isset($node->v2_name) && self::isCdnV2Name($node->v2_name)) {
            return true;
        }
        return isset($node->v2_cdn) && $node->v2_cdn === 'cf';
    }

    /**
     * 解析节点的客户端连接地址 (订阅统一入口, 纯函数无副作用, 无全局开关).
     *
     * 解析顺序 (前者优先):
     *   1. ipv6 节点 (server 含 ':' 即 ipv6, 或旧格式含 `ipv6n`) → 始终直连 ipv6.
     *   2. CDN 节点 → CF 优选 IP (CSV 空 → 降级节点 IP).
     *   3. 主节点 (is_clone=0) → 直连 IP.
     *   4. clone ipv4 节点 → 连接域名 server (resolve_dns 创建 A 记录解析到 IP).
     *
     * @param  mixed $node  须含 server / ip / ipv6 / is_clone / v2_* 属性
     * @return string
     */
    public static function resolveAddress($node)
    {
        // 1. ipv6 节点: 始终直连 ipv6, 不做 host 解析.
        //    新格式 server 已是原生 ipv6 字面量; 旧格式 server 为 ipv6n{id}.domain → 取 ipv6 列.
        if (self::isIpv6Node($node)) {
            $server = (string) $node->server;
            return strpos($server, ':') !== false ? $server : (string) $node->ipv6;
        }

        // 2. CDN 节点: 走 CF 边缘 IP (CSV 来源), 独立于 main/clone 角色.
        if (self::isCdnNode($node)) {
            $ip = self::resolveCdnIp($node);
            if ($ip !== '') {
                return $ip;
            }
            // CSV 为空 → 降级到节点 IP (保证节点仍可用, 不至于订阅空地址)
            return !empty($node->ip) ? $node->ip : (string) $node->server;
        }

        // 3. 主节点 (is_clone == 0): 始终直连 IP, 不做 host 解析.
        //    host/sni 复用机制下, 主节点与所有 clone 共用同一 host/sni, 主节点不创建
        //    DNS 记录 (DnsSyncer 跳过), 故恒返回 IP, 降低整个集群的 DNS 解析数量.
        if (self::isMainNode($node)) {
            if (!empty($node->ip)) {
                return $node->ip;
            }
            return (string) $node->server;
        }

        // 4. clone ipv4 节点 → 连接域名 (server).
        //    resolve_dns 阶段由 DnsSyncer 创建 A 记录, 将该域名解析到节点 IPv4.
        //    该域名只需能解析出 IP 即可, 可与 TLS host (v2_host/v2_sni) 不同 (domain-fronting).
        return self::resolveConnectDomain($node);
    }

    /**
     * 把连接地址包装成 URI 安全形式 (URI scheme 用: vless:// trojan:// hysteria2://).
     *
     * resolveAddress 返回裸地址 (域名 / IPv4 / IPv6 字面量), 供 clash / sing-box / loon
     * 等结构化字段直接使用. 但在 URI scheme 中, IPv6 地址必须用方括号包裹 (RFC 3986),
     * 否则其内部的 ':' 与端口分隔符 ':' 冲突, 客户端无法解析出 host / port / uuid.
     * 本方法对未包裹的 IPv6 字面量 (含 ':') 加 [...] 包裹, 其余原样返回.
     *
     * 用法: $addr = resolveAddress($node); $uri = 'vless://'.wrapIpv6ForUri($addr).':443';
     *
     * @param  mixed $addr  resolveAddress 的返回值 (裸地址)
     * @return string  URI 安全地址 (IPv6 加方括号, 其余原样)
     */
    public static function wrapIpv6ForUri($addr)
    {
        $addr = (string) $addr;
        // 仅对未包裹方括号的 IPv6 字面量 (含 ':') 加括号; 域名 / IPv4 / 已包裹的保持原样.
        if ($addr !== '' && $addr[0] !== '[' && strpos($addr, ':') !== false) {
            return '[' . $addr . ']';
        }
        return $addr;
    }

    /**
     * 解析连接域名 (clone ipv4 节点的连接目标).
     *
     * 默认 = 节点 server 域名 (n{cloneid}.domain). 该域名只需能解析出 IP 即可,
     * 可与 v2_host / v2_sni (TLS 身份) 不同 —— 这正是 domain-fronting 的原理:
     * 连接域名 (解析出节点 IP) 与 TLS host 互相独立.
     *
     * @param  mixed $node
     * @return string
     */
    public static function resolveConnectDomain($node)
    {
        return (string) $node->server;
    }

    /**
     * 解析 CDN 节点的 CF 优选 IP.
     *
     * 优先用节点缓存的 v2_cdn_ip (由 autoRotateCdnIp 每日从 CSV 写入),
     * 否则从 CSV 实时按 nodeId 错峰选取 (首次订阅 / 未轮换时).
     *
     * @param  mixed $node
     * @return string  IP 或空串 (CSV 为空且无缓存)
     */
    public static function resolveCdnIp($node)
    {
        if (!empty($node->v2_cdn_ip)) {
            return $node->v2_cdn_ip;
        }
        $nodeId = isset($node->id) ? $node->id : null;
        return OptimizedIpPool::pick($nodeId, null);
    }

    /**
     * 为新 CDN 节点分配初始 CF 优选 IP (CSV 按 nodeId 错峰).
     *
     * 注意: register 不调用此方法 (保持 register 纯净, address=IP).
     * 仅供需要预填缓存的场景 (如手动初始化 / 迁移) 使用.
     *
     * @param  int|null $nodeId
     * @return string
     */
    public static function pickInitialCdnIp($nodeId)
    {
        return OptimizedIpPool::pick($nodeId, null);
    }

    /**
     * 轮换: 为 CDN 节点选取下一个 CF 优选 IP (前进一格, 与当前不同).
     *
     * 供 autoRotateCdnIp 每日调用: CSV 刷新后或单纯前进一格都能带来变化.
     *
     * @param  int|null $nodeId
     * @param  string   $currentIp 当前 IP
     * @return string
     */
    public static function pickNextCdnIp($nodeId, $currentIp)
    {
        return OptimizedIpPool::pick($nodeId, $currentIp);
    }
}
