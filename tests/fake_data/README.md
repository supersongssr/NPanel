# Fake Data Generator

## where
`tests/fake_data/` 目录

## why
测试站点需要假用户和假节点数据进行功能测试

## how
使用 PHP 脚本批量生成测试数据

## 文件说明

### generate_fake_users.php
- **功能**: 生成100个假用户数据
- **用户名格式**: `test_user_001` ~ `test_user_100`
- **默认密码**: `password123`
- **包含数据**:
  - 用户基本信息
  - 订阅记录
  - 随机的等级、流量、余额等

### generate_fake_nodes.php
- **功能**: 生成10个假节点数据
- **节点名称**: `Test Node 1` ~ `Test Node 10`
- **节点类型**: SS, V2Ray, VLESS, Trojan
- **包含数据**:
  - 节点基本信息
  - V2Ray 配置
  - 随机的国家、流量等

### generate_all_fake_data.php
- **功能**: 一次性生成所有测试数据 (用户 + 节点)
- **推荐使用**: 便捷脚本

## must
- ⚠️ **必须在 `.env APP_ENV=test` 时才能使用**
- 仅用于测试环境
- 请勿在生产环境运行

## 使用方法

### 1. 检查环境配置

确保 `.env` 文件中设置了:
```env
APP_ENV=test
```

### 2. 运行脚本

**生成所有数据 (推荐)**:
```bash
php tests/fake_data/generate_all_fake_data.php
```

**单独生成用户**:
```bash
php tests/fake_data/generate_fake_users.php
```

**单独生成节点**:
```bash
php tests/fake_data/generate_fake_nodes.php
```

### 3. 交互确认

如果已存在测试数据,脚本会询问是否删除:
```
Warning: Found 50 existing test users.
Do you want to delete them first? (yes/no):
```

## 测试账号信息

生成后可以使用以下账号登录测试:
- 用户名: `test_user_001` ~ `test_user_100`
- 密码: `password123`

## 清理测试数据

如需清理测试数据,可以运行:
```sql
-- 删除测试用户
DELETE FROM user WHERE username LIKE 'test_user_%';

-- 删除测试节点
DELETE FROM ss_node WHERE name LIKE 'Test Node %';
```

## 注意事项

1. 数据仅在 `APP_ENV=test` 时才能生成
2. 默认密码仅用于测试,请勿在生产环境使用
3. 测试数据使用随机值生成,每次生成的数据不同
4. 建议在测试数据库中运行,避免污染生产数据
