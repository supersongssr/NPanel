# 节点代理配置机制全链路分析

> 分析日期: 2026-05-02
> 核心文件:
> - `app/Http/Controllers/Api/NodeApiController.php` — 节点注册与配置下发
> - `app/Http/Controllers/SubscribeController.php` — 用户订阅链接生成
> - `app/Http/Controllers/AdminController.php` — 管理员手动配置 v2_* 字段
> - `resources/templates/xray/*.json` — Xray 模板文件

---

## 一、系统架构总览

```
物理服务器启动
     │
     ▼
┌─────────────┐    POST /api/node/applyId      ┌──────────┐
│  Node Client │ ──────────────────────────────▶ │ ss_node  │ 分配或回收 ID
│  (xray 等)   │                                 └──────────┘
└─────────────┘
     │
     ▼
┌─────────────┐    POST /api/node/register      ┌──────────────────┐
│  Node Client │ ──────────────────────────────▶ │ NodeApiController │
│              │    (上报 IP/MEM/CPU/协议组)      │  → 裂变为 N 个节点  │
└─────────────┘                                  └──────────────────┘
     │                                                    │
     │              写入 ss_node 表                        │
     │    ┌────────────────────────────────┐              │
     │    │ 1 个主节点 (v2_name=协议组)      │              │
     │    │ N 个 clone (v2_name=单协议)     │ ◀────────────┘
     │    └────────────────────────────────┘
     │
     ▼
┌─────────────┐    POST /api/node/resolveDns   ┌──────────────┐
│  Node Client │ ──────────────────────────────▶│ Cloudflare   │ DNS 对账
└─────────────┘                                 └──────────────┘
     │
     ▼
┌─────────────┐    POST /api/node/config       ┌──────────────────┐
│  Node Client │ ──────────────────────────────▶│ 模板 + 变量注入   │ → config.json
└─────────────┘                                 └──────────────────┘
     │
     ▼
┌─────────────┐    POST /api/node/status       ┌──────────┐
│  Node Client │ ──────────────────────────────▶│ 流量/心跳  │
└─────────────┘                                 └──────────┘
```

---

## 二、核心概念：type 与 v2_name 的职责分工

### `type` 字段 — 订阅协议类型（用户侧）

| type | 协议 | 订阅链接格式 | 代码位置 |
|------|------|-------------|---------|
| 1 | Shadowsocks | `ss://...` | SubscribeController:250 |
| 2 | VMess | `vmess://...` (Base64 JSON) | SubscribeController:298 |
| 3 | VLESS | `vless://...` (URI) | SubscribeController:324 |
| 4 | Trojan | `trojan://...` (URI) | SubscribeController:335 |
| 5 | Hysteria2 | `hy2://...` (URI) | SubscribeController:345 |

**`type` 决定了 SubscribeController 生成哪种协议的订阅链接。**

### `v2_name` 字段 — 模板选择器（服务端侧）

| v2_name | 含义 | 对应模板文件 |
|---------|------|-------------|
| `xhttp-hy2-ws-grpc` | 高配协议组 (内存 > 2GB) | `resources/templates/xray/xhttp-hy2-ws-grpc.json` |
| `vision-hy2-ws-grpc` | 低配协议组 (内存 ≤ 2GB) | `resources/templates/xray/vision-hy2-ws-grpc.json` |
| `xhttp` / `ws` / `grpc` / `hy2` / `vision` | 单协议 (仅 clone 节点) | ❌ 无对应模板文件 |

**`v2_name` 仅用于 `/api/node/config` 的模板查找，不参与订阅链接生成。**

---

## 三、节点裂变（Fission）机制

### 3.1 协议组的确定

`register()` 方法根据内存大小选择协议组（`NodeApiController.php:164-177`）：

```php
$presets = json_decode($sysConf['node_protocol_presets'] ?? '{}', true);
$thresholdMb = $presets['threshold_mb'] ?? 2048;    // 2GB 阈值
$highProto = $presets['high'] ?? 'xhttp-hy2-ws-grpc';
$lowProto = $presets['low'] ?? 'vision-hy2-ws-grpc';

$v2Name = $request->input('v2_name');     // 优先使用客户端上报
if (!$v2Name) {
    $v2Name = ($nodeMemory > $thresholdGb) ? $highProto : $lowProto;
}
```

也可以由客户端在 `register` 请求中主动上报 `v2_name` 参数覆盖。

### 3.2 裂变矩阵构建

以 `v2_name = "xhttp-hy2-ws-grpc"` 且节点同时有 IPv4 和 IPv6 为例：

```
协议展开: xhttp, hy2, ws, grpc    (shuffle 随机打乱)
IP 列表:  [ipv4, ipv6]

裂变矩阵 (protocol × IP):
┌───────────────────────────────────────┐
│ Slot 0: xhttp + ipv4  → 主节点自身    │  ← array_shift
│ Slot 1: hy2   + ipv4  → clone node   │
│ Slot 2: ws    + ipv4  → clone node   │
│ Slot 3: grpc  + ipv4  → clone node   │
│ Slot 4: xhttp + ipv6  → clone node   │
│ Slot 5: hy2   + ipv6  → clone node   │
│ Slot 6: ws    + ipv6  → clone node   │
│ Slot 7: grpc  + ipv6  → clone node   │
└───────────────────────────────────────┘

总计: 1 主节点 + 7 clone = 8 个节点
```

**关键逻辑**（`NodeApiController.php:257-261`）：

```php
// Slot 0 = 主节点本身 (保留完整的 v2_name，如 "xhttp-hy2-ws-grpc")
$mainSlot = array_shift($slots);
$node->save();  // 主节点不修改 v2_name

// 其余 slot 创建 clone，每个 clone 只有一个协议
foreach ($slots as $i => $slot) {
    $clone->v2_name = $slot['protocol'];  // "xhttp", "ws", "grpc", "hy2"
    $clone->type = 3;                     // 统一设为 VLESS
    // ...
}
```

### 3.3 裂变后的数据库状态

以节点组 1114-1121 为例：

| 字段 | 主节点 1114 | Clone 1115 | Clone 1118 |
|------|-----------|------------|------------|
| `is_clone` | `0` | `1114` | `1114` |
| `type` | `1` (回收遗留) | `3` | `3` |
| `v2_name` | `xhttp-hy2-ws-grpc` | `xhttp` | `hy2` |
| `server` | `node1114.ssmail.win` | `node1115.ssmail.win` | `node1118.ssmail.win` |
| `ip` | `23.148.12.136` | `23.148.12.136` | `""` |
| `ipv6` | `2602:f50a:...` | `""` | `2602:f50a:...` |
| `node_ids` | `1114,1115,...,1121` | `null` | `null` |

**`node_ids` 仅存储在主节点上**，作为该物理服务器所有逻辑节点的索引。

---

## 四、config.json 下发（服务端 → 节点）

### 4.1 模板选择逻辑

`config()` 方法（`NodeApiController.php:671-740`）：

```php
$v2Name = $node->v2_name ?: 'vision-hy2-ws-grpc';
$templatePath = resource_path("templates/xray/{$v2Name}.json");
if (!file_exists($templatePath))
    return response()->json(['status' => 'error', 'message' => 'Template not found'], 500);
```

**只有主节点能正常下发 config**，因为：
- 主节点 `v2_name = "xhttp-hy2-ws-grpc"` → 模板存在 ✅
- Clone `v2_name = "xhttp"` → 模板不存在 ❌ → 500 错误

### 4.2 模板内容

两个模板定义了多协议并列 inbound：

| 模板 | Inbound 协议 | 端口变量 |
|------|-------------|---------|
| **xhttp-hy2-ws-grpc.json** | | |
| | VLESS + XHTTP | `__xhttpPort__` (10013) |
| | Hysteria2 | `__hy2Port__` (443) |
| | VMess + WebSocket | `__wsPort__` (10011) |
| | VLESS + gRPC | `__grpcPort__` (10012) |
| **vision-hy2-ws-grpc.json** | | |
| | VLESS + Vision (TCP+TLS) | `__visionPort__` (443) |
| | Hysteria2 | `__hy2Port__` (443) |
| | VMess + WebSocket (fallback) | `__wsPort__` (10011) |
| | VLESS + gRPC (TLS) | `__grpcPort__` (10012) |

### 4.3 变量注入

模板中的占位符被节点特定值替换：

```php
$vars = [
    '__wsPath__'         => 'ws' . $nodeId,           // WebSocket 路径
    '__wsPort__'         => 10011,                     // WS 端口
    '__v2ServiceName__'  => 'grpc' . $nodeId,          // gRPC 服务名
    '__grpcPort__'       => 10012,                     // gRPC 端口
    '__xhttpPath__'      => 'xhttp' . $nodeId,         // XHTTP 路径
    '__xhttpPort__'      => 10013,                     // XHTTP 端口
    '__hy2Port__'        => 443,                       // Hysteria2 端口
    '__visionPort__'     => 443,                       // Vision 端口
    '__nodeDomain__'     => $node->server,              // 节点域名
    '__dbHost__'         => env('DB_HOST'),             // 数据库连接
    // ...
];
```

### 4.4 inboundTags 和 flows

```php
$expanded = $this->expandProtocols($v2Name);
$inboundTags = array_map(function($proto) {
    return 'proxy-' . $proto;   // ["proxy-xhttp", "proxy-hy2", "proxy-ws", "proxy-grpc"]
}, $expanded);

// Vision 协议需要 flows 配置
if (in_array('vision', $expanded)) {
    $config['ssrpanel']['user']['flows'] = [
        'proxy-vision' => 'xtls-rprx-vision'
    ];
}
```

`ssrpanel.user.inboundTags` 告诉节点端的 xray-panel 只统计这些 inbound 的用户流量。

---

## 五、用户订阅链接生成（数据库 → 客户端）

### 5.1 节点查询

`SubscribeController.php:280`：

```php
$nodeList = SsNode::query()
    ->where('status', 1)           // 在线
    ->where('is_subscribe', 1)     // 允许订阅
    ->where('node_group', $user->node_group)  // 用户分组匹配
    ->where('level', '<=', $user->level)      // 等级权限
    ->orderBy('level', 'desc')
    ->orderBy('traffic_left_daily', 'desc')
    ->get();
```

**重要**: 查询中没有 `is_clone` 过滤 — clone 节点和主节点都会出现在用户订阅中。

### 5.2 订阅链接生成逻辑

对每个节点，根据 `type` 生成对应协议的 URI：

#### type=2 VMess (`SubscribeController:298-323`)

```php
$v2_json = [
    "v"    => "2",
    "ps"   => $node->name . '-' . $node->id,
    "add"  => $node->server,          // 连接地址
    "port" => $node->v2_port,         // 连接端口
    "id"   => $node_uuid,             // 用户 UUID
    "aid"  => $node->v2_alter_id,     // 额外 ID
    "scy"  => $node->v2_method,       // 加密方式
    "net"  => $node->v2_net,          // 传输协议 (tcp/ws/grpc/...)
    "type" => $node->v2_type,         // 伪装类型
    "host" => $node->v2_host,         // 伪装域名
    "path" => $node->v2_path,         // 路径
    "tls"  => $node->v2_tls,          // TLS (0/1/2→''/tls/xtls)
    "sni"  => $node->v2_sni,          // SNI
    "serviceName" => $node->v2_servicename,  // gRPC 服务名
    "mode" => $node->v2_mode,         // gRPC 模式
    "alpn" => $node->v2_alpn          // ALPN
];
$scheme .= 'vmess://' . base64_encode(json_encode($v2_json)) . "\n";
```

#### type=3 VLESS (`SubscribeController:324-334`)

```php
$scheme .= 'vless://' . $node_uuid . '@' . $node->server . ':' . $node->v2_port;
$scheme .= '?encryption=' . $node->v2_encryption
         . '&type=' . $node->v2_net              // 传输协议
         . '&headerType=' . $node->v2_type
         . '&host=' . urlencode($node->v2_host)
         . '&path=' . urlencode($node->v2_path)
         . '&flow=' . $node->v2_flow              // XTLS flow (如 xtls-rprx-vision)
         . '&security=' . $node->v2_tls
         . '&sni=' . $node->v2_sni
         . '&fp=' . $node->v2_fp                  // TLS fingerprint
         . '&serviceName=' . $node->v2_servicename
         . '&mode=' . $vlessMode                  // gRPC/xhttp mode
         . '&alpn=' . urlencode($node->v2_alpn);
$scheme .= '#' . urlencode($node->name) . "\n";
```

#### type=5 Hysteria2 (`SubscribeController:345-362`)

```php
$hy2Url = sprintf(
    "hy2://%s@%s:%s?sni=%s&insecure=1#%s\n",
    $node_uuid,
    $node->server,
    $node->v2_port,
    rawurlencode($node->v2_sni),
    $encodedName
);
```

### 5.3 订阅链接所需的 v2_* 字段清单

| 字段 | VMess | VLESS | Hysteria2 | 说明 |
|------|-------|-------|-----------|------|
| `server` | ✅ | ✅ | ✅ | 连接地址 (域名) |
| `v2_port` | ✅ | ✅ | ✅ | 连接端口 |
| `v2_net` | ✅ | ✅ | - | 传输协议 (tcp/ws/grpc/xhttp/hysteria2) |
| `v2_type` | ✅ | ✅ | - | 伪装类型 |
| `v2_host` | ✅ | ✅ | - | 伪装域名 |
| `v2_path` | ✅ | ✅ | - | WebSocket/XHTTP 路径 |
| `v2_tls` | ✅ | ✅ | - | TLS 开关 (0/1/2) |
| `v2_sni` | ✅ | ✅ | ✅ | TLS SNI |
| `v2_flow` | - | ✅ | - | XTLS flow |
| `v2_encryption` | - | ✅ | - | 加密方式 |
| `v2_servicename` | ✅ | ✅ | - | gRPC serviceName |
| `v2_mode` | ✅ | ✅ | - | gRPC/xhttp mode |
| `v2_alpn` | ✅ | ✅ | - | ALPN |
| `v2_alter_id` | ✅ | - | - | VMess 额外 ID |
| `v2_method` | ✅ | - | - | VMess 加密方式 |
| `v2_fp` | - | ✅ | - | TLS fingerprint |

---

## 六、v2_* 字段的设置来源

### 6.1 Node API（register）— 不设置 v2_* 字段

`register()` 方法**不会**设置任何 `v2_*` 字段。它只设置：

```
v2_name, node_rxtx, node_cpu, node_memory, node_disk, bandwidth,
node_unlock, info, level, node_group, node_cost, traffic_limit,
reset_day, sort, traffic_rate, country_code, node_country, node_city,
ip, ipv6, server, status
```

### 6.2 管理员面板 — 手动设置 v2_* 字段

`AdminController.php:894-907` 和 `1028-1047`：

通过管理员后台 → 编辑节点 → 手动填写 `v2_port`、`v2_net`、`v2_host`、`v2_tls`、`v2_sni`、`v2_flow` 等字段。

### 6.3 数据库默认值 / 回收节点残留

对于通过 Node API 自动注册的节点，`v2_*` 字段来源：
- **新建节点**: 使用数据库 migration 的默认值（如 `v2_port=0`, `v2_net='tcp'`）
- **回收节点**: 继承被回收节点的旧 `v2_*` 值

### 6.4 字段状态对比

以节点组 1114（API 注册）vs 156（管理员配置）为例：

| 字段 | 1114 (API注册) | 156 (管理员配置) |
|------|---------------|-----------------|
| `v2_port` | **0** | **443** |
| `v2_net` | tcp | tcp |
| `v2_host` | "" | n156.ssmail.win |
| `v2_tls` | **0** | **1** (tls) |
| `v2_sni` | **null** | 5728n156.ssmail.win |
| `v2_flow` | **null** | xtls-rprx-vision |
| `v2_fp` | "" | random |

**结论**: 节点组 1114 的 `v2_*` 字段全为空值/默认值，因为它们只通过 API 注册，从未经管理员手动配置。

---

## 七、完整数据流总结

```
┌─────────────────────────────────────────────────────────────────┐
│                     节点端 (xray)                                │
│                                                                  │
│  config.json ◀── 模板文件 + 变量注入                              │
│  (多协议并列运行: xhttp+hy2+ws+grpc)                              │
│  模板由 v2_name 选择                                              │
│  只需要主节点的 node_id 请求一次                                    │
└────────────────────────┬────────────────────────────────────────┘
                         │ 不直接通信
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                   用户端 (v2rayN/Clash 等)                        │
│                                                                  │
│  订阅链接 ◀── SubscribeController                                 │
│  (每个 clone 节点生成一条链接)                                     │
│  链接格式由 type 字段决定                                          │
│  链接内容依赖 v2_port, v2_net, v2_host 等 v2_* 字段               │
│  ❗ 这些字段需要管理员手动配置                                      │
└─────────────────────────────────────────────────────────────────┘
```

### 两套独立的配置体系

| 体系 | 服务对象 | 配置来源 | 关键字段 |
|------|---------|---------|---------|
| **Xray 配置** | 节点服务端 | `v2_name` → 模板文件 → `config.json` | `v2_name`, `server` |
| **用户订阅** | 用户客户端 | `type` + `v2_*` 字段 → 订阅链接 | `type`, `v2_port`, `v2_net`, `v2_host`, `v2_tls` 等 |

**两个体系完全解耦** — `config.json` 正确下发不代表用户订阅链接正确，反之亦然。

---

## 八、当前存在的问题

### 问题 1: register() 不设置 v2_* 字段

clone 节点的 `v2_port`、`v2_net`、`v2_host`、`v2_tls` 等全部为空/默认值。
用户订阅链接会生成 `vless://uuid@node1115.ssmail.win:0?type=tcp&host=&path=`，
**端口为 0，host 为空，无法使用**。

需要管理员手动编辑每个 clone 节点设置正确的 v2_* 值，或者由 register() 自动填充。

### 问题 2: 主节点 type 未设置

主节点从回收池获取时，`type` 保留旧值（如 `type=1`），而不是正确的 `type=3`。
SubscribeController 按 `type` 生成链接，`type=1` 会生成 `ss://` 链接而非 `vless://`。

### 问题 3: clone 节点无法独立请求 config

clone 的 `v2_name` 是单协议（如 `"xhttp"`），没有对应模板文件。
如果客户端为每个 node_id 分别请求 config，clone 节点会返回 500。
