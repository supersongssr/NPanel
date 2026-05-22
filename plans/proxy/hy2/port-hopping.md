# Hysteria2 Port Hopping (动态端口) 实现计划

## Context

NPanel 的两个多协议 Xray 模板中，Hysteria2 入站固定监听 UDP 443 端口。用户希望支持 **Port Hopping（端口跳跃）**——在保持 443 主端口的同时，允许客户端在指定端口范围内动态切换 UDP 端口，以规避运营商针对单一端口的 QoS 限速。

### 设计决策

- **服务端 Xray 只监听 443**：`__hy2Port__` 保持为 `443` 不变。额外端口通过 iptables REDIRECT 将 UDP 流量重定向到 443。
  - 原因：Xray 监听大量端口会消耗大量资源（文件描述符、内存），而 iptables REDIRECT 是内核级转发，几乎零开销。
- **客户端 hy2:// URL 携带端口范围**：支持 port hopping 的客户端（Hysteria2 原生、NekoBox、mihomo 等）会随机选择端口并周期性跳换。
- **跳跃间隔**：由客户端自行决定（Hysteria2 默认 30s），服务端不控制。
- **端口范围**：`30000-32000`（2000 个端口，避开高位端口被屏蔽的风险）。
- **Sing-box 和 Clash**：目前不支持 port hopping，继续使用主端口 443。

### 为什么不能复用 `v2_insider_port` / `v2_outsider_port`

这两个字段是为 **rico63 第三方插件** 设计的特殊部署模式（`v2_port=0` 时生效）：
- `v2_insider_port`：内部监听端口（INT，如 10550）
- `v2_outsider_port`：外部公共端口（INT，如 443）

**不能复用的原因**：
1. 数据类型不匹配 — 两者是 `INT(11)` 单端口，port hopping 需要 `VARCHAR` 存储范围字符串如 `45678-50000`
2. 语义冲突 — 改变它们可能影响已有的 rico63 插件用户
3. 不在 SsNode 模型的 `$fillable` 数组中

### hy2 动态端口订阅格式

根据 [Hysteria 2 官方 URI Scheme](https://v2.hysteria.network/docs/developers/URI-Scheme/)：

```
hy2://uuid@server:443,30000-32000?sni=example.com&insecure=1#节点名
```

- 端口部分格式：`主端口,跳跃范围`
- 支持格式：单端口 `443`、范围 `30000-32000`、混合 `443,30000-32000`
- 跳跃间隔由客户端控制（Hysteria2 默认 30s）

---

## 实现步骤

### 1. 数据库迁移 — 添加 `v2_hop_ports` 字段

**文件**: `database/migrations/2026_05_22_000000_add_v2_hop_ports_to_ss_node_table.php` (新建)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddV2HopPortsToSsNodeTable extends Migration
{
    public function up()
    {
        Schema::table('ss_node', function (Blueprint $table) {
            $table->string('v2_hop_ports')->nullable()->after('v2_port')
                ->comment('Hysteria2端口跳跃范围(如30000-32000)');
        });
    }

    public function down()
    {
        Schema::table('ss_node', function (Blueprint $table) {
            $table->dropColumn('v2_hop_ports');
        });
    }
}
```

- `NULL` 或空字符串 = 不启用 port hopping（默认，向后兼容）
- 填充值如 `30000-32000` = 启用 port hopping，主端口始终来自 `v2_port`（443）

### 2. 更新 SsNode 模型

**文件**: `app/Http/Models/SsNode.php` (第 19 行 `$fillable` 数组)

在 `'v2_port'` 之后添加 `'v2_hop_ports'`。

### 3. 更新客户端订阅 hy2:// URI

**文件**: `app/Http/Controllers/SubscribeController.php` (第 429-436 行)

修改端口部分，当 `v2_hop_ports` 有值时拼接多端口格式：

```php
$hy2Port = $node->v2_port;
if (!empty($node->v2_hop_ports)) {
    $hy2Port = $node->v2_port . ',' . $node->v2_hop_ports;
}
$hy2Url = sprintf(
    "hy2://%s@%s:%s?sni=%s&insecure=1#%s\n",
    $node_uuid,
    $node->server,
    $hy2Port,
    rawurlencode($node->v2_sni),
    $encodedName
);
```

**Sing-box** (第 965-978 行) 和 **Clash** (第 1312-1322 行) 配置保持不变 — 不支持 port hopping，继续使用主端口。

### 4. 更新 Admin 后端 — 持久化 v2_hop_ports

**文件**: `app/Http/Controllers/AdminController.php`

**addNode** (第 895 行之后):
```php
$ssNode->v2_hop_ports = $request->get('v2_hop_ports') ?: null;
```

**editNode** (第 1030 行之后的 `$data` 数组中):
```php
'v2_hop_ports' => $request->get('v2_hop_ports') ?: null,
```

### 5. 更新 Admin 前端 — 添加表单字段

**文件**: `resources/views/admin/editNode.blade.php`

- 第 471 行（`v2_port` 的 `</div>` 之后）插入新的 form-group：
```html
<div class="form-group">
    <label for="v2_hop_ports" class="col-md-3 control-label">HY2 Port Hopping</label>
    <div class="col-md-8">
        <input type="text" class="form-control" name="v2_hop_ports"
               value="{{ $node->v2_hop_ports }}" id="v2_hop_ports"
               placeholder="30000-32000">
        <span class="help-block">Hysteria2 端口跳跃范围（可选，留空禁用，推荐 30000-32000，跳跃间隔由客户端控制，默认 30s）</span>
    </div>
</div>
```
- 第 687 行之后添加 JS 变量：`var v2_hop_ports = $('#v2_hop_ports').val();`
- 第 748 行之后在 AJAX data 中添加：`v2_hop_ports: v2_hop_ports,`

**文件**: `resources/views/admin/addNode.blade.php`

同样的三处修改（form-group、JS 变量、AJAX data），位置对应 addNode 模板中的 `v2_port` 字段之后。

### 6. 更新节点重置逻辑

**文件**: `app/Http/Controllers/Api/NodeApiController.php` (第 607 行之后)

在 `resetNodeToDefaults()` 方法中添加：
```php
$node->v2_hop_ports = null;
```

---

## 不涉及的文件

- **Xray 模板 JSON 文件** — 无需修改，服务端 `__hy2Port__` 始终为 `443`
- **NodeApiController.php 的 `__hy2Port__` 变量** — 保持硬编码 `443` 不变
- **Sing-box / Clash 订阅格式** — 不支持 port hopping，继续使用主端口
- **iptables 规则生成** — 不在 NPanel 范围内，节点运维人员需手动配置：
```bash
# 将 UDP 30000-32000 重定向到 443
iptables -t nat -A PREROUTING -p udp --dport 30000:32000 -j REDIRECT --to-port 443
ip6tables -t nat -A PREROUTING -p udp --dport 30000:32000 -j REDIRECT --to-port 443
```

---

## 验证方法

1. **迁移测试**: 运行迁移，确认 `ss_node` 表新增 `v2_hop_ports` 列且所有现有节点值为 NULL
2. **无 port hopping 节点**: 验证 hy2:// URL 仍为 `server:443`，行为完全不变
3. **有 port hopping 节点**: 设置 `v2_hop_ports = "30000-32000"`，验证：
   - Xray config 中 hy2 inbound 的 `port` 保持 `443`
   - 订阅 URL 变为 `hy2://uuid@server:443,30000-32000?sni=...#name`
   - Sing-box 和 Clash 配置不变，仍使用 `server_port: 443`
4. **容器内测试**: 在 PHP 容器中运行 `php artisan migrate` 确认迁移成功
