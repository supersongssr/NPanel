# 节点 1114-1121 代理配置未正确写入数据库 — 调查报告

> 调查日期: 2026-05-02
> 影响节点: node_ids="1114,1115,1116,1117,1118,1119,1120,1121"
> 相关代码: `app/Http/Controllers/Api/NodeApiController.php` — `register()` 方法

---

## 一、节点关系总览

`register()` 将一个物理节点"裂变"为多个逻辑节点 — 主节点持有完整协议组，每个 clone 只持有一个单协议。

| 节点 ID | 角色 | v2_name | type | server | IP |
|---------|------|---------|------|--------|----|
| 1114 | 主节点 | `xhttp-hy2-ws-grpc` | 1 | node1114.ssmail.win | 23.148.12.136 (v4) + 2602:f50a:1:c25:... (v6) |
| 1115 | clone (xhttp/ipv4) | `xhttp` | 3 | node1115.ssmail.win | 23.148.12.136 |
| 1116 | clone (ws/ipv4) | `ws` | 3 | node1116.ssmail.win | 23.148.12.136 |
| 1117 | clone (grpc/ipv4) | `grpc` | 3 | node1117.ssmail.win | 23.148.12.136 |
| 1118 | clone (hy2/ipv6) | `hy2` | 3 | node1118.ssmail.win | 2602:f50a:1:c25:... |
| 1119 | clone (xhttp/ipv6) | `xhttp` | 3 | node1119.ssmail.win | 2602:f50a:1:c25:... |
| 1120 | clone (ws/ipv6) | `ws` | 3 | node1120.ssmail.win | 2602:f50a:1:c25:... |
| 1121 | clone (grpc/ipv6) | `grpc` | 3 | node1121.ssmail.win | 2602:f50a:1:c25:... |

主节点 1114 的 `node_ids = "1114,1115,1116,1117,1118,1119,1120,1121"`，所有 clone 的 `is_clone = 1114`。

---

## 二、发现的 Bug（共 3 个）

### Bug 1: Clone 节点缺少 6 个关键字段 — `register()` 未从主节点继承

**代码位置**: `NodeApiController.php:265-311`

`register()` 的裂变逻辑中，clone 节点只复制了以下字段：

```
name, v2_name, node_rxtx, ip/ipv6, type, level, node_group, traffic_rate, status, server
```

**缺失字段**（clone 节点 1115-1121 为空或默认值）：

| 字段 | 主节点 1114 值 | Clone 值 | 影响 |
|------|--------------|----------|------|
| `node_country` | `United States` | `null` | 前端不显示国家名 |
| `node_city` | `Ashburn` | `null` | 前端不显示城市 |
| `node_unlock` | `netflix=Yes&disney=Yes...` | `""` (空) | 前端不显示解锁信息；config 下发时不生成解锁路由 |
| `node_cpu` | `4` | `null` | 前端不显示 CPU 规格 |
| `node_memory` | `3.8` | `null` | 前端不显示内存规格 |
| `node_disk` | `50` | `null` | 前端不显示磁盘规格 |

**根因**: `register()` 方法在创建/复活 clone 时（第 265-311 行），没有从主节点 `$node` 复制这些展示和配置字段。对比主节点赋值（第 187-208 行）和 clone 赋值（第 269-280 / 292-306 行），明显缺少 `node_country`、`node_city`、`node_unlock`、`node_cpu`、`node_memory`、`node_disk`、`sort`、`info` 的传递。

---

### Bug 2: Clone 节点 `country_code` 为 `"un"` 而非继承主节点值

**代码位置**: `NodeApiController.php:265-311`

| 字段 | 主节点 1114 | Clone 1115-1121 |
|------|-----------|-----------------|
| `country_code` | `us` | `un`（数据库默认值） |

原因：clone 节点创建时没有设置 `country_code`，完全依赖数据库默认值 `un`。前端无法显示正确的国旗图标。

---

### Bug 3: 主节点 `type` 字段未设置为 3

**代码位置**: `NodeApiController.php:186-209`

| 节点 | type | 说明 |
|------|------|------|
| 1114 (主节点) | `1` | ❌ 应为 `3` |
| 1115-1121 (clone) | `3` | ✅ 正确 |
| 156 (健康主节点参考) | `3` | ✅ 正确 |

原因：
- `register()` 在更新主节点时**没有显式设置 `type` 字段**（第 187-209 行无 `$node->type = 3`）
- 节点 1114 是从回收池复活的旧节点（`applyId()` 中 recycled=true），其旧 `type=1` 被原样保留
- 而 clone 创建时硬编码了 `$clone->type = 3`（第 274/298 行），所以 clone 反而是正确的

---

## 三、config.json 下发情况分析

主节点（1114）调用 `/api/node/config` 时使用 `v2_name = "xhttp-hy2-ws-grpc"`，对应模板 `resources/templates/xray/xhttp-hy2-ws-grpc.json` 存在，所以 **config.json 正常下发**。

但如果客户端**为 clone 节点（如 1115）单独请求 config**，则会导致 **500 错误**：

```
clone v2_name = "xhttp"
模板路径: resources/templates/xray/xhttp.json => 文件不存在
```

现有模板文件仅有：
- `resources/templates/xray/xhttp-hy2-ws-grpc.json` ✅
- `resources/templates/xray/vision-hy2-ws-grpc.json` ✅
- `xhttp.json`, `ws.json`, `grpc.json`, `hy2.json` 等 ❌ 均不存在

---

## 四、修复建议

### 修复点 1: clone 节点继承主节点展示字段

在 `NodeApiController.php` clone 创建的两个分支（第 269 行 revive 分支、第 292 行新建分支）中补充字段继承：

```php
// 添加到 clone 字段赋值区域
$clone->country_code = $node->country_code;
$clone->node_country = $node->node_country;
$clone->node_city = $node->node_city;
$clone->node_unlock = (string)$node->node_unlock;
$clone->node_cpu = $node->node_cpu;
$clone->node_memory = $node->node_memory;
$clone->node_disk = $node->node_disk;
$clone->sort = $node->sort;
$clone->info = $node->info;
```

### 修复点 2: 主节点显式设置 type

在 `register()` 主节点赋值区域（约第 208 行 `$node->status = 1` 之前）添加：

```php
$clone->type = 3;
```

### 修复点 3（可选）: clone 节点的 config 下发

如果客户端需要为 clone 单独请求 config，可在 `config()` 方法中为 clone 节点回退到其主节点的模板：

```php
// 当 clone 的 v2_name 模板不存在时，使用主节点的 v2_name
if (!file_exists($templatePath) && $node->is_clone) {
    $parent = SsNode::find($node->is_clone);
    if ($parent) {
        $v2Name = $parent->v2_name;
        $templatePath = resource_path("templates/xray/{$v2Name}.json");
    }
}
```
