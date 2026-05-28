# 开发计划: 新增 6 种协议组合

> 日期: 2026-05-28
> 参考模板: `resources/templates/xray/` + `resources/templates/nginx/`

---

## 1. 背景与术语

### 现有协议定义 (来自模板与 `V2_PRESETS`)

| 简称 | 完整协议 | xray 配置 |
|------|---------|----------|
| **vision** | VLESS + TCP + TLS + XTLS-Vision | `protocol: vless`, `network: tcp`, `security: tls`, `flow: xtls-rprx-vision`, 监听 443 |
| **ws** | VMess + WebSocket | `protocol: vmess`, `network: ws`, 监听 127.0.0.1:10011, 由 nginx 反代 |
| **grpc** | VLESS + gRPC | `protocol: vless`, `network: grpc`, 监听 127.0.0.1:10012, 由 nginx 反代 |
| **hy2** | Hysteria2 (QUIC/UDP) | `protocol: hysteria v2`, `network: hysteria`, `security: tls`, 监听 443/UDP |
| **xhttp** | VLESS + XHTTP | `protocol: vless`, `network: xhttp`, 监听 127.0.0.1:10013, 由 nginx 反代 |

### 现有两套 "四合一" 模板

| v2_name | 节点裂变 (slot 顺序) |
|---------|---------------------|
| `vision-hy2-ws-grpc` | vision(主) → hy2 → ws → grpc (4 节点) |
| `xhttp-hy2-ws-grpc` | xhttp(主) → hy2 → ws → grpc (4 节点) |

### "Clone" 概念

- 每个协议组的**第一个 slot** 为**主节点** (`is_clone = 0`)
- 其余 slot 为 **clone 节点** (`is_clone = 主节点ID`)
- 每个节点拥有独立域名: `n{nodeId}.{rootDomain}`, 天然不同
- 所有节点共享同一 IP (同一台物理服务器)
- 主节点的 xray 进程运行**模板中全部 inbound**
- Clone 节点在 DB 中独立存在, 供用户订阅不同协议/域名
- Clone 节点请求 `config()` 时, 只保留匹配的 inbound (`v2NetToInboundTag` 过滤)

---

## 2. 新增协议组合定义

| # | v2_name | 裂变结果 (slot 列表) | 节点数 | 说明 |
|---|---------|---------------------|-------|------|
| 1 | `vision-ws-grpc` | vision(主) → ws → grpc | 3 | 无 hy2, 无域名复用, 3 个不同协议 |
| 2 | `vision-hy2` | vision(主) → **vision**(clone) → hy2 | 3 | 第 2 个 vision 是主 vision 的 clone, 域名不同, 配置相同; hy2 独立协议 |
| 3 | `vision` | vision(主) → **vision**(clone) → **vision**(clone) | 3 | 3 个同协议节点, 域名各不同, 配置完全相同 |
| 4 | `xhttp-ws-grpc` | xhttp(主) → ws → grpc | 3 | 无 hy2, 无域名复用, 3 个不同协议 |
| 5 | `xhttp-hy2` | xhttp(主) → **xhttp**(clone) → hy2 | 3 | 第 2 个 xhttp 是主 xhttp 的 clone, 域名不同, 配置相同; hy2 独立协议 |
| 6 | `xhttp` | xhttp(主) → **xhttp**(clone) → **xhttp**(clone) | 3 | 3 个同协议节点, 域名各不同, 配置完全相同 |

**关键差异**: `vision-hy2` 和 `xhttp-hy2` 的裂变数组中包含**重复协议名**, 表示同协议 clone (不同域名)。`expandProtocols()` 必须返回含重复的数组。

### 裂变示例

以 `vision-hy2` + 单 IPv4 为例:

```
expandProtocols("vision-hy2") → ["vision", "vision", "hy2"]

slots = [
    { protocol: "vision", ip: ipv4, addr: "1.2.3.4" },   ← 主节点
    { protocol: "vision", ip: ipv4, addr: "1.2.3.4" },   ← clone 1 (同协议, 不同域名)
    { protocol: "hy2",    ip: ipv4, addr: "1.2.3.4" },   ← clone 2 (不同协议, 不同域名)
]
```

结果:
- 主节点: `n101.example.com` → v2_net=tcp, v2_port=443, v2_flow=xtls-rprx-vision
- Clone 1: `n102.example.com` → v2_net=tcp, v2_port=443, v2_flow=xtls-rprx-vision (域名不同)
- Clone 2: `n103.example.com` → v2_net=hysteria2, v2_port=443 (hy2 协议)

---

## 3. 文件变更清单

### 3.1 Xray 模板 (6 个新文件)

路径: `resources/templates/xray/`

每个模板包含: `log` + `inbounds`(协议相关) + `outbounds` + `routing` + `policy` + `stats` + `api` + `ssrpanel`

| 新文件 | 源模板 | 需保留的 inbound tag |
|--------|--------|---------------------|
| `vision-ws-grpc.json` | `vision-hy2-ws-grpc.json` | `proxy-vision` + `proxy-ws` + `proxy-grpc` + `api` |
| `vision-hy2.json` | `vision-hy2-ws-grpc.json` | `proxy-vision` + `proxy-hy2` + `api` |
| `vision.json` | `vision-hy2-ws-grpc.json` | `proxy-vision` + `api` |
| `xhttp-ws-grpc.json` | `xhttp-hy2-ws-grpc.json` | `proxy-xhttp` + `proxy-ws` + `proxy-grpc` + `api` |
| `xhttp-hy2.json` | `xhttp-hy2-ws-grpc.json` | `proxy-xhttp` + `proxy-hy2` + `api` |
| `xhttp.json` | `xhttp-hy2-ws-grpc.json` | `proxy-xhttp` + `api` |

**操作方式**: 复制源模板, 删除不需要的 inbound 对象, 其余 (log/outbounds/routing/policy/stats/api/ssrpanel) 保持不变。

### 3.2 Nginx 模板 (6 个新文件)

路径: `resources/templates/nginx/`

> **重要**: hy2 (Hysteria2) 使用 QUIC/UDP 协议, nginx 不代理, 由 xray 直接监听 443/UDP。因此模板中无 hy2 相关指令。

| 新文件 | 源模板 | 需变更的内容 |
|--------|--------|-------------|
| `vision-ws-grpc.conf` | `vision-hy2-ws-grpc.conf` | **无需修改** — hy2 在 nginx 中无对应块, 删除不影响; 保留: 80 重定向 + 8088 fallback + 2053 ws/grpc 反代 |
| `vision-hy2.conf` | `vision-hy2-ws-grpc.conf` | 删除 2053 端口 server 块 (无 ws/grpc); 保留: 80 重定向 + 8088 fallback |
| `vision.conf` | `vision-hy2-ws-grpc.conf` | 删除 2053 端口 server 块; 保留: 80 重定向 + 8088 fallback (与 vision-hy2.conf 相同) |
| `xhttp-ws-grpc.conf` | `xhttp-hy2-ws-grpc.conf` | **无需修改** — hy2 在 nginx 中无对应块; 保留: 80 重定向 + 443 SSL (xhttp + ws + grpc location) |
| `xhttp-hy2.conf` | `xhttp-hy2-ws-grpc.conf` | 删除 ws / gRPC location 块; 保留: 80 重定向 + 443 SSL (xhttp location + 默认回落) |
| `xhttp.conf` | `xhttp-hy2-ws-grpc.conf` | 删除 ws / gRPC location 块; 保留: 80 重定向 + 443 SSL (xhttp location + 默认回落) (与 xhttp-hy2.conf 相同) |

### 3.3 PHP 代码 (1 个文件修改)

文件: `app/Http/Controllers/Api/NodeApiController.php`

---

## 4. 任务详细设计

### T1: 新增 `V2_PROTOCOL_SLOTS` 常量 + 修改 `expandProtocols()`

**where**: `NodeApiController.php`, `V2_PRESETS` 常量下方

**what**: 新增协议 slot 映射, 修改 `expandProtocols()` 使其返回含重复协议的数组。

```php
// 新增常量 — 定义每个 v2_name 展开后的协议 slot 列表
// 第一个元素为主节点, 后续为 clone 节点
// 重复协议名 = 同协议不同域名的 clone
const V2_PROTOCOL_SLOTS = [
    // 现有 (保持兼容)
    "vision-hy2-ws-grpc" => ["vision", "hy2", "ws", "grpc"],
    "xhttp-hy2-ws-grpc"  => ["xhttp", "hy2", "ws", "grpc"],
    // 新增
    "vision-ws-grpc" => ["vision", "ws", "grpc"],
    "vision-hy2"     => ["vision", "vision", "hy2"],
    "vision"         => ["vision", "vision", "vision"],
    "xhttp-ws-grpc"  => ["xhttp", "ws", "grpc"],
    "xhttp-hy2"      => ["xhttp", "xhttp", "hy2"],
    "xhttp"          => ["xhttp", "xhttp", "xhttp"],
];
```

```php
// 修改 expandProtocols — 优先查表, 兜底 explode
private function expandProtocols($v2Name)
{
    if (isset(self::V2_PROTOCOL_SLOTS[$v2Name])) {
        return self::V2_PROTOCOL_SLOTS[$v2Name];
    }
    return explode("-", $v2Name);
}
```

**why**: `explode("-", "vision-hy2")` 返回 `["vision","hy2"]` (2 个), 但实际需要 `["vision","vision","hy2"]` (3 个, 含一个同协议 clone)。查表法可精确控制每个协议的 slot 数量和顺序。

**影响分析**:

| 调用点 | 影响 |
|--------|------|
| `register()` L288 裂变矩阵 | ✅ `expandProtocols` 返回 `["vision","vision","hy2"]` → 生成 3 个 slot → 主节点 + 2 clone |
| `register()` L372 主节点 applyV2Preset | ✅ slot["protocol"]="vision" → 正确应用 vision preset |
| `register()` L399/431 clone applyV2Preset | ✅ slot["protocol"]="vision" 或 "hy2" → 各自正确应用对应 preset |
| `applyV2Preset()` L677 端口覆盖 | ✅ `in_array("vision", ["vision","vision","hy2"])` = true → ws/grpc→2053; 无 ws/grpc 的协议不触发 |
| `config()` L1425 模板选择 | ✅ `$v2Name = $node->v2_name` → 正确定位模板文件 |
| `config()` L1445 inbound tag 映射 | ✅ `inboundTags` 会包含重复的 tag (如两个 "proxy-vision"), 但 `ssrpanel.user.inboundTags` 是数组, xray 会正确处理; 或可 `array_unique` |
| `nginxConfig()` L1569 模板选择 | ✅ 取 mainNode 的 v2_name → 正确定位模板 |

### T2: 创建 6 个 Xray 模板

**where**: `resources/templates/xray/`

逐个从源模板复制并删除不需要的 inbound:

#### T2.1: `vision-ws-grpc.json`

来源: `vision-hy2-ws-grpc.json`
- ✅ 保留: `proxy-vision` (vless+tcp+tls, port __visionPort__)
- ❌ 删除: `proxy-hy2`
- ✅ 保留: `proxy-ws` (vmess+ws, listen 127.0.0.1:__wsPort__)
- ✅ 保留: `proxy-grpc` (vless+grpc, listen 127.0.0.1:__grpcPort__)
- ✅ 保留: `api` (dokodemo-door)
- 其余不变

#### T2.2: `vision-hy2.json`

来源: `vision-hy2-ws-grpc.json`
- ✅ 保留: `proxy-vision`
- ✅ 保留: `proxy-hy2` (hysteria2, port __hy2Port__)
- ❌ 删除: `proxy-ws`
- ❌ 删除: `proxy-grpc`
- ✅ 保留: `api`
- 其余不变

#### T2.3: `vision.json`

来源: `vision-hy2-ws-grpc.json`
- ✅ 保留: `proxy-vision`
- ❌ 删除: `proxy-hy2`, `proxy-ws`, `proxy-grpc`
- ✅ 保留: `api`
- 其余不变

> 注: 主节点 config 会拿到完整的 vision inbound + api。3 个 clone 节点各自请求 config 时, `is_clone>0` 过滤只保留 `proxy-vision` + `api`。

#### T2.4: `xhttp-ws-grpc.json`

来源: `xhttp-hy2-ws-grpc.json`
- ✅ 保留: `proxy-xhttp` (vless+xhttp, listen 127.0.0.1:__xhttpPort__)
- ❌ 删除: `proxy-hy2`
- ✅ 保留: `proxy-ws` (vmess+ws)
- ✅ 保留: `proxy-grpc` (vless+grpc)
- ✅ 保留: `api`
- 其余不变

#### T2.5: `xhttp-hy2.json`

来源: `xhttp-hy2-ws-grpc.json`
- ✅ 保留: `proxy-xhttp`
- ✅ 保留: `proxy-hy2`
- ❌ 删除: `proxy-ws`, `proxy-grpc`
- ✅ 保留: `api`
- 其余不变

#### T2.6: `xhttp.json`

来源: `xhttp-hy2-ws-grpc.json`
- ✅ 保留: `proxy-xhttp`
- ❌ 删除: `proxy-hy2`, `proxy-ws`, `proxy-grpc`
- ✅ 保留: `api`
- 其余不变

### T3: 创建 6 个 Nginx 模板

**where**: `resources/templates/nginx/`

#### T3.1: `vision-ws-grpc.conf`

来源: `vision-hy2-ws-grpc.conf` → **直接复制, 无修改**

原因: hy2 在 nginx 中无对应配置 (QUIC/UDP 由 xray 直接处理), 删除 hy2 不影响 nginx 配置。

内容结构:
```
server { listen 80; ... }                     # HTTP → HTTPS 重定向
server { listen 127.0.0.1:8088 http2; ... }   # Vision fallback 伪装站
server { listen 2053 ssl http2; ... }         # WS/gRPC 反代, CF IP 白名单
```

#### T3.2: `vision-hy2.conf`

来源: `vision-hy2-ws-grpc.conf` → 删除 2053 端口 server 块

内容结构:
```
server { listen 80; ... }                     # HTTP → HTTPS 重定向
server { listen 127.0.0.1:8088 http2; ... }   # Vision fallback 伪装站
# (无 2053 块 — 无 ws/grpc)
```

#### T3.3: `vision.conf`

来源: `vision-hy2-ws-grpc.conf` → 删除 2053 端口 server 块

内容结构 (与 `vision-hy2.conf` 完全相同):
```
server { listen 80; ... }
server { listen 127.0.0.1:8088 http2; ... }
```

#### T3.4: `xhttp-ws-grpc.conf`

来源: `xhttp-hy2-ws-grpc.conf` → **直接复制, 无修改**

原因: 同 T3.1, hy2 在 nginx 中无对应配置。

内容结构:
```
server { listen 80; ... }                     # HTTP → HTTPS 重定向
server { listen 443 ssl http2; ... }          # XHTTP + WS + gRPC location 块
```

443 端口内的 location:
- `/__xhttpPath__` → 反代 xhttp (无 IP 限制, 用户直连)
- `/__wsPath__` → 反代 ws (CF IP 白名单)
- `/__v2ServiceName__` → 反代 grpc (CF IP 白名单)
- `/` → 默认回落伪装站

#### T3.5: `xhttp-hy2.conf`

来源: `xhttp-hy2-ws-grpc.conf` → 删除 ws 和 grpc location 块

内容结构:
```
server { listen 80; ... }
server { listen 443 ssl http2; ... }          # XHTTP + 默认回落
```

443 端口内的 location:
- `/__xhttpPath__` → 反代 xhttp (无 IP 限制)
- `/` → 默认回落伪装站

#### T3.6: `xhttp.conf`

来源: `xhttp-hy2-ws-grpc.conf` → 删除 ws 和 grpc location 块

内容结构 (与 `xhttp-hy2.conf` 完全相同):
```
server { listen 80; ... }
server { listen 443 ssl http2; ... }          # XHTTP + 默认回落
```

### T4: 无需修改的代码 — 验证清单

以下代码路径经分析**无需修改**, 但实施后需验证:

#### T4.1: `config()` — clone 节点 inbound 过滤

```php
// L1472: clone 节点只保留匹配的 inbound
if ($node->is_clone > 0) {
    $keepTag = $this->v2NetToInboundTag($node->v2_net);
    $config["inbounds"] = array_values(
        array_filter($config["inbounds"], function ($ib) use ($keepTag) {
            return ($ib["tag"] ?? "") === "api" || ($ib["tag"] ?? "") === $keepTag;
        })
    );
}
```

验证场景:
| 协议 | clone 节点 v2_net | keepTag | 模板中需匹配的 tag | 结果 |
|------|-------------------|---------|-------------------|------|
| vision-hy2, clone#1 | `tcp` (vision preset) | `proxy-vision` | `proxy-vision` | ✅ |
| vision-hy2, clone#2 | `hysteria2` (hy2 preset) | `proxy-hy2` | `proxy-hy2` | ✅ |
| vision, clone#1 | `tcp` | `proxy-vision` | `proxy-vision` | ✅ |
| vision, clone#2 | `tcp` | `proxy-vision` | `proxy-vision` | ✅ |
| xhttp-hy2, clone#1 | `xhttp` | `proxy-xhttp` | `proxy-xhttp` | ✅ |
| xhttp-hy2, clone#2 | `hysteria2` | `proxy-hy2` | `proxy-hy2` | ✅ |

#### T4.2: `config()` — ssrpanel inboundTags

```php
// L1449: 生成 inboundTags
$inboundTags = array_map(function ($proto) {
    return "proxy-" . $proto;
}, $expanded);
```

对于 `vision-hy2` → `["vision","vision","hy2"]` → inboundTags = `["proxy-vision","proxy-vision","proxy-hy2"]`

⚠️ **可能问题**: `inboundTags` 中出现重复的 `"proxy-vision"`。

**建议**: 在此处添加 `array_unique`:
```php
$inboundTags = array_values(array_unique(array_map(function ($proto) {
    return "proxy-" . $proto;
}, $expanded)));
```

> 这是唯一的微小修改点 (T4.2-fix), 防止 xray ssrpanel 插件处理重复 tag 时出错。

#### T4.3: `applyV2Preset()` — 端口覆盖逻辑

```php
// L677: vision 模式下 ws/grpc 端口改为 2053
$expandedMode = $this->expandProtocols($modeName);
if (in_array("vision", $expandedMode)) {
    if ($protocol === "ws" || $protocol === "grpc") {
        $node->v2_port = 2053;
    }
}
```

验证:
| v2_name | expandedMode | slot protocol | 触发? | 正确? |
|---------|-------------|---------------|-------|-------|
| `vision-ws-grpc` | [vision,ws,grpc] | ws | `in_array("vision",...)` ✅ → 2053 | ✅ |
| `vision-ws-grpc` | [vision,ws,grpc] | grpc | 同上 → 2053 | ✅ |
| `vision-hy2` | [vision,vision,hy2] | vision | 不触发 (vision≠ws/grpc) | ✅ |
| `vision-hy2` | [vision,vision,hy2] | hy2 | 不触发 (hy2≠ws/grpc) | ✅ |
| `vision` | [vision,vision,vision] | vision | 不触发 | ✅ |
| `xhttp-*` 系列 | 无 "vision" | 任意 | 不触发 | ✅ |

#### T4.4: `nginxConfig()` — 模板选择

```php
// L1569: 使用 mainNode 的 v2_name 定位 nginx 模板
$v2Name = $mainNode->v2_name ?: "vision-hy2-ws-grpc";
$templatePath = resource_path("templates/nginx/{$v2Name}.conf");
```

✅ 直接使用 v2_name 作为文件名, 只要模板文件存在即可。无代码修改需要。

#### T4.5: `SubscribeController` — 订阅链接生成

订阅控制器根据 `v2_net` 生成链接:
- `tcp` + `v2_flow=xtls-rprx-vision` → vless 链接 ✅
- `xhttp` → vless xhttp 链接 ✅
- `hysteria2` → hy2 链接 ✅
- `ws` → vmess ws 链接 ✅
- `grpc` → vless grpc 链接 ✅

新协议不引入新的 `v2_net` 值, 订阅逻辑**无需修改**。✅

#### T4.6: `applySniPrefix()` — SNI 用户前缀

现有逻辑排除 gRPC 和 ws(CDN) 节点。新协议中:
- vision clone: v2_net=tcp → **应用** SNI 前缀 ✅
- xhttp clone: v2_net=xhttp → **应用** SNI 前缀 ✅
- hy2 clone: v2_net=hysteria2 → **应用** SNI 前缀 ✅

无需修改。✅

---

## 5. 实施顺序

```
阶段 1: 代码修改
  T1 → 修改 expandProtocols + 新增 V2_PROTOCOL_SLOTS
  T4.2-fix → config() 中 inboundTags 加 array_unique

阶段 2: 模板文件
  T2 → 创建 6 个 xray JSON 模板
  T3 → 创建 6 个 nginx conf 模板

阶段 3: 权限修复
  podman exec php7-npanel chown -R www-data:www-data /var/www/NPanel/resources/templates/
  podman exec php7-npanel chown -R www-data:www-data /var/www/NPanel/app/
  podman exec php7-npanel php /var/www/NPanel/composer.phar dump-autoload

阶段 4: 测试验证
```

---

## 6. 测试用例

### 6.1 `expandProtocols` 单元测试

```php
// 现有协议 (不回归)
$this->assertEquals(["vision","hy2","ws","grpc"], $ctrl->expandProtocols("vision-hy2-ws-grpc"));
$this->assertEquals(["xhttp","hy2","ws","grpc"], $ctrl->expandProtocols("xhttp-hy2-ws-grpc"));

// 新协议
$this->assertEquals(["vision","ws","grpc"],         $ctrl->expandProtocols("vision-ws-grpc"));
$this->assertEquals(["vision","vision","hy2"],       $ctrl->expandProtocols("vision-hy2"));
$this->assertEquals(["vision","vision","vision"],    $ctrl->expandProtocols("vision"));
$this->assertEquals(["xhttp","ws","grpc"],           $ctrl->expandProtocols("xhttp-ws-grpc"));
$this->assertEquals(["xhttp","xhttp","hy2"],         $ctrl->expandProtocols("xhttp-hy2"));
$this->assertEquals(["xhttp","xhttp","xhttp"],       $ctrl->expandProtocols("xhttp"));

// 兜底 (未知协议名)
$this->assertEquals(["foo","bar"], $ctrl->expandProtocols("foo-bar"));
```

### 6.2 裂变矩阵验证 (register 逻辑)

| v2_name | IPv4 | 预期 slot 数 | 主节点协议 | clone 协议列表 |
|---------|------|-------------|-----------|---------------|
| `vision-ws-grpc` | 1.2.3.4 | 3 | vision | ws, grpc |
| `vision-hy2` | 1.2.3.4 | 3 | vision | vision, hy2 |
| `vision` | 1.2.3.4 | 3 | vision | vision, vision |
| `xhttp-ws-grpc` | 1.2.3.4 | 3 | xhttp | ws, grpc |
| `xhttp-hy2` | 1.2.3.4 | 3 | xhttp | xhttp, hy2 |
| `xhttp` | 1.2.3.4 | 3 | xhttp | xhttp, xhttp |

### 6.3 config 下发验证

对每个新协议的主节点调用 `config()`, 验证:
- 模板文件存在且 JSON 合法
- inbound 列表与预期一致
- 变量替换正确 (`__nodeDomain__`, `__wsPort__` 等)

对每个 clone 节点调用 `config()`, 验证:
- 只保留 `api` + 匹配的协议 inbound
- `ssrpanel.nodeId` 正确
- `ssrpanel.user.inboundTags` 无重复值

### 6.4 nginx 下发验证

对每个新协议的主节点调用 `nginxConfig()`, 验证:
- 模板文件存在
- 变量替换正确
- nginx -t 语法检查通过

### 6.5 订阅链接验证

对每种协议的 clone 节点, 验证 SubscribeController 生成的链接:
- vision clone: vless://...?type=tcp&flow=xtls-rprx-vision&security=tls&...
- xhttp clone: vless://...?type=xhttp&...
- hy2 clone: hysteria2://...?alpn=h3&...
- ws clone: vmess://... (base64)
- grpc clone: vless://...?type=grpc&...

---

## 7. 风险与注意事项

| 风险 | 缓解措施 |
|------|---------|
| 向后兼容性 | `V2_PROTOCOL_SLOTS` 包含现有两套协议, 行为与 `explode` 一致 |
| 域名配额 | 新协议每个最多 3 个 DNS 记录 (与现有 4 个相比更少), 确保域名池充足 |
| inboundTags 重复 | T4.2-fix 添加 `array_unique` |
| 模板文件遗漏 | 所有模板由源模板复制+删除, 保持结构一致 |
| 文件权限 | 新建文件后执行 chown/chmod |
| `vision.conf` = `vision-hy2.conf` | 正常, 虽然内容相同但语义不同 (协议不同), 独立文件便于后续差异化 |
| `xhttp.conf` = `xhttp-hy2.conf` | 同上 |
