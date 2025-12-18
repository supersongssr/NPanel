# AuthModule - 用户认证模块

## 概述
AuthModule 负责处理用户注册、登录、密码重置、邮箱验证等所有认证相关功能，是整个系统的入口模块。

## 技术信息

### 控制器
- `AuthController` - 主要认证控制器
- 位置：`app/Http/Controllers/AuthController.php`

### 数据模型
- `User` - 用户信息模型
- `EmailLog` - 邮件发送记录模型

## API 接口

### 1. 用户登录
```http
POST /login
Content-Type: application/json

{
    "username": "user@example.com",
    "password": "password123",
    "captcha": "abcd"
}
```

**响应**：
```json
{
    "status": "success",
    "data": {
        "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "user": {
            "id": 1,
            "username": "user@example.com",
            "level": 1,
            "expire_time": "2025-01-18 00:00:00",
            "transfer_enable": 107374182400
        }
    }
}
```

### 2. 用户注册
```http
POST /register
Content-Type: application/json

{
    "username": "newuser",
    "email": "newuser@example.com",
    "password": "password123",
    "captcha": "wxyz",
    "ref_code": "INVITE123"
}
```

### 3. 密码重置
```http
POST /password/reset
Content-Type: application/json

{
    "email": "user@example.com",
    "captcha": "qwer"
}
```

### 4. 邮箱验证
```http
POST /email/verify
Content-Type: application/json

{
    "token": "verification_token_123"
}
```

### 5. 退出登录
```http
GET /logout
Authorization: Bearer {token}
```

## 数据结构

### 输入参数
| 参数名 | 类型 | 必需 | 说明 |
|--------|------|------|------|
| username | string | 是 | 用户名或邮箱地址 |
| password | string | 是 | 用户密码（最少8位） |
| email | string | 注册时必需 | 邮箱地址，必须是有效格式 |
| captcha | string | 是 | 图形验证码 |
| ref_code | string | 否 | 推荐人邀请码 |

### 输出数据
| 字段 | 类型 | 说明 |
|------|------|------|
| token | string | JWT认证令牌，有效期30天 |
| user.id | integer | 用户ID |
| user.username | string | 用户名 |
| user.email | string | 邮箱 |
| user.level | integer | 用户等级 |
| user.expire_time | datetime | 账户到期时间 |
| user.transfer_enable | integer | 流量额度（bytes） |

## 功能实现

### 主要任务
1. **验证用户输入合法性**
   - 检查用户名格式
   - 验证邮箱格式
   - 确认密码强度
   - 验证码校验

2. **处理认证流程**
   - 登录凭据验证
   - 密码加密存储
   - JWT令牌生成
   - 会话管理

3. **安全措施**
   - 登录失败次数限制
   - 验证码防机制
   - 密码强度要求
   - JWT令牌有效期控制

4. **邮件服务**
   - 注册确认邮件
   - 密码重置邮件
   - 邮箱验证邮件
   - 邮件发送记录

### 约束条件
- 密码必须使用 `Hash::make()` 加密存储
- 所有用户输入必须进行验证和过滤
- 必须启用验证码防机制
- 登录失败次数限制（5次失败后锁定30分钟）
- JWT令牌有效期为30天
- 新注册用户必须验证邮箱后才能正常使用
- 用户名必须唯一，长度3-20字符
- 邮箱地址必须唯一且有效

## 代码示例

### 登录验证逻辑
```php
public function login(Request $request)
{
    // 验证输入
    $credentials = $request->validate([
        'username' => 'required|string',
        'password' => 'required|string',
        'captcha' => 'required|string'
    ]);

    // 检查验证码
    if (!$this->validateCaptcha($credentials['captcha'])) {
        return response()->json(['message' => '验证码错误'], 422);
    }

    // 验证用户凭据
    if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']])) {
        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => $token,
                'user' => $user
            ]
        ]);
    }

    return response()->json(['message' => '用户名或密码错误'], 401);
}
```

### 密码加密
```php
// 创建用户时加密密码
$user = new User();
$user->password = Hash::make($request->password);
$user->save();

// 验证密码
if (Hash::check($password, $user->password)) {
    // 密码正确
}
```

## 相关配置

### 环境变量
```env
# JWT配置
JWT_SECRET=your_jwt_secret_key
JWT_TTL=1440  # 令牌有效期（分钟）

# 邮件配置
MAIL_DRIVER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@example.com
MAIL_PASSWORD=mail_password

# 验证码配置
CAPTCHA_LENGTH=4
CAPTCHA_EXPIRE=300  # 5分钟
```

## 测试要点

1. **登录测试**
   - 正确的用户名密码
   - 错误的凭据
   - 验证码错误
   - 账户被锁定

2. **注册测试**
   - 正常注册流程
   - 重复用户名/邮箱
   - 无效邮箱格式
   - 密码强度不足

3. **安全测试**
   - SQL注入防护
   - XSS攻击防护
   - 暴力破解防护
   - 令牌安全性

## 注意事项

1. 所有密码操作必须在服务器端进行
2. 敏感信息不能在日志中记录
3. 验证码必须设置过期时间
4. 邮件发送需要异步处理
5. JWT令牌需要定期轮换
6. 登录日志需要记录用于安全审计