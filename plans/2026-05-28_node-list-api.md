# 开发计划: 新增获取所有节点原始配置的 JSON API

> 日期: 2026-05-28
> 状态: **待审核**

---

## 概述

| 项目 | 内容 |
|------|------|
| **what** | 新增一个 API，返回数据库 `ss_node` 表中所有节点的原始 JSON 数据 |
| **why** | 方便其他应用（运维工具、监控面板、自动化脚本）交互查询全部节点信息 |
| **where** | `app/Http/Controllers/Api/NodeApiController.php` + `routes/api.php` |

### 现有 API 对比

| 现有路由 | 作用 | 差异 |
|---------|------|------|
| `GET /api/node_config` | 查询单个节点的部分字段 (name, v2_host, server 等 ~12 个字段) | 只返回 1 个节点 + 精选字段，不完整 |
| `GET /api/node/new` | 获取一个可用节点 | 仅返回单个空闲节点 |
| `POST /api/node/config` | 获取节点 xray 运行配置 | 返回渲染后的模板，非原始数据 |

**本计划新增**: `GET /api/nodes` → 返回**所有**节点的**全部数据库字段**。

---

## 任务清单

### T1: 新增路由

- **文件**: `routes/api.php`
- **变更**: 在 `Api` namespace group 内（不经过 `node.api.token` 中间件组），新增:
  ```
  Route::get('nodes', 'NodeApiController@listAll');
  ```
- **说明**:
  - 使用 `GET` 方法，RESTful 语义（资源集合用复数名词 `nodes`）
  - 不放入 `node.api.token` 中间件组，方法内自行做双重 Token 验证（Bearer Header 优先 + `?token=` fallback）

### T2: 新增控制器方法 `listAll`

- **文件**: `app/Http/Controllers/Api/NodeApiController.php`
- **方法签名**: `public function listAll(Request $request)`
- **逻辑**:

```php
public function listAll(Request $request)
{
    // 1. Token 验证: Bearer Header 优先, fallback 到 ?token= 查询参数
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

    // 2. 查询所有节点，按 id 排序
    $nodes = SsNode::orderBy('id', 'asc')->get();

    // 3. 直接返回原始 JSON
    return response()->json([
        'status' => 'success',
        'count'  => $nodes->count(),
        'data'   => $nodes,
    ]);
}
```

### 设计决策（已确认 ✅）

| # | 决策点 | 结论 |
|---|--------|------|
| 1 | **认证方式** | **双重认证**: Bearer Header 优先，fallback 到 `?token=` 查询参数 |
| 2 | **字段范围** | 全量字段（原始数据） |
| 3 | **节点过滤** | 返回所有节点（含 status=0 / 离线），不过滤 |
| 4 | **分页** | 一次性返回全部 |
| 5 | **路由路径** | `GET /api/nodes` |

---

## 影响范围

| 文件 | 变更类型 | 说明 |
|------|---------|------|
| `routes/api.php` | 新增 1 行 | 添加 `GET /api/nodes` 路由 |
| `app/Http/Controllers/Api/NodeApiController.php` | 新增 1 个方法 | 添加 `listAll()` 约 15 行 |

**无数据库变更，无前端变更，无中间件变更。**

---

## 测试方案

```bash
# 无 token → 401
curl http://localhost/api/nodes

# 方式 1: Bearer Header (优先)
curl -H "Authorization: Bearer YOUR_API_TOKEN" http://localhost/api/nodes

# 方式 2: 查询参数 (fallback)
curl "http://localhost/api/nodes?token=YOUR_API_TOKEN"
```

预期响应格式:
```json
{
    "status": "success",
    "count": 42,
    "data": [
        {
            "id": 1,
            "name": "US-NewYork",
            "server": "n1.example.com",
            "ip": "1.2.3.4",
            "v2_net": "tcp",
            "v2_port": 443,
            "status": 1,
            ...
        },
        ...
    ]
}
```

---

## 执行步骤

- [ ] T1: 在 `routes/api.php` 添加路由 `GET /api/nodes`
- [ ] T2: 在 `NodeApiController.php` 添加 `listAll()` 方法（双重认证 + 全量返回所有节点）
- [ ] T3: 测试验证
  - 无 token 返回 401
  - Bearer Header 认证返回全部节点数据
  - `?token=` 查询参数认证返回全部节点数据
  - 响应包含所有字段、所有节点（含离线）
- [ ] T4: 容器内权限修复（如需要）
