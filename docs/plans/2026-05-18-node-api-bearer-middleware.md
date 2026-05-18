# Node API Bearer Token Middleware 方案

> 日期: 2026-05-18
> 分支: new/api-v2
> 状态: 待审核

## 背景

当前 `NodeApiController` 的 6 个端点各自内联 token 验证逻辑，存在三种不同实现：
- `applyId` / `register`：只读 body 参数 `token`
- `resolveDns` / `config` / `status`：读 body 参数 `token` 或 `X-API-Token` 头
- `nginxConfig`：同上

不支持 Bearer token。且新增端点时可能遗漏验证。

## 方案：自定义 Middleware

### 新建文件

`app/Http/Middleware/NodeApiToken.php`

**Token 提取优先级：**

1. `Authorization: Bearer <token>` — 标准认证头
2. `X-API-Token: <token>` — 自定义头，向后兼容
3. body/query 参数 `token` — POST body 或 query string，向后兼容

**响应格式区分：**
- `nginx_config` 端点返回纯文本 `response('Unauthorized', 401)`
- 其余端点返回 JSON `response()->json(['status'=>'error','message'=>'Unauthorized'], 401)`
- 判断依据：`$request->is('*/node/nginx_config')`

### 注册 Middleware

`app/Http/Kernel.php` — `$routeMiddleware` 数组新增：

```php
'node.api.token' => \App\Http\Middleware\NodeApiToken::class,
```

### 路由组挂载

`routes/api.php` — node 路由组增加 `middleware` 属性：

```php
Route::group(['prefix' => 'node', 'middleware' => 'node.api.token'], function () {
    Route::post('apply_id', 'NodeApiController@applyId');
    Route::post('register', 'NodeApiController@register');
    Route::post('resolve_dns', 'NodeApiController@resolveDns');
    Route::post('config', 'NodeApiController@config');
    Route::post('nginx_config', 'NodeApiController@nginxConfig');
    Route::post('status', 'NodeApiController@status');
});
```

### Controller 清理

从 `NodeApiController` 中移除以下验证代码：

| 方法 | 移除内容 | 行号(当前) |
|---|---|---|
| `validateToken()` | 整个方法删除 | 第 19-26 行 |
| `applyId()` | 删除 token 参数获取和比较（3 行） | 第 87-89 行 |
| `register()` | 删除 token 参数获取和比较（5 行） | 第 132-137 行 |
| `nginxConfig()` | 删除 token 提取和比较（4 行） | 第 1035-1038 行 |
| `resolveDns()` | 删除 `if ($err = $this->validateToken($request)) return $err;` | 第 493 行 |
| `config()` | 同上 | 第 940 行 |
| `status()` | 同上 | 第 1220 行 |

## 实施步骤

1. 新建 `app/Http/Middleware/NodeApiToken.php`
2. `app/Http/Kernel.php` 注册 `node.api.token` 中间件
3. `routes/api.php` node 路由组添加 `middleware` 属性
4. `NodeApiController.php` 删除 `validateToken()` 方法和所有内联验证代码
5. 运行现有测试确认无回归

## 向后兼容性

三种传 token 方式均保留，现有节点脚本无需修改：

```bash
# 方式 1: body 参数（现有）
curl -d "token=xxx&node_id=1" https://panel/api/node/status

# 方式 2: X-API-Token 头（现有）
curl -H "X-API-Token: xxx" https://panel/api/node/status

# 方式 3: Bearer 头（新增）
curl -H "Authorization: Bearer xxx" https://panel/api/node/status
```
