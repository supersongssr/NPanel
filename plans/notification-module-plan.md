# 管理员告警通知模块 (Notification) 设计与迁移方案

> 状态: **待审核** · 范围: 方案 B(统一抽象) · 日期: 2025-07
> 审核人: ______

---

## 0. 目标与边界 (必须先对齐)

### 目标
把"**通知管理员**"的告警抽象成统一模块, 业务点只声明 **「发生了什么事 / 多严重」**,
不关心走 Telegram / ServerChan 还是别的渠道。

### 明确划入本模块 (✅ 通知管理员)
- `Telegram::send(...)` —— 当前 2 处 (UserController 返利提现)
- `ServerChan::send(...)` —— 当前 10 处 (节点离线/TCP阻断/流量异常/工单/日报等)
- (可选) `Mail::to(crash_warning_email)` 给管理员的告警邮件

### 明确划出本模块 (❌ 通知用户, 不动)
- 所有 `Mail::to(用户邮箱)`、`app/Mail/*` 给用户的通知 (注册激活/支付/工单回复给用户)
- 用户视角的一切通知

> 边界判断: **谁是收信人**。收信人是管理员 = 本模块; 收信人是终端用户 = 不在范围。

---

## 1. 现状诊断 (方案的事实基础)

### 1.1 调用点全景 + 级别判定

| # | 文件:行 | 场景 | 当前渠道 | **应判级别** | 该发 Telegram? |
|---|---|---|---|---|---|
| 1 | AutoJob.php:759 | 节点离线/心跳异常 | ServerChan | **ERROR** | ✅ 该发(当前没接!) |
| 2 | AutoCheckNodeTCP.php:155 | 节点 TCP 阻断 | ServerChan | **ERROR** | ✅ 该发(当前没接!) |
| 3 | UserTrafficAbnormalAutoWarning.php:54 | 流量异常用户 | ServerChan | WARNING | ❌(error-only) |
| 4 | UserController.php:1183 | 邀请返利提现申请 | **Telegram** | INFO | ❌(当前**错误接入**) |
| 5 | UserController.php:1292 | 消费返利提现申请 | **Telegram** | INFO | ❌(当前**错误接入**) |
| 6 | TicketController.php:81 | 工单新回复 | ServerChan | INFO | ❌ |
| 7 | TicketController.php:140 | 用户新建工单 | ServerChan | INFO | ❌ |
| 8 | UserController.php:497 | 用户新建工单 | ServerChan | INFO | ❌ |
| 9 | UserController.php:556 | 工单回复 | ServerChan | INFO | ❌ |
| 10 | UserController.php:587 | 工单用户关闭 | ServerChan | INFO | ❌ |
| 11 | AutoReportNode.php:46 | 节点日报 | ServerChan | INFO | ❌ |

> ⚠️ **核心矛盾**: 你要求"Telegram 只收 error", 但现状 Telegram 只发了 2 条 info,
> 而 2 条真正的 error(节点离线/TCP阻断)压根没接 Telegram。本方案会**纠正**这个倒挂。

### 1.2 底层依赖 (已确认)
- `App\Components\Curl::send($url, $data)` —— 静态, 10s 超时, 无重试。模块复用它, 不重写。
- `App\Components\Helpers::systemConfig()` —— 三层合并(default → .config.php → DB白名单)。模块复用。
- `email_log` 表 —— 复用。`type`: 1=邮件 / 2=ServerChan / 3=Telegram(代码已用, 注释未写)。
  - ⚠️ 存量不一致: 表注释只写了 1/2, 代码已用 3; `status` 表定义 -1/0/1, 代码用 0/1。**记录但不本次修**。

---

## 2. 模块设计

### 2.1 目录结构
```
app/Services/Notification/
├── NotifyService.php            ← 主入口 Service (门面)
├── Notification.php             ← 消息值对象 (title/content/level/context)
├── Level.php                    ← 级别常量 (const, 7.4 兼容, 不用 enum)
├── Channels/
│   ├── ChannelInterface.php     ← 渠道契约
│   ├── TelegramChannel.php      ← Telegram 实现 (迁自 Components/Telegram.php)
│   └── ServerChanChannel.php    ← ServerChan 实现 (迁自 Components/ServerChan.php)
└── NotificationLogger.php       ← 日志写入 (复用 email_log)
```

> 命名空间选择 `App\Services\Notification` —— 与现有 `App\Services\NodeAddress`、
> `App\Services\DnsRecordCleanupService` 风格一致; 不用 `App\Modules\` 避免引入新概念层。
> **[决策点 D1] 请确认是否接受此路径。**

### 2.2 Level (PHP 7.4 const, 不用 enum)
```php
class Level {
    const ERROR   = 400;   // 系统故障级: 节点离线/阻断/服务异常
    const WARNING = 300;   // 需关注: 流量异常/阈值告警
    const INFO    = 200;   // 日常事件: 工单/提现申请/日报
}
// 用数字便于 $level >= $minLevel 比较
```

### 2.3 ChannelInterface (PHP 7.4, 无 union type)
```php
interface ChannelInterface {
    /** 渠道标识 telegram|serverchan */
    public function name();
    /** 是否启用 (查对应 is_xxx 开关) */
    public function enabled();
    /** 该渠道接受的最低级别, 低于此级别不投递 */
    public function minLevel();
    /** 实际发送, 返回 bool */
    public function send(Notification $notification);
}
```

### 2.4 各渠道默认 minLevel (体现"Telegram 只收 error")
| 渠道 | enabled 开关 | 默认 minLevel | 含义 |
|---|---|---|---|
| TelegramChannel | `is_telegram` | **Level::ERROR** | **只收 error** (你的硬需求) |
| ServerChanChannel | `is_server_chan` | Level::INFO | 收所有(info 及以上) |

> minLevel 可被配置项覆盖(可选), 默认值硬编码在 Channel 类里, 零配置即符合你的需求。

### 2.5 NotifyService 主入口
```php
class NotifyService {
    public function error($title, $content, array $context = []);   // → Level::ERROR
    public function warning($title, $content, array $context = []); // → Level::WARNING
    public function info($title, $content, array $context = []);    // → Level::INFO

    /** 核心: 遍历所有已注册渠道, 对 enabled 且 minLevel<=level 的投递 */
    public function notify($level, $title, $content, array $context = []);
}
```

### 2.6 业务点改造前后对比
```php
// ── 改造前 (节点离线, AutoJob.php) ──
ServerChan::send('节点异常警告', "节点{$node->name}可能离线了");
// 问题: Telegram 永远收不到

// ── 改造后 ──
app(\App\Services\Notification\NotifyService::class)
    ->error('节点异常警告', "节点 {$node->name} 心跳异常, 可能离线");
// → 自动: Telegram(enabled & minLevel=ERROR) 收到 ✅
//        ServerChan(enabled & minLevel=INFO)  也收到 ✅
// 业务点不再关心渠道, 只声明"这是 error"
```

---

## 3. 迁移策略: 绞杀者模式 (5 阶段, 低风险)

> 核心原则: 先建模块, 旧 Components 类先保留为适配器, 逐个调用点迁移, 最后清理。
> **任一阶段可独立上线、独立回滚。**

### 阶段 0 — 建模块骨架 (不动任何调用点) [预计 0.5 天]
- 新建 `app/Services/Notification/` 全套类
- TelegramChannel/ServerChanChannel 内部调用现有 `Curl::send` + `NotificationLogger`(写 email_log)
- 旧 `Components/Telegram.php`、`ServerChan.php` **完全不动**
- 单测: mock Curl, 验证级别过滤 / enabled / 日志写入
- 产出: `tests/test_notification_unit.php`

### 阶段 1 — 接入 ERROR 调用点 (核心目标达成) [预计 0.5 天]
改造 2 个 error 场景, 让 Telegram 真正生效:
- `AutoJob.php:759` 节点离线 → `->error(...)`
- `AutoCheckNodeTCP.php:155` TCP 阻断 → `->error(...)`
- 验证: 配了 Telegram 的环境现在能收到 error 告警

### 阶段 2 — 接入 WARNING / INFO 调用点 [预计 0.5 天]
- 流量异常 → `->warning(...)` (Telegram 按 error-only 自动不发, 符合预期)
- 工单 5 处 → `->info(...)`
- 节点日报 → `->info(...)`

### 阶段 3 — 纠正返利提现的错误接入 [预计 0.2 天]
- `UserController.php:1183,1292` 当前直接 `Telegram::send()` 发 info
- 改为 `->info(...)`: 走 ServerChan 等低级别渠道; Telegram 不再收 (符合"error-only")
- **[决策点 D2] 这会让"用 Telegram 收提现申请"的用户收不到。是否接受?
  还是保留提现也走 Telegram(放宽 Telegram 到 INFO)?**

### 阶段 4 — 旧 Components 类收尾 [预计 0.2 天]
- `Components/Telegram.php` / `ServerChan.php` 改为**适配器**: 内部转发到 `NotifyService::info()`
  (保底, 任何遗漏调用点仍工作, 且统一走新日志)
- 或直接删除(若调用点已 100% 迁移)

### 阶段 5 — 清理 [预计 0.2 天]
- 删除 PushBear 死配置 (`is_push_bear`/`push_bear_send_key`/`push_bear_qrcode`, 无实现类)
- 更新 `email_log` 表注释补 `3-Telegram`
- 写模块文档 `AI/modules/notification.yaml` (按 AGENTS.md 的 where/why/how/input/output/do/must 规范)

---

## 4. 兼容性 & 破坏性变更

| 项 | 类型 | 说明 |
|---|---|---|
| 配置项 `is_telegram` / `telegram_bot_token` / `telegram_chat_id` | 兼容 | 名字不变, 行为不变 |
| 旧 `Telegram::send()` / `ServerChan::send()` 调用 | 兼容 | 阶段4 转适配器, 不报错 |
| **Telegram 收到的内容** | **行为变更** | 阶段1 后开始收 error; 阶段3 后不再收提现 info |
| `email_log.type` 新增 Telegram 写入(=3) | 兼容 | 代码早就在写, 只是补注释 |

> 阶段3 是唯一需要用户拍板的"行为变更", 见决策点 D2。

---

## 5. 测试计划

`tests/test_notification_unit.php` (APP_ENV=test):
- [ ] Level 过滤: `error()` 时 TelegramChannel.send 被调用; `info()` 时不被调用
- [ ] enabled 开关: `is_telegram=0` 时 TelegramChannel 不投递
- [ ] ServerChan minLevel=INFO: 三种级别都投递
- [ ] NotificationLogger: 成功/失败都写 email_log, type 正确
- [ ] 异常隔离: 某渠道抛异常不影响其他渠道 (try-catch 包裹每个 channel)

---

## 6. 风险与约束

| 风险 | 缓解 |
|---|---|
| Telegram 硬编码 Markdown 易因特殊字符失败 | Channel 内改用 HTML 或转义; 写入失败记 error 日志 |
| 某渠道异常拖垮调用方 | 每个 channel.send try-catch; 单独 Log::error |
| PHP 7.4 约束 | 全程 const/接口/位置参数, 不用 enum/union/match/命名参数 |
| 渐进迁移期间双写日志 | 阶段4 前旧类和新 Service 可能并存, 接受临时重复, 阶段4 收口 |

---

## 7. 待你决策的点 (审核重点)

- **[D1]** 模块路径用 `app/Services/Notification/`? (推荐) 还是 `app/Modules/Notification/`?
- **[D2]** 阶段3: 返利提现(info)当前接 Telegram, 改造后 Telegram 收不到。**接受**(Telegram 严格 error-only)?
  还是放宽 Telegram 到 INFO(连提现一起收)?
- **[D3]** ServerChan 是否纳入统一抽象? (推荐: 纳入, 它是同类管理员告警渠道)
- **[D4]** 流量异常判 WARNING 还是 ERROR? (我判 WARNING, 因非系统故障)
- **[D5]** 阶段4 旧 Components 类: 保留为适配器(保底, 推荐) 还是 直接删除?
- **[D6]** 是否需要"静默期/防刷" (同节点离线 N 分钟内不重复推送)? 本次不做, 列为后续增强。

---

## 8. 落地后的最终形态

```php
// 任何业务点, 一行搞定, 渠道/级别/日志全部自动处理
use App\Services\Notification\NotifyService;

app(NotifyService::class)->error('节点离线', "节点 {$node->name} 心跳超时");
// 后续新增 Bark/钉钉/飞书 → 只加一个 Channel 类 + 注册, 业务点零改动
```

---

## 9. 执行结果 (已完成 2025-07)

### 生产环境核查 (ssh srp)
- `is_server_chan=0` 且 `server_chan_key` 为空 → ServerChan 确认未启用 → 按 D3 取消
- `is_telegram=0` 且 token/chat_id 为空 → Telegram 也未配置 (模块就绪后需填配置才生效)

### 决策落地
| 决策 | 选择 | 落地 |
|---|---|---|
| D1 路径 | `app/Services/Notification/` | ✅ |
| D2 可配置 | 新增 `telegram_min_level` (默认 error, 可 warning/info) | ✅ |
| D3 ServerChan | 生产未启用 → 取消, 统一 Telegram | ✅ 删除类+配置+迁移调用 |
| D4 流量异常 | WARNING | ✅ |
| D5 旧类 | 直接删 | ✅ Telegram.php / ServerChan.php 已删 |
| D6 防刷 | 不做 | ✅ |

### 交付物
- **新增 6 个文件**: `app/Services/Notification/{Level,Notification,NotifyService,NotificationLogger}.php` + `Channels/{ChannelInterface,TelegramChannel}.php`
- **配置**: `config.default.php` 删 ServerChan/PushBear 5 项, 加 `telegram_min_level`
- **便捷函数**: `app/helpers.php` 加 `notify()`
- **迁移 6 个文件 12 个调用点**: UserController / TicketController / AutoJob / AutoCheckNodeTCP / UserTrafficAbnormalAutoWarning / AutoReportNode
- **删除 2 个旧类**: `app/Components/{Telegram,ServerChan}.php`
- **测试**: `tests/test_notification.php` — **19 cases 全过**
- **文档**: `AI/modules/notification.yaml`

### 验证
- ✅ 14 个文件 `php -l` 语法检查通过
- ✅ composer dump-autoload 成功 (5016 classes)
- ✅ grep 确认无活代码引用已删类 (仅剩注释块内历史死代码, 不影响运行)
- ✅ 19 个测试用例全过 (Level 转换 / enabled / minLevel / 级别过滤 / 值对象 / notify helper)

### 待人工跟进 (非代码)
- [ ] 生产 `.config.php` 填入 `is_telegram=1` + `telegram_bot_token` + `telegram_chat_id` 才能真正收到告警
- [ ] (可选) DB `email_log` 表注释补 `3-Telegram` (代码层面已用 const LOG_TYPE 管理)
