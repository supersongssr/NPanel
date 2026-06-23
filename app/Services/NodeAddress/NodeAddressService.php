<?php

namespace App\Services\NodeAddress;

/**
 * 节点地址解析模块 (订阅统一入口, 纯函数无副作用).
 *
 * ──────────────────────────────────────────────────────────────────
 * 设计: register 阶段所有节点 address 原生 = IP (节点注册系统不碰 DNS).
 * DNS 记录的创建交给独立的 DnsSyncer 模块, 在 resolve_dns 端点统一处理
 * (把 clone ipv4 节点的连接域名解析到节点 IP). 本模块只在订阅时惰性解析
 * "客户端连接地址", 与 DNS 记录同步完全解耦.
 *
 * address 解析规则 (无全局开关, 固定行为):
 *   1. ipv6 单栈节点 (ip 空 + ipv6 非空) → 始终直连 ipv6 (不做 host 解析).
 *   2. CDN 节点 (v2_name=xhttp-cdn 或 v2_cdn='cf') → CF 优选 IP (CSV 来源;
 *      CSV 空 → 降级节点 IP). CDN 走 CF 边缘, 独立于 main/clone 角色.
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
    /** CDN 模式对应的 v2_name (历史标记, 域名亲和用). */
    const CDN_V2_NAME = 'xhttp-cdn';

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
     * 节点是否为 ipv6 单栈节点 (register 单栈锁定: ip 空且 ipv6 非空).
     *
     * ipv6 单栈节点的连接地址始终为 ipv6, 不做 host 解析 (直连 ipv6):
     *   - register 时按 [ipv4×N, ipv6×N] 槽位展开, ipv6 槽位的节点仅填 ipv6 (ip 空).
     *   - resolveAddress 恒返回 ipv6; DnsSyncer 跳过 ipv6 节点 (不创建 DNS 记录).
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
     * 节点是否"意图"走 CDN (历史标记 v2_name=xhttp-cdn 或 v2_cdn=cf).
     *
     * 注意: 这只是节点意图标记 (用于域名亲和选 CDN 根域名 + resolveAddress 返回 CF IP),
     * DNS 记录行为由 DnsSyncer 按节点角色决定, 不由此方法决定.
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
     * 解析节点的客户端连接地址 (订阅统一入口, 纯函数无副作用, 无全局开关).
     *
     * 解析顺序 (前者优先):
     *   1. ipv6 单栈节点 → 始终直连 ipv6.
     *   2. CDN 节点 → CF 优选 IP (CSV 空 → 降级节点 IP).
     *   3. 主节点 (is_clone=0) → 直连 IP.
     *   4. clone ipv4 节点 → 连接域名 server (resolve_dns 创建 A 记录解析到 IP).
     *
     * @param  mixed $node  须含 ip / ipv6 / server / is_clone / v2_* 属性
     * @return string
     */
    public static function resolveAddress($node)
    {
        // 1. ipv6 单栈节点: 始终直连 ipv6, 不做 host 解析.
        if (self::isIpv6Node($node)) {
            return (string) $node->ipv6;
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
