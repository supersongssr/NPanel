<?php

namespace App\Services\Subscribe\Formatters;

use App\Http\Models\SsNode;
use App\Http\Models\User;

/**
 * Quantumult X 订阅格式化器
 *
 * 将节点列表转换为 QuanX 明文格式或 Base64 编码格式。
 * QuanX 节点格式：协议=地址:端口, 参数1=值1, 参数2=值2, tag=节点名
 *
 * 支持协议：VMess, VLESS, Trojan, Shadowsocks
 * 支持特性：WebSocket 传输, TLS, Vision (xtls-rprx-vision), Reality
 */
class QuanXFormatter
{
    /**
     * 生成 QuanX 格式的节点配置
     *
     * @param \Illuminate\Support\Collection $nodeList 节点列表
     * @param User $user 用户对象
     * @param bool $base64 是否返回 Base64 编码
     * @return string
     */
    public function format($nodeList, User $user, bool $base64 = false): string
    {
        $lines = [];

        foreach ($nodeList as $node) {
            $line = $this->formatNode($node, $user);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        $plainText = implode("\n", $lines);

        if ($base64) {
            return base64_encode($plainText);
        }

        return $plainText;
    }

    /**
     * 格式化单个节点为 QuanX 格式
     *
     * @param SsNode $node 节点对象
     * @param User $user 用户对象
     * @return string|null
     */
    private function formatNode(SsNode $node, User $user): ?string
    {
        // 获取节点 UUID
        $uuid = $node->node_uuid ?: $user->vmess_id;

        // 生成国旗+城市名的显示名称
        $countryCode = strtolower($node->country_code ?? '');
        $displayName = $node->name;
        if (!empty($countryCode) && $countryCode !== 'un') {
            $flag = $node->isotoemoji($countryCode);
            $cityName = $node->node_city ?: $node->node_country ?: $node->name;
            $displayName = $flag . $cityName;
        }

        // 标签防爆处理：清洗特殊字符
        $tag = str_replace([',', '=', "\n", "\r"], ['_', '-', '', ''], $displayName);
        $tag .= ($node->traffic_rate != 1 ? '_x' . $node->traffic_rate : '');
        $tag .= '_#' . $node->id;

        // TLS 状态判断
        $tlsEnabled = ($node->v2_tls == 1 || $node->v2_tls == 2);

        // 根据 type 生成对应协议行（不含 tag）
        switch ($node->type) {
            case 2: // VMess
                $line = $this->formatVmess($node, $uuid, $tlsEnabled);
                break;
            case 3: // VLESS
                $line = $this->formatVless($node, $uuid, $tlsEnabled);
                break;
            case 4: // Trojan
                $line = $this->formatTrojan($node, $uuid, $tlsEnabled);
                break;
            case 1: // Shadowsocks
                $line = $this->formatShadowsocks($node);
                break;
            default:
                return null;
        }

        // tag 必须放在最后，避免后续追加参数时破坏 tag 结构
        return $line . ", tag={$tag}";
    }

    /**
     * 格式化 VMess 节点（不含 tag，tag 由 formatNode 追加）
     */
    private function formatVmess(SsNode $node, string $uuid, bool $tlsEnabled): string
    {
        $cipher = $node->v2_method ?: 'aes-128-gcm';
        $line = "vmess={$node->server}:{$node->v2_port}, method={$cipher}, password={$uuid}, fast-open=false, udp-relay=true";

        // 传输层参数
        $line = $this->appendTransportParams($line, $node, $tlsEnabled);

        return $line;
    }

    /**
     * 格式化 VLESS 节点（不含 tag，tag 由 formatNode 追加）
     */
    private function formatVless(SsNode $node, string $uuid, bool $tlsEnabled): string
    {
        $line = "vless={$node->server}:{$node->v2_port}, method=none, password={$uuid}, fast-open=false, udp-relay=true";

        // Vision flow 支持
        if (!empty($node->v2_flow)) {
            $line .= ", flow={$node->v2_flow}";
        }

        // Reality 支持
        if ($node->v2_encryption === 'reality') {
            $line .= ", security=reality";
            if (!empty($node->v2_sni)) {
                $line .= ", tls-host={$node->v2_sni}";
            }
            if (!empty($node->v2_fp)) {
                $line .= ", fingerprint={$node->v2_fp}";
            }
            // Short ID — 使用 v2_mode 字段暂存（QuanX 中为 short-id 参数）
            if (!empty($node->v2_mode)) {
                $line .= ", short-id={$node->v2_mode}";
            }
        }

        // 传输层参数（非 Reality 时）
        if ($node->v2_encryption !== 'reality') {
            $line = $this->appendTransportParams($line, $node, $tlsEnabled);
        }

        return $line;
    }

    /**
     * 格式化 Trojan 节点（不含 tag，tag 由 formatNode 追加）
     */
    private function formatTrojan(SsNode $node, string $uuid, bool $tlsEnabled): string
    {
        $sni = $node->v2_sni ?: $node->server;
        $line = "trojan={$node->server}:{$node->v2_port}, password={$uuid}, over-tls=true, tls-host={$sni}, tls-verification=true, fast-open=false, udp-relay=true";

        // WS 传输
        if ($node->v2_net === 'ws' || $node->v2_net === 'http') {
            $line .= ", obfs=ws";
            if (!empty($node->v2_path)) {
                $line .= ", obfs-uri={$node->v2_path}";
            }
            if (!empty($node->v2_host)) {
                $line .= ", obfs-host={$node->v2_host}";
            }
        }

        return $line;
    }

    /**
     * 格式化 Shadowsocks 节点（不含 tag，tag 由 formatNode 追加）
     */
    private function formatShadowsocks(SsNode $node): string
    {
        $cipher = $node->method ?: 'aes-256-cfb';
        $password = $node->single_passwd ?: $node->protocol_param;
        return "shadowsocks={$node->server}:{$node->ssh_port}, method={$cipher}, password={$password}, fast-open=false, udp-relay=true";
    }

    /**
     * 追加传输层参数（WS + TLS 组合处理）
     *
     * QuanX 中 WS+TLS 的写法是：obfs=ws, over-tls=true, tls-host=xxx
     * 非 WS 的 TLS 写法是：over-tls=true, tls-host=xxx（VMess 也可用 obfs=over-tls）
     *
     * @param string $line 已拼接的基础行
     * @param SsNode $node 节点对象
     * @param bool $tlsEnabled 是否启用 TLS
     * @return string
     */
    private function appendTransportParams(string $line, SsNode $node, bool $tlsEnabled): string
    {
        $isWs = ($node->v2_net === 'ws' || $node->v2_net === 'http');

        if ($isWs && $tlsEnabled) {
            // WS + TLS 组合
            $line .= ", obfs=ws, over-tls=true";
            if (!empty($node->v2_path)) {
                $line .= ", obfs-uri={$node->v2_path}";
            }
            if (!empty($node->v2_host)) {
                $line .= ", obfs-host={$node->v2_host}";
            }
            if (!empty($node->v2_sni)) {
                $line .= ", tls-host={$node->v2_sni}";
            }
        } elseif ($isWs) {
            // 仅 WS，无 TLS
            $line .= ", obfs=ws";
            if (!empty($node->v2_path)) {
                $line .= ", obfs-uri={$node->v2_path}";
            }
            if (!empty($node->v2_host)) {
                $line .= ", obfs-host={$node->v2_host}";
            }
        } elseif ($tlsEnabled) {
            // 仅 TLS，无 WS（TCP + TLS）
            $line .= ", over-tls=true";
            if (!empty($node->v2_sni)) {
                $line .= ", tls-host={$node->v2_sni}";
            }
        }

        return $line;
    }
}
