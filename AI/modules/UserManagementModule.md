# UserManagementModule - 用户管理模块

## 概述
UserManagementModule 负责管理用户信息、邀请系统、流量查询、用户等级管理等功能，是系统的核心业务模块之一。

## 技术信息

### 控制器
- `UserController` - 用户管理控制器
- 位置：`app/Http/Controllers/UserController.php`

### 数据模型
- `User` - 用户信息模型
- `UserTrafficLog` - 用户流量日志
- `UserTrafficDaily` - 用户每日流量统计
- `UserTrafficHourly` - 用户每小时流量统计
- `Invite` - 邀请码模型
- `ReferralLog` - 邀请记录模型
- `UserLoginLog` - 用户登录日志
- `UserBanLog` - 用户封禁日志
- `UserBalanceLog` - 用户余额日志
- `UserSubscribe` - 用户订阅信息
- `UserLabel` - 用户标签模型

## 主要功能

### 1. 用户资料管理
- 查看用户个人信息
- 修改用户资料
- 更改密码
- 管理用户标签
- 查看登录历史

### 2. 流量统计与查询
- 实时流量使用情况
- 历史流量统计
- 流量使用图表
- 节点流量分布
- 流量预警提醒

### 3. 邀请系统
- 生成邀请链接
- 邀请统计
- 邀请返利
- 邀请排行榜

### 4. 订阅管理
- 查看订阅信息
- 重置订阅令牌
- 订阅链接管理
- 客户端配置下载

## API 接口

### 1. 获取用户资料
```http
GET /user/profile
Authorization: Bearer {token}
```

**响应**：
```json
{
    "status": "success",
    "data": {
        "id": 1,
        "username": "testuser",
        "email": "test@example.com",
        "level": 2,
        "expire_time": "2025-12-18 00:00:00",
        "transfer_enable": 107374182400,
        "u": 1073741824,
        "d": 2147483648,
        "node_group": "1,2,3",
        "last_checkin": "2024-12-18 10:30:00",
        "reg_time": "2023-12-18 00:00:00",
        "invite_num": 5,
        "class_expire": "2025-01-18 00:00:00"
    }
}
```

### 2. 更新用户资料
```http
PUT /user/profile
Authorization: Bearer {token}
Content-Type: application/json

{
    "email": "newemail@example.com",
    "node_group": "1,2",
    "nickname": "新昵称"
}
```

### 3. 获取流量统计
```http
GET /user/traffic
Authorization: Bearer {token}
```

### 4. 修改密码
```http
POST /user/change-password
Authorization: Bearer {token}
Content-Type: application/json

{
    "old_password": "oldpass123",
    "new_password": "newpass123"
}
```

### 5. 获取邀请信息
```http
GET /user/invite
Authorization: Bearer {token}
```

### 6. 重置订阅码
```http
POST /user/reset-subscribe
Authorization: Bearer {token}
```

### 7. 获取余额记录
```http
GET /user/balance
Authorization: Bearer {token}
```

## 数据结构

### 用户信息
| 字段 | 类型 | 说明 |
|------|------|------|
| id | integer | 用户ID |
| username | string | 用户名 |
| email | string | 邮箱 |
| level | integer | 用户等级 |
| expire_time | datetime | 账户到期时间 |
| transfer_enable | integer | 流量额度（bytes） |
| u | integer | 上传流量（bytes） |
| d | integer | 下载流量（bytes） |
| node_group | string | 节点分组ID |
| last_checkin | datetime | 最后签到时间 |
| invite_num | integer | 邀请人数 |

### 流量统计
| 字段 | 类型 | 说明 |
|------|------|------|
| total | integer | 总流量使用（bytes） |
| upload | integer | 上传流量（bytes） |
| download | integer | 下载流量（bytes） |
| unused | integer | 剩余流量（bytes） |
| usage | float | 使用百分比 |
| daily_data | array | 每日流量数据 |
| hourly_data | array | 每小时流量数据 |

## 使用方法

### 1. 查看用户资料
```php
// 获取当前用户信息
$user = Auth::user();
$profile = [
    'id' => $user->id,
    'username' => $user->username,
    'email' => $user->email,
    'level' => $user->level,
    'expire_time' => $user->expire_time,
    // ... 其他字段
];

// 计算流量使用情况
$totalUsed = $user->u + $user->d;
$totalTransfer = $user->transfer_enable;
$percentage = ($totalUsed / $totalTransfer) * 100;
```

### 2. 统计流量数据
```php
// 获取每日流量统计
$dailyTraffic = UserTrafficDaily::where('user_id', $userId)
    ->where('created_at', '>=', Carbon::now()->subDays(30))
    ->orderBy('created_at', 'asc')
    ->get();

// 获取每小时流量统计
$hourlyTraffic = UserTrafficHourly::where('user_id', $userId)
    ->where('created_at', '>=', Carbon::today())
    ->orderBy('created_at', 'asc')
    ->get();
```

### 3. 处理邀请系统
```php
// 生成邀请码
$invite = new Invite();
$invite->user_id = $userId;
$invite->code = Str::random(8);
$invite->save();

// 记录邀请关系
$referralLog = new ReferralLog();
$referralLog->ref_by = $refUserId;
$referralLog->ref_user_id = $newUserId;
$referralLog->amount = 0; // 可设置邀请奖励
$referralLog->save();
```

### 4. 修改用户密码
```php
// 验证旧密码
if (!Hash::check($oldPassword, $user->password)) {
    return response()->json(['message' => '原密码错误'], 400);
}

// 更新密码
$user->password = Hash::make($newPassword);
$user->save();
```

## 约束条件

1. **权限控制**
   - 用户只能查看和修改自己的信息
   - 管理员可以操作所有用户信息
   - 敏感操作需要密码验证

2. **数据准确性**
   - 流量统计必须实时准确
   - 用户余额变动需要记录
   - 邀请关系必须完整记录

3. **安全要求**
   - 密码修改必须验证原密码
   - 邮箱修改需要邮件验证
   - 登录日志必须完整记录

## 最佳实践

### 1. 流量统计优化
```php
// 使用缓存减少数据库查询
$trafficStats = Cache::remember('user_traffic_'.$userId, 300, function() use ($userId) {
    return [
        'total' => UserTrafficLog::where('user_id', $userId)->sum('total'),
        'upload' => UserTrafficLog::where('user_id', $userId)->sum('u'),
        'download' => UserTrafficLog::where('user_id', $userId)->sum('d'),
    ];
});
```

### 2. 用户标签管理
```php
// 批量添加用户标签
$user->labels()->attach([1, 2, 3]);

// 获取用户标签列表
$labels = $user->labels()->pluck('name')->toArray();
```

### 3. 邀请码管理
```php
// 限制每个用户最多5个有效邀请码
$activeInvites = Invite::where('user_id', $userId)
    ->whereNull('used_at')
    ->count();

if ($activeInvites >= 5) {
    return response()->json(['message' => '邀请码数量已达上限'], 400);
}
```

## 测试要点

1. **用户资料更新测试**
   - 正常资料修改
   - 重复邮箱检查
   - 敏感信息修改

2. **流量统计测试**
   - 流量计算准确性
   - 统计数据展示
   - 流量预警功能

3. **邀请系统测试**
   - 邀请码生成
   - 邀请关系建立
   - 返利计算

4. **权限测试**
   - 用户只能查看自己信息
   - 管理员权限控制
   - 未授权访问限制

## 注意事项

1. 用户密码必须加密存储
2. 流量数据需要定期备份
3. 邀请码需要有有效期
4. 敏感操作需要二次验证
5. 所有操作需要记录日志
6. 用户删除需要软删除
7. 批量操作需要队列处理