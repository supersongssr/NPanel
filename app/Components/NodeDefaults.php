<?php

namespace App\Components;

use App\Http\Models\SsNode;

/**
 * 节点默认值工厂 (单点模块).
 *
 * 收敛 "把一个节点置成默认状态" 的唯一入口, 区分两种语义:
 *
 *   - resetToDefaults() : 全量重置为默认值 = "全新干净节点".
 *       仅供 NodeApiController::applyId() 使用 —— 身份继承原则要求 applyId 阶段
 *       交付一个全部默认值的节点 (无论新建还是回收旧身份), register 阶段再在其上
 *       记录节点上报值. 含 last_traffic_reset_at = null (全新未激活, register 时
 *       if-null → now 激活安全网基线).
 *
 *   - resetV2Derived()  : 仅清 v2 / server / node_ids 等 "register 会重算的派生字段".
 *       供 NodeApiController::register() 在 applyV2Preset 重算 v2 配置前清空,
 *       防止换 v2_name 时旧 preset 的字段 (如 v2_flow / v2_xhttp_verify) 残留.
 *       严格不动 traffic / identity / metrics —— 这些由节点上报或 status() 维护,
 *       register 不重置 (见身份继承原则: 带 id 上报 = 继承遗留信息).
 *
 * 身份继承原则 ("带 id 上报 = 继承遗留信息; 新申请 id = 全新干净节点"):
 *   · 新申请 id (applyId): 调 resetToDefaults() 全清 → 全新.
 *   · 带 id 上报 (同机重装, applyId 被 proxyInstall.sh Step0 跳过): register 只
 *     resetV2Derived() 清派生子集 + 记录节点上报, traffic/identity/metrics 保留.
 *
 * 注意: 两个方法都只改属性, 不 save() —— 由调用方统一决定何时落库.
 *
 * 详见: app/Http/Controllers/Api/NodeApiController.php (applyId / register)
 *       app/Components/NodeTrafficReset.php (流量计数 + 基线重置, 月度/安全网用)
 */
class NodeDefaults
{
    /**
     * 全量重置为默认值 (全新干净节点). 供 applyId 使用.
     *
     * @param  SsNode $node
     * @return void
     */
    public static function resetToDefaults(SsNode $node)
    {
        // --- v2 / server / node_ids 派生子集 (与 register 的 resetV2Derived 共用) ---
        self::resetV2Derived($node);

        // --- Identity (id / created_at / updated_at 保留) ---
        $node->name = "";
        $node->v2_name = "";

        // --- Service type ---
        $node->type = 0;

        // --- Grouping & location ---
        $node->group_id = 0;
        $node->node_group = 1;
        $node->country_code = "un";
        $node->node_country = null;
        $node->node_city = null;

        // --- Server (物理地址; server 连接地址由 register fission 重算, 故此处仅清) ---
        $node->ip = "";
        $node->ipv6 = "";
        $node->desc = "";
        $node->ssh_port = 22;

        // --- SS legacy fields ---
        $node->method = "aes-256-cfb";
        $node->protocol = "origin";
        $node->protocol_param = "";
        $node->obfs = "plain";
        $node->obfs_param = "";

        // --- Traffic & bandwidth ---
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

        // --- Feature flags ---
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

        // --- Status & metrics ---
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

        // --- Hardware ---
        $node->node_cpu = null;
        $node->node_memory = null;
        $node->node_disk = null;

        // --- Billing ---
        $node->node_rxtx = null;

        // --- Unlock & info ---
        $node->node_unlock = "";
        $node->info = "";
        $node->monitor_url = null;
        $node->node_uuid = null;

        // --- Clone / fission identity ---
        $node->is_clone = 0;

        // --- 流量重置基线: 全新节点 = 未激活 (null). register 时 if-null → now 激活;
        //     若 register 未跟上 (节点崩溃), traffic_used=0 + 基线 null 也不会触发安全网. ---
        $node->last_traffic_reset_at = null;
    }

    /**
     * 仅清 v2 / server / node_ids 等 register 会重算的派生字段.
     *
     * register 按 (可能变化的) v2_name 经 applyV2Preset 重算 v2 配置; 各 preset 只
     * 设置自己用到的字段, 故换 v2_name 前必须把整个 v2 子集清空, 否则旧 preset 的
     * 字段 (如 vision 的 v2_flow、xhttp 的 v2_xhttp_verify) 会残留污染新配置.
     *
     * 严格不动: name / v2_name / is_clone / traffic_* / heartbeat_at / server_uptime /
     * node_health / monitor_url / node_uuid / last_traffic_reset_at 等连续性字段
     * —— 这些由节点上报或 status() 维护, register 不重置 (身份继承原则).
     *
     * @param  SsNode $node
     * @return void
     */
    public static function resetV2Derived(SsNode $node)
    {
        // --- server 连接地址 + node_ids 集群标识: register fission 会重算 ---
        $node->server = "";
        $node->node_ids = null;

        // --- V2Ray 全字段清空 (applyV2Preset 按 preset 重填) ---
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
        // xhttp-cdn / xhttp-cdn-hy2 的 ECH 下载规格: 换模式时清空, 防旧值残留.
        $node->v2_ech = null;
        // xhttp-split 的下行域名 / 下行地址: 换模式时清空, 防旧值残留.
        $node->v2_xhttp_dl_host = null;
        $node->v2_xhttp_dl_add = null;
        $node->v2_insider_port = 0;
        $node->v2_outsider_port = 0;

        // vision-reality: REALITY X25519 密钥对 + shortId: 换模式时清空, 防旧密钥残留.
        $node->v2_reality_pbk = null;
        $node->v2_reality_private = null;
        $node->v2_reality_sid = null;
    }
}
