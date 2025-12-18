# ApiModule - API接口模块

## 概述
ApiModule 提供RESTful API接口，用于第三方集成、客户端接入和数据交互。支持节点数据上报、用户认证、订阅获取、流量统计等功能，是系统对外提供服务的核心接口模块。

## 技术信息

### 控制器
- `Api/LoginController` - API登录认证
- `Api/PingController` - 节点心跳和数据上报
- `Api/*PayController` - 支付回调API
- 位置：`app/Http/Controllers/Api/`

### 数据模型
- `User` - 用户信息
- `SsNode` - 节点信息
- `SsNodeInfo` - 节点监控信息
- `SsNodeOnlineLog` - 节点在线日志
- `Payback` - 支付回调记录

## API版本控制

### 当前版本
- **API Version**: v1
- **Base URL**: `https://example.com/api/v1`
- **Content-Type**: `application/json`

### 版本策略
- URL路径版本控制：`/api/v1/`
- 向后兼容原则
- 废弃API提前通知

## 认证方式

### 1. JWT Token认证
```http
Authorization: Bearer {jwt_token}
```

### 2. API Key认证（节点使用）
```http
X-API-Key: {node_api_key}
```

### 3. 签名验证（回调接口）
```http
X-Signature: sha256={signature}
```

## 核心API接口

### 1. 用户认证接口

#### API登录
```http
POST /api/login
Content-Type: application/json

{
    "username": "user@example.com",
    "password": "password123",
    "client_type": "node"
}
```

**响应**：
```json
{
    "status": "success",
    "data": {
        "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "token_type": "Bearer",
        "expires_in": 86400,
        "user": {
            "id": 1,
            "username": "user@example.com",
            "level": 2,
            "expire_time": "2025-12-18 00:00:00",
            "transfer_enable": 107374182400
        }
    }
}
```

#### 刷新Token
```http
POST /api/refresh
Authorization: Bearer {refresh_token}
```

#### 退出登录
```http
POST /api/logout
Authorization: Bearer {token}
```

### 2. 节点管理接口

#### 节点心跳上报
```http
POST /api/ping
Content-Type: application/json
X-API-Key: {node_api_key}

{
    "node_id": 1,
    "load": "0.25",
    "uptime": 86400,
    "online_users": 25,
    "memory_usage": 60.5,
    "cpu_usage": 30.2,
    "disk_usage": 45.8
}
```

**响应**：
```json
{
    "status": "success",
    "message": "Node status updated",
    "data": {
        "config_updated": false,
        "users_list_updated": true,
        "next_ping_interval": 60
    }
}
```

#### 获取节点配置
```http
GET /api/node/config/{node_id}
X-API-Key: {node_api_key}
```

**响应**：
```json
{
    "status": "success",
    "data": {
        "node_id": 1,
        "users": [
            {
                "id": 1,
                "uuid": "550e8400-e29b-41d4-a716-446655440000",
                "port": 10001,
                "password": "user_password",
                "method": "aes-256-gcm",
                "speed_limit": 10485760,
                "expire_time": "2025-12-18 00:00:00"
            }
        ],
        "traffic_stats": {
            "upload": 1073741824,
            "download": 2147483648
        }
    }
}
```

### 3. 流量统计接口

#### 流量数据上报
```http
POST /api/traffic/report
X-API-Key: {node_api_key}
Content-Type: application/json

{
    "node_id": 1,
    "timestamp": 1702910400,
    "data": [
        {
            "user_id": 1,
            "upload": 1048576,
            "download": 2097152,
            "ip": "1.2.3.4"
        },
        {
            "user_id": 2,
            "upload": 524288,
            "download": 1048576,
            "ip": "1.2.3.5"
        }
    ]
}
```

#### 获取用户流量统计
```http
GET /api/user/traffic/stats
Authorization: Bearer {token}
Query Parameters:
- period: daily, weekly, monthly
- node_id: (optional) 节点ID

Response:
{
    "status": "success",
    "data": {
        "period": "daily",
        "stats": [
            {
                "date": "2024-12-18",
                "upload": 1073741824,
                "download": 2147483648,
                "total": 3221225472
            }
        ],
        "summary": {
            "total_upload": 10737418240,
            "total_download": 21474836480,
            "total": 32212254720
        }
    }
}
```

### 4. 订阅接口

#### 获取订阅信息
```http
GET /api/subscribe/info
Authorization: Bearer {token}
```

**响应**：
```json
{
    "status": "success",
    "data": {
        "upload": 1073741824,
        "download": 2147483648,
        "total": 3221225472,
        "unused": 104150881280,
        "usage": "3%",
        "expire": "2025-12-18 00:00:00",
        "node_count": 15,
        "class": 2,
        "class_expire": "2025-01-18 00:00:00"
    }
}
```

### 5. 支付回调接口

#### 支付宝回调
```http
POST /api/payment/callback/alipay
Content-Type: application/x-www-form-urlencoded

gmt_create=2024-12-18+22%3A45%3A30&charset=UTF-8&...
```

#### 微信支付回调
```http
POST /api/payment/callback/wechat
Content-Type: application/xml

<xml>
    <return_code><![CDATA[SUCCESS]]></return_code>
    <appid><![CDATA[wx1234567890abcdef]]></appid>
    <mch_id><![CDATA[1234567890]]></mch_id>
    ...
</xml>
```

## 数据结构

### 节点心跳数据
```json
{
    "node_id": "integer - 节点ID",
    "load": "float - 系统负载",
    "uptime": "integer - 运行时间(秒)",
    "online_users": "integer - 在线用户数",
    "memory_usage": "float - 内存使用率(%)",
    "cpu_usage": "float - CPU使用率(%)",
    "disk_usage": "float - 磁盘使用率(%)",
    "network_rx": "integer - 网络接收(bytes)",
    "network_tx": "integer - 网络发送(bytes)",
    "timestamp": "integer - 时间戳"
}
```

### 流量统计数据
```json
{
    "user_id": "integer - 用户ID",
    "node_id": "integer - 节点ID",
    "upload": "integer - 上传流量(bytes)",
    "download": "integer - 下载流量(bytes)",
    "ip": "string - 用户IP",
    "timestamp": "integer - 时间戳"
}
```

## 使用方法

### 1. API认证中间件
```php
// app/Http/Middleware/ApiAuth.php
class ApiAuth
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        try {
            $payload = JWT::decode($token, config('app.jwt_secret'), ['HS256']);
            $user = User::find($payload->sub);

            if (!$user) {
                return response()->json(['error' => 'User not found'], 401);
            }

            $request->setUserResolver(function() use ($user) {
                return $user;
            });

        } catch (JWTException $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        return $next($request);
    }
}
```

### 2. 节点认证
```php
// app/Http/Middleware/NodeAuth.php
class NodeAuth
{
    public function handle($request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json(['error' => 'API key required'], 401);
        }

        $node = SsNode::where('api_key', $apiKey)->first();

        if (!$node) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        if ($node->status != 1) {
            return response()->json(['error' => 'Node is disabled'], 403);
        }

        $request->merge(['node' => $node]);

        return $next($request);
    }
}
```

### 3. 节点心跳处理
```php
public function ping(Request $request)
{
    $node = $request->node;
    $data = $request->all();

    // 更新节点状态
    $nodeInfo = new SsNodeInfo();
    $nodeInfo->node_id = $node->id;
    $nodeInfo->load = $data['load'];
    $nodeInfo->uptime = $data['uptime'];
    $nodeInfo->log_time = time();
    $nodeInfo->save();

    // 记录在线用户数
    $onlineLog = new SsNodeOnlineLog();
    $onlineLog->node_id = $node->id;
    $onlineLog->online_user = $data['online_users'];
    $onlineLog->log_time = time();
    $onlineLog->save();

    // 检查配置是否需要更新
    $configUpdated = $this->checkConfigUpdate($node);

    // 获取用户列表
    $usersUpdated = $this->updateUsersList($node);

    return response()->json([
        'status' => 'success',
        'message' => 'Node status updated',
        'data' => [
            'config_updated' => $configUpdated,
            'users_list_updated' => $usersUpdated,
            'next_ping_interval' => 60
        ]
    ]);
}
```

### 4. 流量数据处理
```php
public function reportTraffic(Request $request)
{
    $node = $request->node;
    $trafficData = $request->input('data', []);

    foreach ($trafficData as $data) {
        // 验证用户是否存在
        $user = User::find($data['user_id']);
        if (!$user) continue;

        // 记录流量日志
        UserTrafficLog::create([
            'user_id' => $data['user_id'],
            'node_id' => $node->id,
            'u' => $data['upload'],
            'd' => $data['download'],
            'ip' => $data['ip'],
            'log_time' => $request->input('timestamp', time()),
        ]);

        // 更新用户总流量
        $user->increment('u', $data['upload']);
        $user->increment('d', $data['download']);

        // 检查流量是否超限
        $totalUsed = $user->u + $user->d;
        if ($totalUsed >= $user->transfer_enable) {
            $user->update(['enable' => 0]);
            // 发送流量耗尽通知
            $this->sendTrafficExhaustedNotification($user);
        }
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Traffic data processed',
        'processed_count' => count($trafficData)
    ]);
}
```

## 限流和安全

### 1. API限流
```php
// 使用Redis实现API限流
class ApiRateLimit
{
    public function handle($request, Closure $next, $limit = 60, $window = 60)
    {
        $key = 'api_limit:' . $request->ip();
        $current = Redis::get($key);

        if ($current >= $limit) {
            return response()->json(['error' => 'Too many requests'], 429);
        }

        Redis::incr($key);
        Redis::expire($key, $window);

        return $next($request);
    }
}
```

### 2. 请求验证
```php
// 验证请求参数
$request->validate([
    'node_id' => 'required|integer|exists:ss_node,id',
    'load' => 'required|numeric|min:0|max:100',
    'online_users' => 'required|integer|min:0',
    'uptime' => 'required|integer|min:0'
]);
```

### 3. 签名验证
```php
// 支付回调签名验证
private function verifySignature($data, $signature)
{
    $expectedSignature = hash_hmac('sha256', $data, config('payment.secret'));
    return hash_equals($expectedSignature, $signature);
}
```

## 约束条件

1. **访问控制**
   - API访问需要认证
   - 请求频率限制
   - IP白名单（可选）

2. **数据验证**
   - 输入数据必须验证
   - 敏感操作需要二次验证
   - 数据格式标准化

3. **性能要求**
   - 响应时间<500ms
   - 支持高并发
   - 使用缓存优化

## 最佳实践

### 1. 统一响应格式
```php
// 标准API响应格式
return response()->json([
    'status' => 'success', // success, error
    'message' => '操作成功',
    'data' => $data,
    'timestamp' => time()
]);
```

### 2. 异常处理
```php
// 全局异常处理
class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'timestamp' => time()
            ], 500);
        }

        return parent::render($request, $exception);
    }
}
```

### 3. API文档生成
```php
// 使用注释生成API文档
/**
 * @OA\Post(
 *     path="/api/ping",
 *     summary="Node heartbeat",
 *     tags={"Node"},
 *     security={{"bearerAuth": {}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"node_id","load","uptime","online_users"},
 *             @OA\Property(property="node_id", type="integer"),
 *             @OA\Property(property="load", type="number"),
 *             @OA\Property(property="uptime", type="integer"),
 *             @OA\Property(property="online_users", type="integer")
 *         )
 *     )
 * )
 */
```

## 测试要点

1. **接口功能**
   - 正常请求响应
   - 认证授权
   - 参数验证

2. **异常处理**
   - 错误响应格式
   - 异常码准确性
   - 日志记录

3. **性能测试**
   - 响应时间
   - 并发能力
   - 资源消耗

4. **安全测试**
   - 认证绕过
   - 注入攻击
   - 限流测试

## 注意事项

1. API需要完善的错误处理机制
2. 敏感数据不能在响应中返回
3. 所有API调用需要记录日志
4. 定期清理过期的API日志
5. 监控API性能和错误率
6. 保持API的向后兼容性
7. 使用HTTPS保护数据传输
8. 实施合理的访问频率限制