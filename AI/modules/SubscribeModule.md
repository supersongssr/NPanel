# SubscribeModule - 订阅服务模块

## 概述
SubscribeModule 负责生成和管理用户订阅链接，支持多种客户端配置格式（SSR、V2Ray、Clash、Surge等），根据用户权限和等级自动过滤可用节点。

## 技术信息

### 控制器
- `SubscribeController` - 订阅服务控制器
- 位置：`app/Http/Controllers/SubscribeController.php`

### 数据模型
- `UserSubscribe` - 用户订阅信息
- `SsNode` - 节点信息
- `User` - 用户信息
- `SsGroup` - 节点分组
- `SsGroupNode` - 节点分组关联

## 支持的客户端格式

### 1. Shadowsocks/SSR
- **格式**: Base64编码的SSR链接
- **协议**: ssr://
- **示例**: `ssr://server:port:protocol:method:obfs:password/?remarks=...`

### 2. V2Ray
- **格式**: JSON配置文件
- **协议**: vmess://
- **示例**: V2Ray客户端标准JSON格式

### 3. Clash
- **格式**: YAML配置文件
- **类型**: 代理配置
- **示例**: Clash标准YAML格式

### 4. Surge
- **格式**: 文本配置
- **类型**: 脚本和代理配置
- **示例**: Surge脚本格式

### 5. QuantumultX
- **格式**: 文本配置
- **类型**: 节点列表
- **示例**: QuantumultX标准格式

### 6. Shadowrocket
- **格式**: 订阅链接
- **类型**: 节点配置
- **示例**: 小火箭订阅格式

## API 接口

### 1. 获取订阅内容
```http
GET /subscribe/{token}
Accept: application/vnd.apple.mpegurl
```

### 2. API订阅接口
```http
GET /api/subscribe?token={user_token}&client=clash
```

**查询参数**:
- `token`: 用户订阅令牌
- `client`: 客户端类型 (可选)
- `expand`: 是否展开节点信息 (可选)

### 3. 获取订阅信息
```http
GET /subscribe/info/{token}
```

### 4. 重置订阅码
```http
POST /subscribe/reset
Authorization: Bearer {user_token}
```

## 订阅令牌格式

订阅令牌通常是Base64编码的用户信息：
```
token = Base64Encode(userID . '_' . timestamp . '_' . sign)
```

## 客户端识别

通过HTTP Accept头自动识别客户端类型：

| Accept头 | 客户端类型 | 输出格式 |
|----------|------------|----------|
| `application/json` | 通用 | JSON |
| `application/vnd.apple.mpegurl` | iOS/Mac | Base64 |
| `text/plain` | 通用 | Base64 |
| `application/octet-stream` | 下载 | 文件下载 |
| `application/yaml` | Clash | YAML |
| `text/yaml` | Clash | YAML |

## 数据结构

### SSR节点配置
```json
{
    "server": "hk.example.com",
    "server_port": 443,
    "protocol": "origin",
    "method": "aes-256-gcm",
    "obfs": "plain",
    "password": "user_password",
    "remarks": "香港节点-01",
    "group": "NPanel",
    "obfs_param": "",
    "protocol_param": ""
}
```

### V2Ray节点配置
```json
{
    "v": "2",
    "ps": "香港节点-01",
    "add": "hk.example.com",
    "port": 443,
    "id": "user_uuid",
    "aid": 16,
    "net": "ws",
    "type": "none",
    "host": "example.com",
    "path": "/path",
    "tls": "tls"
}
```

### Clash配置结构
```yaml
port: 7890
socks-port: 7891
mixed-port: 7892
allow-lan: true
mode: rule
log-level: info
external-controller: 127.0.0.1:9090

proxies:
  - name: "香港节点-01"
    type: ss
    server: hk.example.com
    port: 443
    cipher: aes-256-gcm
    password: user_password

proxy-groups:
  - name: "Proxy"
    type: select
    proxies:
      - "香港节点-01"
      - DIRECT

rules:
  - GEOIP,CN,DIRECT
  - MATCH,Proxy
```

## 使用方法

### 1. 生成SSR订阅
```php
public function getSsrSubscribe($user)
{
    $nodes = $this->getAvailableNodes($user);
    $ssrLinks = [];

    foreach ($nodes as $node) {
        if ($node->type == 1) { // SS/SSR节点
            $ssrLink = $this->buildSsrLink($node, $user);
            $ssrLinks[] = $ssrLink;
        }
    }

    return base64_encode(implode("\n", $ssrLinks));
}

private function buildSsrLink($node, $user)
{
    $server = $node->server ?: $node->ip;
    $port = $node->single ? $node->single_port : $user->port;
    $protocol = $node->single ? $node->single_protocol : $user->protocol;
    $method = $node->single ? $node->single_method : $user->method;
    $obfs = $node->single ? $node->single_obfs : $user->obfs;
    $password = $node->single ? $node->single_passwd : $user->passwd;
    $remarks = base64_encode($node->name);
    $group = base64_encode('NPanel');

    $ssr = "{$server}:{$port}:{$protocol}:{$method}:{$obfs}:" .
            base64_encode($password) . "/?remarks={$remarks}&group={$group}";

    return "ssr://" . base64_encode($ssr);
}
```

### 2. 生成V2Ray配置
```php
public function getV2RayConfig($user)
{
    $nodes = $this->getAvailableNodes($user);
    $configs = [];

    foreach ($nodes as $node) {
        if ($node->type == 2) { // V2Ray节点
            $config = [
                "v" => "2",
                "ps" => $node->name,
                "add" => $node->server ?: $node->ip,
                "port" => $node->v2_port,
                "id" => $user->uuid,
                "aid" => $node->v2_alter_id,
                "net" => $node->v2_net,
                "type" => $node->v2_type,
                "host" => $node->v2_host,
                "path" => $node->v2_path,
                "tls" => $node->v2_tls ? "tls" : ""
            ];
            $configs[] = "vmess://" . base64_encode(json_encode($config));
        }
    }

    return $configs;
}
```

### 3. 生成Clash配置
```php
public function getClashConfig($user)
{
    $nodes = $this->getAvailableNodes($user);
    $proxies = [];
    $proxyNames = [];

    foreach ($nodes as $node) {
        $proxy = $this->buildClashProxy($node, $user);
        if ($proxy) {
            $proxies[] = $proxy;
            $proxyNames[] = $proxy['name'];
        }
    }

    $config = [
        'port' => 7890,
        'socks-port' => 7891,
        'mixed-port' => 7892,
        'allow-lan' => true,
        'mode' => 'rule',
        'log-level' => 'info',
        'external-controller' => '127.0.0.1:9090',
        'proxies' => $proxies,
        'proxy-groups' => [
            [
                'name' => 'Proxy',
                'type' => 'select',
                'proxies' => array_merge($proxyNames, ['DIRECT'])
            ]
        ],
        'rules' => [
            'GEOIP,CN,DIRECT',
            'MATCH,Proxy'
        ]
    ];

    return Yaml::dump($config, 4, 2);
}
```

### 4. 节点过滤逻辑
```php
private function getAvailableNodes($user)
{
    $query = SsNode::where('status', 1)
        ->where('is_subscribe', 1);

    // 根据用户等级过滤
    if ($user->level > 0) {
        $query->where('level', '<=', $user->level);
    } else {
        $query->where('level', 0);
    }

    // 根据用户分组过滤
    if (!empty($user->node_group)) {
        $groupIds = explode(',', $user->node_group);
        $query->whereIn('node_group', $groupIds);
    }

    // 按排序字段排序
    $nodes = $query->orderBy('sort', 'desc')
        ->orderBy('id', 'asc')
        ->get();

    return $nodes;
}
```

## 订阅信息统计

```php
public function getSubscribeInfo($user)
{
    // 计算流量使用情况
    $totalUsed = $user->u + $user->d;
    $totalTransfer = $user->transfer_enable;
    $unused = $totalTransfer - $totalUsed;
    $percentage = ($totalUsed / $totalTransfer) * 100;

    // 获取可用节点数量
    $nodeCount = SsNode::where('status', 1)
        ->where('is_subscribe', 1)
        ->where(function($query) use ($user) {
            if ($user->level > 0) {
                $query->where('level', '<=', $user->level);
            } else {
                $query->where('level', 0);
            }
        })
        ->count();

    return [
        'upload' => formatBytes($user->u),
        'download' => formatBytes($user->d),
        'total' => formatBytes($totalUsed),
        'unused' => formatBytes($unused),
        'usage' => round($percentage, 2) . '%',
        'expire' => $user->expire_time,
        'node_count' => $nodeCount,
        'class' => $user->level,
        'class_expire' => $user->class_expire
    ];
}
```

## 约束条件

1. **访问控制**
   - 订阅令牌必须有有效期
   - 用户只能访问有权限的节点
   - 访问频率限制

2. **数据安全**
   - 节点配置信息必须加密
   - 敏感信息不能在日志中记录
   - 用户隐私保护

3. **性能要求**
   - 支持大量并发请求
   - 使用缓存减少数据库查询
   - 响应时间控制在500ms内

## 最佳实践

### 1. 使用缓存优化性能
```php
// 缓存用户节点列表
$cacheKey = "user_nodes_{$user->id}_{$user->level}_{$user->node_group}";
$nodes = Cache::remember($cacheKey, 300, function() use ($user) {
    return $this->getAvailableNodes($user);
});
```

### 2. 异步处理统计信息
```php
// 异步更新订阅访问日志
UpdateSubscribeLog::dispatch([
    'user_id' => $user->id,
    'ip' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'client_type' => $this->detectClient()
]);
```

### 3. 客户端自动识别
```php
private function detectClient()
{
    $userAgent = request()->userAgent();
    $accept = request()->header('Accept');

    if (strpos($userAgent, 'Clash') !== false) {
        return 'clash';
    } elseif (strpos($userAgent, 'Surge') !== false) {
        return 'surge';
    } elseif (strpos($userAgent, 'Quantumult') !== false) {
        return 'quantumult';
    }

    // 根据 Accept 头部判断
    if ($accept && strpos($accept, 'yaml') !== false) {
        return 'clash';
    }

    return 'default';
}
```

## 测试要点

1. **格式兼容性**
   - 各种客户端格式正确性
   - Base64编码解码
   - JSON/YAML格式验证

2. **权限控制**
   - 节点过滤正确性
   - 用户权限验证
   - 令牌有效性

3. **性能测试**
   - 并发访问性能
   - 缓存效果
   - 响应时间

4. **边界情况**
   - 无可用节点
   - 过期用户
   - 无效令牌

## 注意事项

1. 订阅令牌需要定期更新
2. 节点配置变更需要清理缓存
3. 大量节点时需要分页处理
4. 支持CDN加速订阅链接
5. 记录订阅访问日志用于分析
6. 支持自定义客户端规则
7. 定期清理过期的订阅数据