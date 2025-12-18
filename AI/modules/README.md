# NPanel 模块文档

本文档描述了 NPanel 项目的各个功能模块，方便 AI 理解项目架构和进行开发工作。

## 模块列表

### 1. AuthModule（用户认证模块）
**文件**: `AuthModule.yaml`
**控制器**: `AuthController`
**主要功能**: 用户注册、登录、密码重置、邮箱验证
**关键API**:
- POST /login - 用户登录
- POST /register - 用户注册
- POST /password/reset - 密码重置

### 2. UserManagementModule（用户管理模块）
**文件**: `UserManagementModule.yaml`
**控制器**: `UserController`
**主要功能**: 用户信息管理、邀请系统、流量查询、用户等级管理
**关键API**:
- GET /user/profile - 获取用户资料
- GET /user/traffic - 获取流量统计
- GET /user/invite - 获取邀请信息

### 3. NodeManagementModule（节点管理模块）
**文件**: `NodeManagementModule.yaml`
**控制器**: `AdminController`
**主要功能**: 节点增删改查、负载监控、在线状态检测、流量统计
**关键API**:
- GET /admin/node/list - 节点列表
- GET /admin/node/monitor/{id} - 节点监控
- POST /admin/node - 添加节点

### 4. SubscribeModule（订阅服务模块）
**文件**: `SubscribeModule.yaml`
**控制器**: `SubscribeController`
**主要功能**: 生成订阅链接、客户端配置、节点过滤
**关键API**:
- GET /subscribe/{token} - 获取订阅内容
- GET /api/subscribe - API订阅接口

### 5. ShopModule（商店系统模块）
**文件**: `ShopModule.yaml`
**控制器**: `ShopController`
**主要功能**: 套餐展示、购买流程、库存管理、订单处理
**关键API**:
- GET /shop/plans - 获取套餐列表
- POST /shop/purchase - 创建购买订单

### 6. PaymentModule（支付系统模块）
**文件**: `PaymentModule.yaml`
**控制器**: `PaymentController`, `Api/*PayController`
**主要功能**: 多种支付方式处理、支付回调、订单管理
**关键API**:
- POST /payment/create - 创建支付订单
- POST /api/payment/callback/{gateway} - 支付回调

### 7. ApiModule（API接口模块）
**文件**: `ApiModule.yaml`
**控制器**: `Api/LoginController`, `Api/PingController`
**主要功能**: RESTful API、第三方集成、数据交互
**关键API**:
- POST /api/login - API登录
- POST /api/ping - 节点心跳

### 8. TicketModule（工单系统模块）
**文件**: `TicketModule.yaml`
**控制器**: `TicketController`
**主要功能**: 用户工单、客服回复、问题分类、状态管理
**关键API**:
- GET /tickets - 获取工单列表
- POST /tickets - 创建工单

## 模块文档格式说明

每个模块的 YAML 文档包含以下字段：

- `module`: 模块基本信息（名称、中文名、描述）
- `controller`: 对应的控制器
- `model`: 使用的数据模型
- `where`: 代码位置
- `api_endpoints`: API 端点列表
- `input`: 输入参数定义
- `in`: 功能输入描述
- `output`: 输出数据定义
- `out`: 功能输出描述
- `do`: 模块执行的任务
- `must`: 约束条件

## 使用说明

1. **AI 开发时参考**: 在进行代码开发时，先查看对应模块的 YAML 文档，了解模块的功能、输入输出和约束条件。

2. **API 设计参考**: 新增 API 时参考现有模块的 API 设计模式。

3. **数据模型理解**: 通过查看各模块使用的模型，理解数据表之间的关系。

4. **约束条件遵守**: 每个模块的 `must` 字段定义了开发时必须遵守的约束条件。

## 模块关系图

```
┌─────────────────┐    ┌─────────────────┐
│   AuthModule    │    │ UserManagement  │
│   (用户认证)      │────│   Module        │
│                 │    │   (用户管理)      │
└─────────────────┘    └─────────────────┘
         │                       │
         │                       │
┌─────────────────┐    ┌─────────────────┐
│   SubscribeMod  │    │  NodeManagement │
│   (订阅服务)      │────│    Module       │
│                 │    │   (节点管理)      │
└─────────────────┘    └─────────────────┘
         │                       │
         │                       │
┌─────────────────┐    ┌─────────────────┐
│   ShopModule    │    │ PaymentModule   │
│   (商店系统)      │────│  (支付系统)      │
│                 │    │                 │
└─────────────────┘    └─────────────────┘
         │                       │
         └───────────────────────┘
                    │
         ┌─────────────────┐
         │   ApiModule     │
         │   (API接口)      │
         └─────────────────┘
                    │
         ┌─────────────────┐
         │  TicketModule   │
         │  (工单系统)      │
         └─────────────────┘
```

## 开发规范

1. 所有模块必须遵循 CLAUDE.md 中定义的开发规范
2. 新增功能必须在对应模块的 YAML 文档中更新
3. API 设计必须使用 RESTful 风格
4. 数据验证必须在控制器中完成
5. 所有用户输入必须过滤和验证
6. 敏感操作需要权限验证
7. 重要操作必须记录日志