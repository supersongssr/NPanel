# Nginx 配置下发方案

> 日期: 2026-05-18
> 分支: new/api-v2
> 状态: 待实施

## 背景

当前 Node API v2 生命周期为 `apply_id → register → resolve_dns → config → status(循环)`。

- `config` 端点下发 Xray JSON 配置
- Nginx 配置模板已就绪（`resources/templates/nginx/`），但尚未有下发通道
- `register` 响应中包含端口/路径字段（`ws_path`、`ws_port` 等），与 Xray/Nginx 模板中的变量重复

## 模板现状

```
resources/templates/nginx/
├── vision-hy2-ws-grpc.conf    # 仅 80→443 重定向，无 HTTPS server block
└── xhttp-hy2-ws-grpc.conf     # 完整 HTTPS 配置（ws/grpc/xhttp 分流 + 伪装站回落）
```

`vision-hy2-ws-grpc.conf` 只做 HTTP→HTTPS 重定向，因为 Vision 和 HY2 协议由 Xray/Hysteria2 自行处理 TLS，不经过 Nginx。

## 方案：直接返回 conf 文件

### 新增端点

**路由:** `POST /api/node/nginx_config`

**请求参数:**

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| token | string | 是 | API Token（支持 body 或 query string） |
| node_id | integer | 是 | 节点 ID |

**成功响应 (200):** `Content-Type: text/plain`

直接返回渲染后的 nginx 配置文本，无 JSON 包裹。

**节点侧用法：**

```bash
# 和下载 SSL 证书一模一样的方式
curl -sS -o /etc/nginx/conf.d/proxy.conf \
    -d "token=${API_TOKEN}&node_id=${NODE_ID}" \
    "${API_URL}/api/node/nginx_config"
nginx -t && systemctl restart nginx
```

**错误响应（HTTP 状态码即可，不需要 JSON）：**

| 状态码 | 含义 | 说明 |
|---|---|---|
| 200 | 成功 | 直接返回 conf 文本 |
| 401 | 认证失败 | token 无效或缺失 |
| 404 | 无配置 | 该节点没有 nginx 配置（如 vision 模式，Xray 独占 443，不需要 nginx HTTPS） |
| 500 | 服务端错误 | 模板缺失等 |

### 设计决策：为什么直接返回文本而非 JSON 包裹

1. **与已有下载模式一致** — 节点脚本已经在用 `wget -O` / `curl -o` 下载文件（SSL 证书、xray 二进制、nodeStatus.sh），nginx config 没有理由不同
2. **不需要 jq** — 返回 JSON 的话，节点还要 `jq -r '.nginx_config'` 提取字符串再处理 `\n` 转义。直接返回文本，`curl -o` 写盘，零处理
3. **没有 JSON 转义问题** — nginx 配置里有引号、正则、特殊字符（`~^`、`$host`、`\\`），嵌在 JSON 字符串里要双层转义，容易出问题
4. **瘦节点不判断** — 不需要 `has_https_block` 这类元数据让节点做分支。面板知道节点是什么模式，vision 模式直接返回 404，节点脚本无需理解协议
5. **错误处理用 HTTP 状态码足够** — 200 写盘，404 跳过，401 报错，5xx 重试。不需要 JSON 包 `{"status":"error"}`

### 调用时机

与 `config`（Step 3）并行拉取。更新后的生命周期：

```
apply_id → register → resolve_dns → config + nginx_config → status(循环)
```

### 模板选择逻辑

与 `config` 端点一致：通过节点的 `v2_name` 字段定位模板文件。

```
v2_name = "xhttp-hy2-ws-grpc"  →  resources/templates/nginx/xhttp-hy2-ws-grpc.conf
v2_name = "vision-hy2-ws-grpc" →  返回 404（vision 模式不需要 nginx HTTPS 配置）
```

注意：每个节点（包括克隆节点）的 `v2_name` 是单个协议（如 `ws`、`hy2`），但模板选择应使用**协议组名称**（即主节点的 `v2_name` 或 `register` 返回的 `v2_name`），因为 nginx 配置是按协议组分组的，一个 nginx 实例服务该节点的所有协议。

### vision 模式处理

vision-hy2-ws-grpc 协议组的 nginx 模板仅包含 HTTP→HTTPS 重定向（11 行），节点不需要这个配置（Xray 和 HY2 各自监听 443）。面板应直接返回 **404**，节点脚本跳过 nginx 配置步骤即可。

判断逻辑：渲染模板后检测是否包含 `listen 443`，若不包含则返回 404。

### 变量注入映射

Nginx 模板占位符 → 实际值：

| 占位符 | 数据来源 | 示例值 |
|---|---|---|
| `__nodeDomainRegex__` | `parseServerField($node->server)['root_domain']` | `ssmail.win` |
| `__nodeDomain__` | `parseServerField($node->server)['root_domain']` | `ssmail.win` |
| `__xhttpPath__` | 硬编码 | `srp-xhttp` |
| `__xhttpPort__` | 硬编码 | `10013` |
| `__wsPath__` | 硬编码 | `srp-ws` |
| `__wsPort__` | 硬编码 | `10011` |
| `__v2ServiceName__` | 硬编码 | `srp-grpc` |
| `__grpcPort__` | 硬编码 | `10012` |
| `__httpProxyHost__` | 固定值 | `npanel-nav.freessr.bid` |

### PHP 实现要点

```php
public function nginxConfig(Request $request)
{
    if ($err = $this->validateToken($request)) return $err;

    $nodeId = $request->input('node_id');
    $node = SsNode::find($nodeId);
    if (!$node) return response('Node not found', 404);
    if ($node->status == 0) return response('Node is offline', 403);

    // 获取协议组名称（从主节点取，而非单个协议）
    $mainNode = ($node->is_clone > 0) ? SsNode::find($node->is_clone) : $node;
    $v2Name = $mainNode->v2_name ?: 'vision-hy2-ws-grpc';

    $templatePath = resource_path("templates/nginx/{$v2Name}.conf");
    if (!file_exists($templatePath)) return response('Template not found', 500);

    $conf = file_get_contents($templatePath);

    $serverParts = $this->parseServerField($node->server);
    $rootDomain = $serverParts ? $serverParts['root_domain'] : '';

    $conf = str_replace(
        ['__nodeDomainRegex__', '__nodeDomain__', '__xhttpPath__', '__xhttpPort__',
         '__wsPath__', '__wsPort__', '__v2ServiceName__', '__grpcPort__',
         '__httpProxyHost__'],
        [$rootDomain, $rootDomain, 'srp-xhttp', '10013',
         'srp-ws', '10011', 'srp-grpc', '10012',
         'npanel-nav.freessr.bid'],
        $conf
    );

    // vision 模式：模板只有重定向，不含 HTTPS block，返回 404 让节点跳过
    if (strpos($conf, 'listen 443') === false) {
        return response('No nginx HTTPS config for this protocol group', 404);
    }

    return response($conf, 200)->header('Content-Type', 'text/plain');
}
```

**注意事项：**
- 使用 Laravel `response($content, 200)->header('Content-Type', 'text/plain')` 直接返回纯文本
- 变量注入使用 `str_replace` 一次性替换所有占位符
- 克隆节点应返回主节点协议组对应的 nginx 配置（同一台机器共享一个 nginx）
- vision 模式返回 404，节点脚本 `curl -f` 会自动跳过

## register 响应精简

### 移除的字段

以下字段将从 `register` 响应中删除，因为已嵌入 Xray config 和 Nginx config 模板：

| 移除字段 | 原返回值 | 移入位置 |
|---|---|---|
| `ws_path` | `"srp-ws"` | Xray config + Nginx config 模板 |
| `ws_port` | `10011` | Xray config + Nginx config 模板 |
| `grpc_service_name` | `"srp-grpc"` | Xray config + Nginx config 模板 |
| `grpc_port` | `10012` | Xray config + Nginx config 模板 |
| `xhttp_path` | `"srp-xhttp"` | Xray config + Nginx config 模板 |
| `xhttp_port` | `10013` | Xray config + Nginx config 模板 |
| `hy2_port` | `443` | Xray config 模板 |
| `vision_port` | `443` | Xray config 模板 |
| `proxy_host` | `"npanel-nav.freessr.bid"` | Nginx config 模板 |

### 精简后的 register 响应

```json
{
    "status": "success",
    "node_id": 42,
    "clone_node_ids": [43, 44, 45],
    "node_ids": "42,43,44,45",
    "root_domain": "example.com",
    "v2_name": "vision-hy2-ws-grpc"
}
```

仅保留注册流程本身的元数据（ID、域名、协议组），不再下发运行参数。

## 路由注册

`routes/api.php` 新增：

```php
Route::post('nginx_config', 'NodeApiController@nginxConfig');
```

## 实施步骤

1. `NodeApiController` 新增 `nginxConfig()` 方法
2. `routes/api.php` 注册路由
3. 从 `register()` 方法中移除端口/路径字段返回逻辑
4. 更新 `docs/api/node-api-v2.md` 文档
5. 编写测试用例

## 依据

- 与节点已有文件下载模式一致（`curl -o`），无需引入 jq
- 瘦节点原则：节点不做判断，面板决定是否需要 nginx 配置（200 vs 404）
- register 职责回归"告知节点你是谁"，config/nginx_config 负责"告知节点怎么运行"
- 消除 register 与 config 之间的端口/路径数据重复
