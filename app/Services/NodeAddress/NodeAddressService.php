<?php

namespace App\Services\NodeAddress;

/**
 * 节点地址 + DNS 解析统一模块.
 *
 * ──────────────────────────────────────────────────────────────────
 * 设计目标: 将"客户端连接地址"的决策 + "DNS 记录同步" 从节点注册系统中彻底解耦.
 *   - register 只负责节点身份 (ID / IP / 协议预置), address 原生 = IP, 不碰 DNS.
 *   - 本模块在订阅 / resolve_dns 时, 根据全局开关决定 address + 是否创建 DNS 记录.
 *   - 节点注册系统不再被 CDN / 域名地址逻辑污染.
 * ──────────────────────────────────────────────────────────────────
 *
 * 全局开关 env('NODE_ADDRESS_MODE'):
 *   ip   (功能0): address = 节点 IP, 不创建 DNS 记录 (register 默认行为)
 *   dns  (功能1): 创建 A 记录解析 host 到 CF, ipv4 节点 address 改为 domain 模式.
 *                 只要 domain 能解析出正确 IP 即可, 不校验其 root domain 是否与
 *                 TLS host (v2_host/v2_sni) 的 root domain 一致 (domain-fronting).
 *   cdn  (功能2): 解析 host 到 CF (灰云), ipv4 节点 address 改为 CF 优选 IP (CSV 来源).
 *
 * ipv6 单栈节点例外 (优先于全局开关): address 始终 = ipv6, 不做 host 解析 (直连 ipv6).
 *   - register 按 [ipv4×N, ipv6×N] 槽位展开, ipv6 槽位的节点仅填 ipv6 (ip 空).
 *   - resolveAddress 对 ipv6 节点恒返回 ipv6; DnsSyncer 跳过 ipv6 节点 (不创建 DNS 记录).
 *
 * 主节点 (is_clone == 0) 也优先于全局开关: address 始终 = IP (不做 host 解析).
 *   - host/sni 复用机制下, 主节点与所有 clone 共用同一 host/sni; 主节点不创建 DNS 记录
 *     (DnsSyncer 跳过), 恒直连 IP, 降低整个集群的 DNS 解析数量.
 *   - 仅 clone 的 ipv4 节点在 dns/cdn 模式下转域名/CDN IP.
 *
 * @see \App\Services\NodeAddress\OptimizedIpPool  F2 的 IP 来源 (CSV)
 * @see \App\Services\NodeAddress\DnsSyncer       DNS 记录同步 (CF)
 */
class NodeAddressService
{
    /** CDN 模式对应的 v2_name (历史标记, 域名亲和用). */
    const CDN_V2_NAME = 'xhttp-cdn';

    const MODE_IP = 'ip';
    const MODE_DNS = 'dns';
    const MODE_CDN = 'cdn';

    /** @return string CDN 模式的 v2_name */
    public static function cdnV2Name()
    {
        return self::CDN_V2_NAME;
    }

    /**
     * v2_name 是否为 CDN 模式.
     *
     * @param  string $v2Name
     * @return bool
     */
    public static function isCdnV2Name($v2Name)
    {
        return $v2Name === self::CDN_V2_NAME;
    }

    /**
     * 读取全局开关 env('NODE_ADDRESS_MODE').
     * 合法值: ip | dns | cdn; 非法值降级为 ip.
     *
     * @return string
     */
    public static function mode()
    {
        $m = strtolower((string) env('NODE_ADDRESS_MODE', self::MODE_IP));
        if ($m === self::MODE_DNS || $m === self::MODE_CDN) {
            return $m;
        }
        return self::MODE_IP;
    }

    /**
     * 是否为 CDN 解析模式 (全局开关 = cdn).
     *
     * @return bool
     */
    public static function isCdnMode()
    {
        return self::mode() === self::MODE_CDN;
    }

    /**
     * 是否为 DNS 域名解析模式 (全局开关 = dns).
     *
     * @return bool
     */
    public static function isDnsMode()
    {
        return self::mode() === self::MODE_DNS;
    }

    /**
     * 是否为 IP 直连模式 (全局开关 = ip, 默认).
     *
     * @return bool
     */
    public static function isIpMode()
    {
        return self::mode() === self::MODE_IP;
    }

    /**
     * 节点是否为 ipv6 单栈节点 (register 单栈锁定: ip 空且 ipv6 非空).
     *
     * ipv6 单栈节点的连接地址始终为 ipv6, 不做 host 解析 (直连 ipv6):
     *   - register 时按 [ipv4×N, ipv6×N] 槽位展开, ipv6 槽位的节点仅填 ipv6 (ip 空).
     *   - 无论全局开关 (ip/dns/cdn), resolveAddress 恒返回 ipv6, DnsSyncer 跳过不建记录.
     *
     * @param  mixed $node
     * @return bool
     */
    public static function isIpv6Node($node)
    {
        return $node && empty($node->ip) && !empty($node->ipv6);
    }

    /**
     * 节点是否为主节点 (is_clone == 0).
     *
     * host/sni 复用机制下, 主节点与所有 clone 共用同一 host/sni; 主节点连接地址恒为 IP
     * (不创建 DNS 记录, DnsSyncer 跳过), 仅 clone 的 ipv4 节点在 dns/cdn 模式下解析为
     * 域名/CDN IP. 独立节点 (无 clone) 也视为自身主节点, 同样直连 IP.
     *
     * @param  mixed $node
     * @return bool
     */
    public static function isMainNode($node)
    {
        return $node && isset($node->is_clone) && (int) $node->is_clone === 0;
    }

    /**
     * 节点是否"意图"走 CDN (历史标记 v2_name=xhttp-cdn 或 v2_cdn=cf).
     *
     * 注意: 这只是节点意图标记 (用于域名亲和选 CDN 根域名),
     * 真正的地址/DNS 行为由全局开关 mode() 决定, 不由此方法决定.
     *
     * @param  mixed $node
     * @return bool
     */
    public static function isCdnNode($node)
    {
        if ($node && isset($node->v2_name) && self::isCdnV2Name($node->v2_name)) {
            return true;
        }
        return $node && isset($node->v2_cdn) && $node->v2_cdn === 'cf';
    }

    /**
     * 解析节点的客户端连接地址 (订阅统一入口, 纯函数无副作用).
     *
     * ipv6 单栈节点优先: 始终直连 ipv6 (不做 host 解析), 与全局开关无关.
     * 主节点 (is_clone == 0) 次优先: 始终直连 IP (host/sni 复用, 不建 DNS 记录), 与全局开关无关.
     * 其余 (clone ipv4) 节点行为由全局开关 mode() 决定:
     *   ip  → 节点 IP
     *   dns → 解析域名 (resolveConnectDomain; 可与 TLS host 不同)
     *   cdn → CF 优选 IP (CSV 来源); CSV 为空时降级到节点 IP
     *
     * @param  mixed $node  须含 ip / ipv6 / server 属性
     * @return string
     */
    public static function resolveAddress($node)
    {
        // ipv6 单栈节点: 始终直连 ipv6, 不做 host 解析.
        // 此规则优先于全局开关 (ip/dns/cdn 均如此): ipv6 节点无 IPv4, 域名解析对其无意义.
        if (self::isIpv6Node($node)) {
            return (string) $node->ipv6;
        }

        // 主节点 (is_clone == 0): 始终直连 IP, 不做 host 解析.
        // host/sni 复用机制下, 主节点与所有 clone 共用同一 host/sni, 主节点不创建 DNS 记录
        // (DnsSyncer 跳过), 故恒返回 IP, 降低整个集群的 DNS 解析数量.
        // 仅 clone 的 ipv4 节点在 dns/cdn 模式下转为域名/CDN IP.
        if (self::isMainNode($node)) {
            if (!empty($node->ip)) {
                return $node->ip;
            }
            return (string) $node->server;
        }

        $mode = self::mode();

        if ($mode === self::MODE_CDN) {
            $ip = self::resolveCdnIp($node);
            if ($ip !== '') {
                return $ip;
            }
            // CSV 为空 → 降级到节点 IP (保证节点仍可用, 不至于订阅空地址)
            return !empty($node->ip) ? $node->ip : (string) $node->server;
        }

        if ($mode === self::MODE_DNS) {
            return self::resolveConnectDomain($node);
        }

        // MODE_IP
        if (!empty($node->ip)) {
            return $node->ip;
        }
        return (string) $node->server;
    }

    /**
     * 功能1: 解析连接域名 (DNS 解析目标).
     *
     * 默认 = 节点 server 域名. 该域名只需能解析出 IP 即可,
     * 可与 v2_host / v2_sni (TLS 身份) 不同 —— 这正是 domain-fronting 的原理:
     * 连接 domain (解析出节点 IP) 与 TLS host 互相独立.
     *
     * @param  mixed $node
     * @return string
     */
    public static function resolveConnectDomain($node)
    {
        return (string) $node->server;
    }

    /**
     * 功能2: 解析 CDN 节点的 CF 优选 IP.
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
     * 注意: register 不再调用此方法 (保持 register 纯净, address=IP).
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
