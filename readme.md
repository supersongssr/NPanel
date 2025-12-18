# NPanel SSR 代理管理面板

一个基于 Laravel 框架开发的 SSR/V2Ray/Trojan 代理服务管理面板，提供用户管理、节点管理、订阅服务、支付系统等完整功能。

## 🛠 技术栈

### 后端技术
- **PHP 7.1+** - 核心开发语言
- **Laravel 5.6** - Web 框架
- **MySQL** - 数据库
- **Redis** - 缓存和会话存储
- **Composer** - 依赖管理

### 前端技术
- **Vue.js 2.5** - 前端框架
- **Bootstrap 4** - UI 组件库
- **jQuery** - DOM 操作库
- **Laravel Mix** - 前端构建工具

### 主要扩展包
- `spatie/laravel-permission` - 权限管理
- `mews/captcha` - 验证码
- `guzzlehttp/guzzle` - HTTP 客户端
- `predis/predis` - Redis 客户端
- `phpoffice/phpspreadsheet` - Excel 处理
- `overtrue/laravel-lang` - 中文语言包

## 📁 项目结构

```
NPanel/
├── app/                     # 应用核心代码
│   ├── Http/
│   │   ├── Controllers/     # 控制器
│   │   │   ├── AdminController.php      # 后台管理
│   │   │   ├── AuthController.php       # 用户认证
│   │   │   ├── UserController.php       # 用户管理
│   │   │   ├── SubscribeController.php   # 订阅服务
│   │   │   ├── PaymentController.php    # 支付管理
│   │   │   ├── ShopController.php       # 商店系统
│   │   │   └── Api/                     # API 接口
│   │   │       ├── PingController.php   # 节点检测
│   │   │       ├── LoginController.php  # 登录API
│   │   │       └── *PayController.php   # 支付回调
│   │   ├── Middleware/       # 中间件
│   │   └── Models/          # 数据模型 (48个模型)
│   ├── Components/          # 组件库
│   ├── Providers/           # 服务提供者
│   └── helpers.php         # 辅助函数
├── config/                  # 配置文件
├── database/               # 数据库相关
├── public/                 # Web 根目录
│   ├── assets/            # 静态资源
│   ├── js/                # JavaScript 文件
│   └── index.php          # 入口文件
├── resources/              # 视图和前端资源
│   ├── views/             # Blade 模板
│   └── js/                # Vue 组件
├── routes/                 # 路由定义
│   ├── web.php            # Web 路由
│   └── api.php            # API 路由
├── storage/                # 存储目录
├── tests/                  # 测试文件
├── sql/                    # 数据库脚本
│   ├── db.sql            # 完整数据库结构
│   └── update/           # 更新脚本
└── vendor/                 # Composer 依赖
```

## 📊 数据结构

### 核心数据表

#### 节点管理
```yaml
ss_node:  # 节点信息表
  - id: 节点ID
  - type: 服务类型 (1-SS, 2-V2, 3-VLESS, 4-Trojan)
  - name: 节点名称
  - group_id: 所属分组
  - server: 服务器域名
  - ip: IPv4地址
  - ipv6: IPv6地址
  - method: 加密方式
  - traffic_rate: 流量比率
  - bandwidth: 出口带宽(M)
  - status: 状态 (0-维护, 1-正常)
  - v2_*: V2Ray相关配置
  - is_subscribe: 是否允许订阅
```

#### 用户管理
```yaml
user:  # 用户表
  - id: 用户ID
  - username: 用户名
  - email: 邮箱
  - password: 密码哈希
  - transfer_enable: 流量额度
  - u/d: 上传/下载流量
  - expire_time: 到期时间
  - ref_by: 邀请人ID
  - ref_count: 邀请人数
  - is_admin: 是否管理员
  - reg_ip: 注册IP
```

#### 订阅和套餐
```yaml
ss_plan:  # 套餐表
  - id: 套餐ID
  - name: 套餐名称
  - content: 套餐内容
  - month_price: 月付价格
  - quarter_price: 季付价格
  - year_price: 年付价格
  - transfer_enable: 包含流量
  - stock: 库存
  - is_sale: 是否上架

user_subscribe_log:  # 购买记录
  - id: 记录ID
  - user_id: 用户ID
  - plan_id: 套餐ID
  - price: 支付金额
  - ref_time: 续费时间
```

#### 支付系统
```yaml
payback:  # 支付回调记录
  - id: 记录ID
  - total_amount: 支付金额
  - tradeno: 商户订单号
  - trade_no: 第三方订单号
  - gateway: 支付网关
```

## 🧩 模块划分

### 1. 用户认证模块 (AuthController)
- **功能**: 用户注册、登录、密码重置、邮箱验证
- **输入**: 用户名、密码、邮箱、验证码
- **输出**: 用户会话、认证令牌
- **约束**: 密码必须加密存储、支持验证码防刷

### 2. 用户管理模块 (UserController)
- **功能**: 用户信息管理、邀请系统、流量查询
- **输入**: 用户ID、更新数据
- **输出**: 用户详情、使用统计
- **约束**: 仅管理员可操作其他用户信息

### 3. NodeManagementModule（节点管理模块）
- **功能**: 节点增删改查、负载监控、在线状态、流量统计
- **输入**: 节点配置、监控数据、筛选条件
- **输出**: 节点列表、状态信息、监控图表
- **约束**: 节点必须通过检测才能上线

### 4. 订阅服务模块 (SubscribeController)
- **功能**: 生成订阅链接、客户端配置、节点过滤
- **输入**: 用户ID、客户端类型
- **输出**: 订阅内容、节点配置
- **约束**: 根据用户套餐和分组过滤节点

### 5. 商店系统模块 (ShopController)
- **功能**: 套餐展示、购买流程、库存管理
- **输入**: 套餐ID、支付方式
- **输出**: 订单信息、支付链接
- **约束**: 库存检查、重复购买验证

### 6. 支付系统模块 (PaymentController)
- **功能**: 支付宝、微信、多种第三方支付
- **输入**: 支付金额、支付方式
- **输出**: 支付结果、订单状态
- **约束**: 支付回调验证、防重复支付

### 7. API 接口模块 (Api/*)
- **功能**: RESTful API、第三方集成、数据交互
- **输入**: 请求参数、认证信息
- **输出**: JSON 数据、状态码
- **约束**: API 访问频率限制

### 8. 工单系统模块 (TicketController)
- **功能**: 用户工单、客服回复、问题分类
- **输入**: 工单内容、回复信息
- **输出**: 工单列表、回复记录
- **约束**: 工单状态流转管理

## 🚀 安装部署

### 环境要求
- PHP >= 7.1.3
- MySQL >= 5.7
- Redis >= 3.0
- Composer
- Node.js >= 6.0 (用于前端构建)

### 安装步骤

1. **克隆项目**
```bash
git clone https://github.com/supersongssr/NPanel.git
cd NPanel
```

2. **安装依赖**
```bash
composer install
npm install
```

3. **配置环境**
```bash
cp .env.example .env
php artisan key:generate
```

4. **编辑 .env 文件**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ssrpanel
DB_USERNAME=root
DB_PASSWORD=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

5. **导入数据库**
```bash
mysql -u root -p ssrpanel < sql/db.sql
```

6. **设置权限**
```bash
chmod -R 755 storage
chmod -R 755 bootstrap/cache
```

7. **构建前端**
```bash
npm run prod
```

8. **配置 Web 服务器**

**Nginx 配置示例:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/NPanel/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 📖 API 文档

### 认证相关

#### 用户登录
```
POST /api/login
Content-Type: application/json

{
    "username": "user@example.com",
    "password": "password",
    "captcha": "captcha_code"
}

Response:
{
    "status": "success",
    "data": {
        "token": "jwt_token",
        "user": { ... }
    }
}
```

#### 用户注册
```
POST /api/register
Content-Type: application/json

{
    "username": "username",
    "email": "user@example.com",
    "password": "password",
    "captcha": "captcha_code",
    "ref_code": "referral_code"
}
```

### 订阅服务

#### 获取订阅链接
```
GET /api/subscribe?token={user_token}
Headers:
    Accept: application/json, text/plain, application/vnd.apple.mpegurl, application/octet-stream

Response (根据客户端类型返回不同格式):
- Base64编码的节点列表
- V2Ray配置
- Surge配置
- Clash配置
```

### 节点信息

#### 获取节点列表
```
GET /api/nodes
Headers:
    Authorization: Bearer {token}

Response:
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "name": "节点名称",
            "country_code": "hk",
            "server": "node.example.com",
            "port": 443,
            "method": "aes-256-gcm",
            "traffic_rate": 1.0,
            "status": 1
        }
    ]
}
```

### 支付相关

#### 创建支付订单
```
POST /api/payment/create
Headers:
    Authorization: Bearer {token}
Content-Type: application/json

{
    "plan_id": 1,
    "cycle": "month", // month, quarter, year
    "gateway": "alipay" // alipay, wechat, stripe
}

Response:
{
    "status": "success",
    "data": {
        "order_id": "order_123",
        "payment_url": "https://payment.gateway.com/...",
        "amount": 9.99
    }
}
```

#### 支付回调处理
```
POST /api/payment/callback/{gateway}
Content-Type: application/x-www-form-urlencoded

// 根据不同支付网关的回调格式处理
```

### 节点检测 API

#### 节点心跳
```
POST /api/ping
Content-Type: application/json

{
    "node_id": 1,
    "load": 0.5,
    "uptime": 86400,
    "online_users": 100
}

Response:
{
    "status": "success",
    "message": "Node status updated"
}
```

## 🔧 测试

### 远程测试配置
```yaml
remote:
    host: test.srp
    user: root
    sync: rsync -av
    path: /www/wwwroot/Npanel/
```

### 测试步骤
1. 使用 rsync 同步代码到测试服务器
2. 在远程服务器执行测试
3. 验证功能是否正常

**注意事项:**
- 不能在 macOS 本机运行（缺少部署环境）
- 测试前必须先同步到远程服务器

## 📝 开发规范

详见 [CLAUDE.md](./CLAUDE.md) 文件，包含：
- 命令与语法约束
- 工作流程
- 代码风格指南
- 测试要求

## 🤝 贡献

1. Fork 本项目
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request

## 📄 许可证

本项目基于 MIT 许可证开源 - 查看 [LICENSE](./LICENSE) 文件了解详情

## 🆘 支持

如果您遇到问题或有建议，请：
1. 查看 [FAQ](./docs/faq.md)
2. 搜索 [Issues](https://github.com/supersongssr/NPanel/issues)
3. 创建新的 Issue

