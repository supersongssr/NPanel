# Node API 媒体解锁功能质量测试报告 (QA) - 增强版

## 1. 测试目标
深度验证 `NodeApiController` 对 13 个独立 `unlock_xxx` 参数的全链路处理能力。重点评估：
- 全 13 个字段全量上报时的存储边界与数据完整性。
- 对包含特殊字符（如 `&`）和复杂描述（如 `Yes(Region=HK & MO)`）的容错处理。
- 模糊匹配逻辑（Fuzzy Matching）对大小写、空格及非标准输入的鲁棒性。
- 确保向下兼容性通过 API 全链路验证而非手动篡改数据库。

## 2. 测试环境
- **面板环境**：Podman 容器 `php7-npanel`
- **代码路径**：`/var/www/test-npanel.freessr.bid/app/Http/Controllers/Api/NodeApiController.php`
- **数据库限制**：`ss_node.node_unlock` 字段类型为 `VARCHAR(500)`。

## 3. 增强测试用例

### TC-01: 13 字段全量高负载注册测试 (Full Matrix Stress Test)
- **描述**：模拟节点同时上报全部 13 个解锁字段，且每个字段附带冗长描述，验证是否触及 `VARCHAR(500)` 的截断风险。
- **动作**：
  ```bash
  # 构造 13 个带有详细信息的参数
  curl -X POST "http://localhost/api/node/register" \
    --data-urlencode "token=${API_TOKEN}" \
    --data-urlencode "node_id=1" \
    --data-urlencode "unlock_netflix=Yes(Region=TW,CDN=Akamai)" \
    --data-urlencode "unlock_disney=Yes(Region=US,4K=Supported)" \
    --data-urlencode "unlock_chatgpt=Yes(Plus=Enabled)" \
    --data-urlencode "unlock_claude=Yes(Accessible)" \
    --data-urlencode "unlock_gemini=Yes" \
    --data-urlencode "unlock_tiktok=Yes(Live=OK)" \
    --data-urlencode "unlock_bilibili=Yes(Mainland)" \
    --data-urlencode "unlock_iqiyi=Yes(Global)" \
    --data-urlencode "unlock_bahamut=Yes" \
    --data-urlencode "unlock_mewatch=No" \
    --data-urlencode "unlock_bing=US" \
    --data-urlencode "unlock_google_scholar=Yes(accessible)" \
    --data-urlencode "unlock_notebooklm=Yes"
  ```
- **判定标准**：
  1. 数据库存储成功且无报错。
  2. 使用 `LENGTH(node_unlock)` 检查是否发生静默截断（若接近 500 则需警惕）。
  3. `node_unlock` 中的内容应能正确表达 13 个字段的聚合。

### TC-02: 特殊字符与分隔符容错测试 (Special Characters & Delimiters)
- **描述**：验证上报值中包含 `&` 或 `=` 等特殊字符时，聚合逻辑是否会发生“格式崩塌”。
- **动作**：上报 `unlock_netflix=Yes(HK & MO & TW)`。
- **预期结果**：数据库中存储的值应为 `...netflix=Yes(HK & MO & TW)...`。
- **判定标准**：后续调用 `config` 接口时，`netflix` 仍能正常触发（验证正则匹配是否受干扰）。

### TC-03: 模糊匹配鲁棒性测试 (Robustness & Case Sensitivity)
- **描述**：验证对非规范输入（大小写混用、两端空格）的识别能力。
- **动作**：通过 API 注册 `unlock_netflix=  YES(Tw)  &unlock_disney=true&unlock_tiktok=On`。
- **验证**：调用 `GET /api/node/config`。
- **预期结果**：Netflix、Disney、TikTok 的路由规则均应下发。

### TC-04: 全链路向下兼容性测试 (E2E Backward Compatibility)
- **描述**：不手动修改 DB，通过 API 模拟旧版客户端上报极简状态值。
- **动作**：
  1. 调用 `register`：`token=${API_TOKEN}&node_id=1&unlock_netflix=1&unlock_chatgpt=yes`。
  2. 调用 `config`：检查配置。
- **预期结果**：配置下发正确，证明 `1` 和 `yes` 等旧版标识在全链路上依然有效。

### TC-05: 空值与字段缺失防守测试 (Null & Missing Defense)
- **描述**：验证参数部分缺失或为空字符串时的系统稳定性。
- **动作**：仅上报 `unlock_netflix=&unlock_disney=Yes` (netflix 为空)。
- **预期结果**：
  1. 数据库存储正常，不应出现类似 `&&` 的格式错误。
  2. 聚合后的字符串不应包含无意义的空 key。

### TC-06: 存储顺序无关性校验 (Order Independence)
- **描述**：由于聚合使用了关联数组，验证不同顺序的输入在解析时是否等效。
- **验证方法**：无论 `node_unlock` 字段中 `netflix` 是在开头还是结尾，`config` 接口的输出必须一致。

## 4. 自动化语法回归
```bash
podman exec -it php7-npanel php -l /var/www/test-npanel.freessr.bid/app/Http/Controllers/Api/NodeApiController.php
```

## 5. 结论矩阵
| 测试维度 | 风险点 | 状态 |
|---|---|---|
| **全量负载** | VARCHAR(500) 溢出 | [待执行] |
| **特殊字符** | `&` 破坏 QueryString 格式 | [待执行] |
| **全链路兼容** | 旧版 `1/yes` 识别失效 | [待执行] |
| **输入鲁棒性** | 大小写/空格导致匹配失败 | [待执行] |
| **语法一致性** | 容器环境兼容性 | ✅ 已通过 |
