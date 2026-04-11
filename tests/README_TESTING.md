# NPanel API 测试说明

## 🎯 测试概述

本目录包含完整的API测试套件，用于验证PingController的各个API端点功能是否正常。

## 📁 测试文件说明

### 1. 主要测试脚本
- **`remote_test.sh`** - 远程服务器测试脚本（推荐使用）
- **`test_ping_api.sh`** - 通用API测试脚本
- **`deploy_and_test.sh`** - 一键部署并测试脚本

### 2. PHPUnit测试
- **`tests/Feature/PingControllerApiTest.php`** - Laravel框架集成测试

### 3. 分析和修复工具
- **`simple_check.php`** - 静态代码分析
- **`analyze_api.php`** - 详细代码分析
- **`PingController_Fixed.php`** - 修复后的控制器示例

## 🚀 快速使用方法

### 方法1：一键部署并测试（推荐）

```bash
# 本地运行：部署测试脚本到远程服务器并执行
./tests/deploy_and_test.sh
```

### 方法2：手动远程测试

```bash
# 1. 同步测试脚本
scp tests/remote_test.sh root@test.srp:/www/wwwroot/Npanel/tests/
scp tests/test_ping_api.sh root@test.srp:/www/wwwroot/Npanel/tests/

# 2. SSH登录并运行测试
ssh root@test.srp
cd /www/wwwroot/Npanel
./tests/remote_test.sh
```

### 方法3：PHPUnit测试

```bash
# 在远程服务器运行
ssh root@test.srp
cd /www/wwwroot/Npanel
./vendor/bin/phpunit tests/Feature/PingControllerApiTest.php
```

## 🔧 环境配置要求

在远程服务器的 `/www/wwwroot/Npanel/.env` 文件中必须包含：

```env
# API配置（必须）
API_TOKEN=your_actual_api_token_here
APP_URL=http://your-domain.com

# 可选：测试服务器配置
TEST_API_URL=http://your-domain.com/api
```

## 📋 测试覆盖的API端点

### 1. Ping API (`/api/ping`)
- ✅ 缺少host参数测试
- ✅ 无效token测试  
- ✅ 有效域名测试
- ✅ IPv4地址测试

### 2. SimpleApiTools API (`/api/simpleApiTools`)
- ✅ 缺少token测试
- ✅ 无效token测试
- ✅ 获取IP和时间测试

### 3. getNodeConfig API (`/api/node_config`)
- ✅ 缺少token测试
- ✅ 缺少node_id测试
- ✅ 不存在的节点测试

### 4. SSN相关API
- ✅ ssn_sub API token验证
- ✅ ssn_v2 API token验证

## 🐛 已知问题和修复

### 修复内容：
1. **路由命名不一致**：`simple_api_tools` → `simpleApiTools`
2. **ping方法**：移除直接echo和exit()，改为JSON响应
3. **ssn_v2方法**：移除exit()，添加适当响应
4. **clonepay方法**：添加token验证和安全检查

### 应用修复：
```bash
# 如需应用修复，请执行：
cp tests/PingController_Fixed.php app/Http/Controllers/Api/PingController.php
```

## 📊 测试结果解读

### 成功指标：
- ✅ **绿色** - 测试通过
- HTTP状态码正确（200）
- 响应内容包含预期数据
- API返回标准JSON格式

### 失败指标：
- ❌ **红色** - 测试失败
- HTTP状态码错误
- 响应内容不符合预期
- 连接错误

### 警告指标：
- ⚠️ **黄色** - 需要检查
- 响应格式可能不标准
- 需要进一步验证

## 🔄 持续测试

### 定期测试建议：
```bash
# 设置定时任务，每天运行测试
echo "0 2 * * * cd /www/wwwroot/Npanel && ./tests/remote_test.sh >> /var/log/api_test.log" | crontab -
```

### 测试报告保存：
```bash
# 保存测试结果
./tests/remote_test.sh > test_results_$(date +%Y%m%d_%H%M%S).log
```

## 🚨 故障排除

### 常见问题：

1. **SSH连接失败**
   ```bash
   # 检查网络连接
   ping test.srp
   
   # 检查SSH密钥
   ssh-add -l
   ```

2. **.env文件权限**
   ```bash
   # 检查文件权限
   ls -la /www/wwwroot/Npanel/.env
   
   # 修复权限
   chmod 640 /www/wwwroot/Npanel/.env
   ```

3. **PHP依赖问题**
   ```bash
   # 检查composer
   cd /www/wwwroot/Npanel && composer install
   
   # 检查权限
   chown -R www-data:www-data storage bootstrap/cache
   ```

4. **Web服务器状态**
   ```bash
   # 检查Nginx/Apache
   systemctl status nginx
   systemctl status apache2
   
   # 检查错误日志
   tail -f /var/log/nginx/error.log
   ```

## 📞 技术支持

如果测试失败，请提供：
1. 错误截图或日志
2. .env文件内容（隐藏敏感信息）
3. Web服务器配置
4. PHP版本和Laravel版本

---

**注意**：所有测试脚本已配置为从根目录读取.env文件，确保在远程服务器 `ssh root@test.srp` 环境下正常运行。