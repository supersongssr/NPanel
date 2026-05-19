# Node API 媒体解锁功能适配方案 (WHAT-WHY-WHERE-HOW-MUST)

## WHAT
支持节点通过 13 个独立参数（`unlock_xxx`）上报流媒体解锁状态，并实现模糊状态匹配（例如支持 `Yes(Region=TW)`），确保面板能正确下发 Xray 解锁路由配置。

## WHY
1. **上报逻辑变更**：节点端脚本已不再发送聚合的 `node_unlock` 字符串，而是拆分为 13 个独立字段上报。
2. **状态判定失效**：原有的 `in_array` 匹配逻辑过于严格，无法识别带括号的区域信息（如 `Yes(Region=TW)`），导致虽然节点有解锁但面板不发配置。
3. **架构兼容性**：需要将独立参数重新聚合存储到数据库的 `node_unlock` 字段中，以维持与现有数据库 Schema 和前端展示的兼容。

## WHERE
- **文件**：`app/Http/Controllers/Api/NodeApiController.php`
- **方法**：
    - `register()`: 负责接收并聚合上报参数。
    - `applyUnlocks()`: 负责解析并判定解锁状态是否激活。

## HOW

### 1. 聚合独立上报参数 (register)
在 `register` 方法中，遍历 `Request` 对象的所有参数，提取 `unlock_` 前缀的内容并聚合为标准 Query String。

```php
// 修改点: 约 189 行
$unlockData = [];
foreach ($request->all() as $k => $v) {
    if (strpos($k, 'unlock_') === 0 && $v !== null && $v !== '') {
        $serviceName = substr($k, 7); // 提取如 netflix
        $unlockData[$serviceName] = $v;
    }
}
$node->node_unlock = urldecode(http_build_query($unlockData));
```

### 2. 实现模糊状态匹配 (applyUnlocks)
在 `applyUnlocks` 内部循环中，使用正则匹配状态字符串。

```php
// 修改点: 约 1146 行
foreach ($unlocks as $key => $value) {
    $valLower = strtolower(trim((string)$value));
    // 匹配以 1, yes, true, on 开头的字符串，允许后面有括号说明
    $isEnabled = preg_match('/^(1|yes|true|on)/', $valLower) === 1;

    if ($isEnabled) {
        // ... 原有逻辑 (加载模板并应用)
    }
}
```

## MUST

### 1. 语法校验
由于 PHP 环境运行在 Podman 容器中，必须通过以下命令进行语法检查：
```bash
podman exec -it php7-npanel php -l /var/www/test-npanel.freessr.bid/app/Http/Controllers/Api/NodeApiController.php
```

### 2. 功能验证
- **上报测试**：使用 Postman 或 curl 模拟节点注册，发送 `unlock_netflix=Yes(Region=HK)`，检查数据库 `ss_node` 表的 `node_unlock` 字段是否包含 `netflix=Yes(Region=HK)`。
- **配置测试**：调用 `GET /api/node/config?node_id={id}` 接口，确认返回的 Xray JSON 中 `outbounds` 包含 `netflix` 相关的解锁配置（如 Shadowsocks 出站）。

### 3. 注意事项
- 存储时务必使用 `urldecode` 处理 `http_build_query` 的结果，确保 `()` 等字符在数据库中以原样存储，便于前端解析。
