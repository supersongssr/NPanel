# NodeManagementModule - 节点管理模块

## 概述
NodeManagementModule 负责管理代理节点的增删改查、负载监控、在线状态检测、流量统计等功能，是 SSR/V2Ray/Trojan 代理服务的核心管理模块。

## 技术信息

### 控制器
- `AdminController` - 节点管理功能集成在管理员控制器中
- 相关方法：`nodeList()`, `node()`, `nodeMonitor()`, `updateNode()`, `deleteNode()`
- 位置：`app/Http/Controllers/AdminController.php`

### 数据模型
- `SsNode` - 节点信息模型
- `SsNodeInfo` - 节点监控信息模型
- `SsNodeOnlineLog` - 节点在线日志
- `SsNodeTrafficDaily` - 节点每日流量统计
- `SsNodeTrafficHourly` - 节点每小时流量统计
- `SsNodeLabel` - 节点标签关联
- `SsGroupNode` - 节点分组关联
- `SsNodeIp` - 节点IP记录
- `Country` - 国家信息
- `SsGroup` - 节点分组

## 主要功能

### 1. 节点基础管理
- 添加新节点
- 编辑节点信息
- 删除节点
- 节点列表查询
- 节点状态管理

### 2. 节点监控
- 实时负载监控
- 在线用户数统计
- 节点连通性检测
- 运行时间统计
- 性能指标监控

### 3. 流量统计
- 节点流量统计
- 每日/每小时流量分析
- 流量峰值监控
- 流量使用排行

### 4. 节点配置
- SS/SSR 配置
- V2Ray 配置
- Trojan 配置
- VLESS 配置
- 混淆和协议设置

## API 接口

### 1. 获取节点列表
```http
GET /admin/node/list?page=1&nodename=test&node_group=1&status=1
Authorization: Bearer {admin_token}
```

**响应**：
```json
{
    "status": "success",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name": "香港-01",
                "type": 1,
                "server": "hk1.example.com",
                "ip": "1.2.3.4",
                "country_code": "HK",
                "status": 1,
                "online_users": 25,
                "load": "0.25",
                "uptime": "15天3小时",
                "transfer": "1.2TB",
                "sort": 0,
                "level": 0
            }
        ],
        "total": 50
    }
}
```

### 2. 添加节点
```http
POST /admin/node
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "name": "香港-02",
    "type": 1,
    "server": "hk2.example.com",
    "ip": "1.2.3.5",
    "port": 443,
    "method": "aes-256-gcm",
    "group_id": 1,
    "node_group": 1,
    "level": 0,
    "country_code": "HK",
    "status": 1,
    "sort": 0
}
```

### 3. 更新节点
```http
PUT /admin/node/1
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "name": "香港-01-更新",
    "status": 0
}
```

### 4. 节点监控数据
```http
GET /admin/node/monitor/1
Authorization: Bearer {admin_token}
```

### 5. 删除节点
```http
DELETE /admin/node/1
Authorization: Bearer {admin_token}
```

## 节点配置详解

### 节点类型
| 类型值 | 说明 | 支持的协议 |
|--------|------|------------|
| 1 | SS/SSR | Shadowsocks, ShadowsocksR |
| 2 | V2Ray | V2Ray (VMess) |
| 3 | VLESS | VLESS 协议 |
| 4 | Trojan | Trojan 协议 |

### 节点状态
| 状态值 | 说明 |
|--------|------|
| 0 | 维护中 |
| 1 | 正常运行 |

### SS/SSR 节点配置
```php
$ssNode = [
    'method' => 'aes-256-gcm',
    'protocol' => 'origin',
    'obfs' => 'plain',
    'protocol_param' => '',
    'obfs_param' => '',
    'compatible' => true,  // 兼容原版SS
    'single' => false,     // 是否单端口多用户
];
```

### V2Ray 节点配置
```php
$v2Node = [
    'v2_port' => 443,
    'v2_method' => 'aes-128-gcm',
    'v2_alter_id' => 16,
    'v2_net' => 'ws',  // tcp, ws, h2, quic
    'v2_type' => 'none',
    'v2_host' => 'example.com',
    'v2_path' => '/path',
    'v2_tls' => 1,  // 0-无, 1-tls, 2-xtls
];
```

## 使用方法

### 1. 创建新节点
```php
$node = new SsNode();
$node->name = $request->name;
$node->type = $request->type;
$node->server = $request->server;
$node->ip = $request->ip;
$node->method = $request->method;
$node->group_id = $request->group_id;
$node->node_group = $request->node_group;
$node->level = $request->level;
$node->country_code = $request->country_code;
$node->status = 1;
$node->sort = $request->sort ?: 0;
$node->save();

// 处理节点标签
if ($request->has('labels')) {
    foreach ($request->labels as $labelId) {
        $nodeLabel = new SsNodeLabel();
        $nodeLabel->node_id = $node->id;
        $nodeLabel->label_id = $labelId;
        $nodeLabel->save();
    }
}
```

### 2. 节点状态监控
```php
// 获取节点在线状态
$onlineLog = SsNodeOnlineLog::where('node_id', $nodeId)
    ->where('log_time', '>=', strtotime("-2 hours"))
    ->orderBy('id', 'desc')
    ->first();

$node->online_users = $onlineLog ? $onlineLog->online_user : 0;

// 获取节点负载信息
$nodeInfo = SsNodeInfo::where('node_id', $nodeId)
    ->where('log_time', '>=', strtotime("-10 minutes"))
    ->orderBy('id', 'desc')
    ->first();

$node->load = $nodeInfo ? $nodeInfo->load : '离线';
$node->uptime = $nodeInfo ? seconds2time($nodeInfo->uptime) : 0;
```

### 3. 流量统计
```php
// 每日流量统计
$dailyTraffic = SsNodeTrafficDaily::where('node_id', $nodeId)
    ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))
    ->orderBy('created_at', 'asc')
    ->pluck('total')
    ->toArray();

// 每小时流量统计
$hourlyTraffic = SsNodeTrafficHourly::where('node_id', $nodeId)
    ->where('created_at', '>=', date('Y-m-d'))
    ->orderBy('created_at', 'asc')
    ->pluck('total')
    ->toArray();
```

### 4. 节点筛选查询
```php
$query = SsNode::query();

// 按名称搜索
if (!empty($nodename)) {
    $query->where('name', 'like', '%' . $nodename . '%');
}

// 按分组筛选
if (!empty($node_group)) {
    $query->where('node_group', $node_group);
}

// 按状态筛选
if (!empty($status)) {
    $query->where('status', $status);
}

// 按类型筛选
if (!empty($type)) {
    $query->where('type', $type);
}

$nodeList = $query->with(['label'])
    ->orderBy('sort', 'desc')
    ->orderBy('id', 'desc')
    ->paginate(10);
```

## 监控和告警

### 1. 节点离线检测
```php
// 检查节点是否离线（超过10分钟没有数据）
$offlineNodes = SsNode::where('status', 1)
    ->whereDoesntHave('info', function($query) {
        $query->where('log_time', '>=', strtotime("-10 minutes"));
    })
    ->get();

foreach ($offlineNodes as $node) {
    // 发送告警通知
    $this->sendNodeOfflineAlert($node);
}
```

### 2. 流量异常检测
```php
// 检测流量异常增长
$abnormalNodes = SsNodeTrafficHourly::where('created_at', '>=', Carbon::now()->subHour())
    ->where('total', '>', 1024 * 1024 * 1024 * 10) // 超过10GB
    ->with('node')
    ->get();
```

## 约束条件

1. **节点配置要求**
   - 节点必须通过连通性检测才能上线
   - 服务器地址必须是有效域名或IP
   - 端口必须在有效范围内(1-65535)
   - 加密方式必须支持

2. **数据完整性**
   - 删除节点需清理相关数据
   - 流量统计需要定期归档
   - 监控数据需要定期清理

3. **安全要求**
   - 管理员权限验证
   - 操作日志记录
   - 敏感信息加密

## 最佳实践

### 1. 节点批量操作
```php
// 批量更新节点状态
SsNode::whereIn('id', $nodeIds)
    ->update(['status' => 0]);

// 使用队列处理耗时操作
ProcessNodeUpdate::dispatch($nodeId);
```

### 2. 性能优化
```php
// 使用缓存减少数据库查询
$nodeList = Cache::remember('node_list', 300, function() {
    return SsNode::where('status', 1)
        ->with(['label', 'group'])
        ->orderBy('sort', 'desc')
        ->get();
});
```

### 3. 监控数据处理
```php
// 异步处理节点监控数据
ProcessNodeStats::dispatch([
    'node_id' => $nodeId,
    'load' => $load,
    'uptime' => $uptime,
    'online_users' => $onlineUsers,
    'traffic' => $traffic
]);
```

## 测试要点

1. **节点管理功能**
   - 添加/编辑/删除节点
   - 节点配置验证
   - 批量操作功能

2. **监控功能**
   - 实时状态更新
   - 负载数据准确性
   - 离线检测

3. **流量统计**
   - 数据准确性
   - 性能表现
   - 数据展示

4. **权限控制**
   - 管理员权限验证
   - 操作限制
   - 日志记录

## 注意事项

1. 节点删除需要级联删除相关数据
2. 监控数据需要设置自动清理
3. 节点配置需要完整验证
4. 批量操作需要使用队列处理
5. 敏感操作需要二次确认
6. 定期备份节点配置
7. 监控异常告警需要及时处理