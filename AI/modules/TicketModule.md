# TicketModule - 工单系统模块

## 概述
TicketModule 管理用户工单的创建、回复、状态流转和客服支持，提供完整的客户服务支持系统，包括问题分类、优先级管理、工单分配和满意度评价等功能。

## 技术信息

### 控制器
- `TicketController` - 工单系统控制器
- 位置：`app/Http/Controllers/TicketController.php`

### 数据模型
- `Ticket` - 工单主表
- `TicketReply` - 工单回复记录
- `User` - 用户信息
- `TicketType` - 工单类型

## 工单状态流转

### 状态定义
```
待处理 (pending) → 处理中 (processing) → 已完成 (completed) → 已关闭 (closed)
     ↓               ↓                 ↓
  已取消 (cancelled)  ←------------ 已重新打开 (reopened)
```

### 状态说明
- **待处理 (pending)**: 用户刚提交的工单，等待客服处理
- **处理中 (processing)**: 客服正在处理的工单
- **已完成 (completed)**: 问题已解决，等待用户确认
- **已关闭 (closed)**: 工单已关闭，不再活跃
- **已取消 (cancelled)**: 用户主动取消的工单
- **已重新打开 (reopened)**: 已完成或关闭的工单被重新打开

## 工单分类

### 工单类型
| 类型ID | 类型名称 | 说明 | 优先级 |
|--------|----------|------|--------|
| 1 | 问题反馈 | 使用过程中遇到的问题 | 中 |
| 2 | 功能建议 | 对系统功能的建议和改进 | 低 |
| 3 | 投诉申诉 | 对服务或处理的投诉 | 高 |
| 4 | 账户问题 | 账户相关的问题 | 高 |
| 5 | 支付问题 | 支付相关的咨询和问题 | 高 |
| 6 | 其他 | 其他类型的问题 | 低 |

### 优先级定义
- **高**: 严重影响使用，需要立即处理
- **中**: 影响部分功能，24小时内处理
- **低**: 一般性咨询，72小时内处理

## API 接口

### 1. 获取工单列表
```http
GET /tickets?status=pending&type=1&page=1
Authorization: Bearer {user_token}
```

**查询参数**:
- `status`: 工单状态
- `type`: 工单类型
- `priority`: 优先级
- `page`: 页码

**响应**：
```json
{
    "status": "success",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "title": "无法连接香港节点",
                "content": "从今天下午开始无法连接香港的节点...",
                "status": "pending",
                "type": 1,
                "priority": "high",
                "user_id": 123,
                "user_name": "testuser",
                "reply_count": 0,
                "last_reply_at": null,
                "created_at": "2024-12-18 20:30:00",
                "updated_at": "2024-12-18 20:30:00"
            }
        ],
        "total": 5,
        "per_page": 10
    }
}
```

### 2. 创建工单
```http
POST /tickets
Authorization: Bearer {user_token}
Content-Type: application/json

{
    "title": "无法连接香港节点",
    "content": "从今天下午开始无法连接香港的节点，其他节点正常。尝试重启客户端也没有效果。请帮忙检查一下节点状态。",
    "type": 1,
    "priority": "high",
    "attachments": [
        {
            "name": "error_log.txt",
            "url": "https://example.com/attachments/1.txt"
        }
    ]
}
```

**响应**：
```json
{
    "status": "success",
    "data": {
        "id": 1,
        "ticket_no": "TK20241218001",
        "title": "无法连接香港节点",
        "status": "pending",
        "type": 1,
        "priority": "high",
        "created_at": "2024-12-18 20:30:00"
    }
}
```

### 3. 获取工单详情
```http
GET /ticket/1
Authorization: Bearer {user_token}
```

**响应**：
```json
{
    "status": "success",
    "data": {
        "id": 1,
        "ticket_no": "TK20241218001",
        "title": "无法连接香港节点",
        "content": "从今天下午开始无法连接香港的节点...",
        "status": "processing",
        "type": 1,
        "priority": "high",
        "user_id": 123,
        "user_name": "testuser",
        "assigned_to": 456,
        "assigned_name": "客服小王",
        "replies": [
            {
                "id": 1,
                "content": "您好，我们已经收到了您的问题。正在为您检查节点状态...",
                "is_admin": true,
                "admin_name": "客服小王",
                "created_at": "2024-12-18 20:45:00"
            },
            {
                "id": 2,
                "content": "香港节点确实出现了故障，技术人员正在处理中...",
                "is_admin": true,
                "admin_name": "客服小王",
                "created_at": "2024-12-18 21:00:00"
            }
        ],
        "created_at": "2024-12-18 20:30:00",
        "updated_at": "2024-12-18 21:00:00"
    }
}
```

### 4. 回复工单
```http
POST /ticket/1/reply
Authorization: Bearer {user_token}
Content-Type: application/json

{
    "content": "好的，请问大概什么时候能恢复？"
}
```

### 5. 关闭工单
```http
PUT /ticket/1/close
Authorization: Bearer {user_token}
Content-Type: application/json

{
    "satisfaction": 5,
    "feedback": "问题解决得很快，客服态度很好！"
}
```

### 6. 重新打开工单
```http
PUT /ticket/1/reopen
Authorization: Bearer {user_token}
Content-Type: application/json

{
    "reason": "问题又出现了",
    "content": "节点恢复后又无法连接了"
}
```

### 7. 管理员接口
```http
# 管理员获取所有工单
GET /admin/tickets?status=all&assigned_to=456
Authorization: Bearer {admin_token}

# 分配工单
PUT /admin/ticket/1/assign
Authorization: Bearer {admin_token}
Content-Type: application/json
{
    "admin_id": 456,
    "note": "分配给客服小王处理"
}

# 批量操作工单
POST /admin/tickets/batch
Authorization: Bearer {admin_token}
Content-Type: application/json
{
    "ticket_ids": [1, 2, 3],
    "action": "close",
    "reason": "批量关闭"
}
```

## 使用方法

### 1. 创建工单
```php
public function createTicket(Request $request)
{
    $user = Auth::user();

    // 验证输入
    $request->validate([
        'title' => 'required|string|min:5|max:100',
        'content' => 'required|string|min:10|max:2000',
        'type' => 'required|integer|exists:ticket_type,id',
        'priority' => 'sometimes|integer|in:1,2,3',
        'attachments' => 'sometimes|array',
        'attachments.*' => 'file|max:10240'
    ]);

    // 检查是否已有相似工单
    $similarTicket = Ticket::where('user_id', $user->id)
                           ->where('status', '!=', 'closed')
                           ->where('created_at', '>=', Carbon::now()->subHours(24))
                           ->where(function($query) use ($request) {
                               $query->where('title', 'like', '%' . $request->title . '%')
                                     ->orWhere('content', 'like', '%' . substr($request->content, 0, 100) . '%');
                           })
                           ->first();

    if ($similarTicket) {
        return response()->json([
            'status' => 'error',
            'message' => '您已有相似的工单正在处理中',
            'data' => [
                'ticket_id' => $similarTicket->id,
                'ticket_no' => $similarTicket->ticket_no
            ]
        ], 400);
    }

    // 创建工单
    $ticket = new Ticket();
    $ticket->ticket_no = $this->generateTicketNo();
    $ticket->user_id = $user->id;
    $ticket->title = $request->title;
    $ticket->content = $request->content;
    $ticket->type = $request->type;
    $ticket->priority = $request->priority ?? 2; // 默认中等优先级
    $ticket->status = 'pending';
    $ticket->save();

    // 处理附件
    if ($request->hasFile('attachments')) {
        foreach ($request->file('attachments') as $file) {
            $path = $file->store('ticket_attachments', 'public');
            TicketAttachment::create([
                'ticket_id' => $ticket->id,
                'filename' => $file->getClientOriginalName(),
                'filepath' => $path,
                'filesize' => $file->getSize()
            ]);
        }
    }

    // 自动分配客服
    $this->autoAssignTicket($ticket);

    // 发送通知
    $this->sendNewTicketNotification($ticket);

    return response()->json([
        'status' => 'success',
        'message' => '工单创建成功',
        'data' => $ticket
    ]);
}
```

### 2. 回复工单
```php
public function replyTicket(Request $request, $id)
{
    $user = Auth::user();
    $ticket = Ticket::findOrFail($id);

    // 权限检查
    if ($ticket->user_id != $user->id && !$user->is_admin) {
        return response()->json(['message' => '无权限访问此工单'], 403);
    }

    // 检查工单状态
    if (in_array($ticket->status, ['closed', 'cancelled'])) {
        return response()->json(['message' => '工单已关闭，无法回复'], 400);
    }

    $request->validate([
        'content' => 'required|string|min:5|max:1000'
    ]);

    // 创建回复
    $reply = new TicketReply();
    $reply->ticket_id = $ticket->id;
    $reply->content = $request->content;
    $reply->is_admin = $user->is_admin;
    $reply->user_id = $user->id;
    $reply->save();

    // 更新工单状态
    if ($ticket->status == 'pending') {
        $ticket->status = 'processing';
    }
    $ticket->last_reply_at = Carbon::now();
    $ticket->save();

    // 发送通知
    if ($user->is_admin) {
        // 通知用户
        $this->sendReplyNotification($ticket, $ticket->user, $reply);
    } else {
        // 通知客服
        $this->sendReplyNotification($ticket, $ticket->assigned_admin, $reply);
    }

    return response()->json([
        'status' => 'success',
        'message' => '回复成功',
        'data' => $reply
    ]);
}
```

### 3. 自动分配工单
```php
private function autoAssignTicket($ticket)
{
    // 根据工单类型和优先级分配
    $query = User::where('is_admin', 1)
                 ->where('status', 1);

    // 根据工单类型分配给对应客服
    switch ($ticket->type) {
        case 5: // 支付问题
            $query->where('role', 'finance');
            break;
        case 4: // 账户问题
            $query->where('role', 'account');
            break;
        default:
            $query->where('role', 'support');
    }

    // 获取负载最轻的客服
    $admin = $query->withCount(['tickets' => function($query) {
                $query->where('status', 'processing');
            }])
            ->orderBy('tickets_count', 'asc')
            ->first();

    if ($admin) {
        $ticket->assigned_to = $admin->id;
        $ticket->assigned_at = Carbon::now();
        $ticket->save();

        // 发送分配通知
        $this->sendAssignmentNotification($ticket, $admin);
    }
}
```

### 4. 生成工单号
```php
private function generateTicketNo()
{
    $prefix = 'TK' . date('Ymd');
    $lastTicket = Ticket::where('ticket_no', 'like', $prefix . '%')
                        ->orderBy('ticket_no', 'desc')
                        ->first();

    if ($lastTicket) {
        $sequence = intval(substr($lastTicket->ticket_no, -4)) + 1;
    } else {
        $sequence = 1;
    }

    return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}
```

## 工单统计

### 1. 获取工单统计
```php
public function getTicketStats()
{
    $stats = [
        'total' => Ticket::count(),
        'pending' => Ticket::where('status', 'pending')->count(),
        'processing' => Ticket::where('status', 'processing')->count(),
        'completed' => Ticket::where('status', 'completed')->count(),
        'today' => Ticket::whereDate('created_at', Carbon::today())->count(),
        'this_week' => Ticket::whereBetween('created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ])->count(),
        'this_month' => Ticket::whereMonth('created_at', Carbon::now()->month)
                          ->whereYear('created_at', Carbon::now()->year)
                          ->count(),
    ];

    // 按类型统计
    $stats['by_type'] = TicketType::withCount(['tickets' => function($query) {
        $query->whereMonth('created_at', Carbon::now()->month)
              ->whereYear('created_at', Carbon::now()->year);
    }])->get();

    // 客服处理统计
    if (Auth::user()->is_admin) {
        $stats['admin_stats'] = [
            'assigned' => Ticket::where('assigned_to', Auth::id())->count(),
            'processing' => Ticket::where('assigned_to', Auth::id())
                               ->where('status', 'processing')->count(),
            'completed' => Ticket::where('assigned_to', Auth::id())
                               ->where('status', 'completed')
                               ->whereMonth('updated_at', Carbon::now()->month)
                               ->count(),
        ];
    }

    return $stats;
}
```

## 约束条件

1. **权限控制**
   - 用户只能查看自己的工单
   - 管理员可以查看所有工单
   - 分配权限限制

2. **业务规则**
   - 工单标题5-100字符
   - 工单内容10-2000字符
   - 24小时内不能创建重复工单
   - 已关闭工单可以重新打开

3. **安全要求**
   - 内容需要过滤敏感信息
   - 附件大小限制10MB
   - 防止恶意提交

## 最佳实践

### 1. 使用队列发送通知
```php
// 异步发送工单通知
SendTicketNotification::dispatch([
    'ticket_id' => $ticket->id,
    'type' => 'new',
    'recipient' => $ticket->user_id
])->onQueue('notifications');
```

### 2. 工单自动关闭
```php
// 定时任务自动处理超时工单
$schedule->call(function () {
    // 自动关闭7天未更新的已完成工单
    Ticket::where('status', 'completed')
          ->where('updated_at', '<', Carbon::now()->subDays(7))
          ->update(['status' => 'closed']);

    // 自动提醒3天未回复的工单
    Ticket::where('status', 'processing')
          ->where('last_reply_at', '<', Carbon::now()->subDays(3))
          ->get()
          ->each(function ($ticket) {
              $this->sendFollowUpReminder($ticket);
          });
})->daily();
```

### 3. 工单满意度评价
```php
public function rateTicket(Request $request, $id)
{
    $ticket = Ticket::where('user_id', Auth::id())
                   ->where('status', 'completed')
                   ->findOrFail($id);

    $request->validate([
        'satisfaction' => 'required|integer|min:1|max:5',
        'feedback' => 'sometimes|string|max:500'
    ]);

    $ticket->satisfaction = $request->satisfaction;
    $ticket->feedback = $request->feedback;
    $ticket->status = 'closed';
    $ticket->save();

    // 更新客服绩效
    if ($ticket->assigned_to) {
        $this->updateAdminPerformance($ticket->assigned_to, $request->satisfaction);
    }

    return response()->json(['status' => 'success']);
}
```

## 测试要点

1. **工单创建**
   - 各种类型工单创建
   - 附件上传
   - 重复工单检测

2. **工单流程**
   - 状态流转
   - 回复功能
   - 分配机制

3. **权限控制**
   - 用户访问权限
   - 管理员权限
   - 数据隔离

4. **通知系统**
   - 创建通知
   - 回复通知
   - 分配通知

## 注意事项

1. 工单内容需要敏感词过滤
2. 附件需要安全扫描
3. 工单统计需要实时更新
4. 客服分配要考虑工作负载
5. 满意度评价要匿名统计
6. 定期分析工单数据改进服务
7. 工单知识库积累常用回复
8. 支持工单模板快速创建