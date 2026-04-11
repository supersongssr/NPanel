# API自动化测试说明

本目录包含NPanel API的自动化测试脚本，用于验证PingController的各个API端点是否正常工作。

## 测试内容

### 1. PHPUnit测试 (Feature测试)
- **文件**: `tests/Feature/PingControllerApiTest.php`
- **功能**: 使用Laravel内置测试框架测试API逻辑
- **测试覆盖**:
  - ping API的token验证和参数处理
  - simpleApiTools API的token验证和数据返回
  - getNodeConfig API的权限和参数验证
  - ssn_sub和ssn_v2 API的token验证

### 2. Shell脚本测试
- **文件**: `.tests/test_ping_api.sh`
- **功能**: 通过HTTP请求测试实际API端点
- **测试覆盖**: 
  - 各种API的HTTP状态码和响应内容验证
  - 错误处理测试（无效token、缺少参数等）
  - 正常功能测试（有效请求）

## 使用方法

### 环境准备

1. **配置环境变量**:
   ```bash
   # 在.env文件中添加或修改：
   API_TOKEN=your_actual_api_token
   TEST_API_URL=http://your-domain.com/api
   ```

2. **创建测试环境配置**:
   ```bash
   cp .env.testing.example .env.testing
   # 编辑.env.testing填入实际配置
   ```

### 运行测试

#### PHPUnit测试
```bash
# 运行所有Feature测试
./vendor/bin/phpunit --testsuite=Feature

# 只运行PingController测试
./vendor/bin/phpunit tests/Feature/PingControllerApiTest.php

# 运行测试并生成覆盖率报告
./vendor/bin/phpunit --coverage-html coverage tests/Feature/PingControllerApiTest.php
```

#### Shell脚本测试
```bash
# 直接执行测试脚本
cd .tests
./test_ping_api.sh

# 或者从项目根目录执行
./.tests/test_ping_api.sh
```

## 测试API端点

### 1. ping API (`/api/ping`)
- **功能**: 测试指定主机的端口连通性
- **参数**:
  - `token`: API令牌（必须）
  - `host`: 目标主机（必须）
  - `port`: 端口号（可选，默认22）
  - `transport`: 协议类型（可选，默认tcp）
  - `timeout`: 超时时间（可选，默认0.5秒）

### 2. simpleApiTools API (`/api/simpleApiTools`)
- **功能**: 提供各种工具功能（获取IP、时间、节点ID等）
- **参数**:
  - `token`: MD5(token + salt)格式的令牌（必须）
  - `salt`: 随机字符串（必须）
  - 其他参数根据需要添加

### 3. getNodeConfig API (`/api/node_config`)
- **功能**: 获取节点配置信息
- **参数**:
  - `token`: API令牌（必须）
  - `node_id`: 节点ID（必须）

### 4. ssn_sub API (`/api/ssn_sub/{id}`)
- **功能**: 更新节点订阅信息
- **参数**: `token`（必须）

### 5. ssn_v2 API (`/api/ssn_v2/{id}`)
- **功能**: 更新节点V2配置信息
- **参数**: `token`（必须）

## 注意事项

1. **Token配置**: 确保`.env`文件中的`API_TOKEN`与系统中配置的一致
2. **测试环境**: Shell脚本测试使用真实HTTP请求，需要确保目标服务可访问
3. **数据库**: 某些测试可能需要数据库中有测试数据（如节点信息）
4. **权限**: 确保测试脚本有执行权限：`chmod +x .tests/test_ping_api.sh`
5. **日志**: 测试过程中产生的日志会被记录到Laravel的日志系统中

## 测试结果解读

- **绿色✓**: 测试通过
- **红色✗**: 测试失败
- **黄色**: 测试描述

测试失败时，请检查：
1. API_TOKEN是否正确配置
2. 目标服务是否正常运行
3. 网络连接是否正常
4. 相关数据是否存在（如测试用的节点ID）