when: 2026-07-26T22:00:00
where:
  - app/Services/Notification/Notification.php
  - app/Services/Notification/NotifyService.php
  - app/Http/Controllers/UserController.php
  - tests/test_notification.php
  - AI/modules/notification.yaml
why:
  - 用户需求: /referral 页面用户申请现金提现 (邀请返利 / 消费返利) 后, 后台 /admin/applyList
    需人工审核, 此时必须发 Telegram 通知管理员及时处理.
  - 原代码两处提现入口 (ExtractAffMoney / ExtractRefMoney) 已调 NotifyService::info(),
    但 Telegram 配置 min_level=error, INFO(200)<ERROR(400) 被过滤, 提现通知永远发不出.
    (notification.yaml 原设计有意 info→Telegram 不收, 与本需求冲突)
how:
  - 新增强制投递 (force) 机制: NotifyService::notify() 增加 $force 参数, true 时跳过 minLevel
    过滤 (仍受 enabled() 约束, 凭证缺失不发); Notification 值对象增加 $force / isForce().
  - NotifyService 新增 broadcast($title,$content): 强制送达便捷入口, 级别记为 INFO (不污染 Level).
  - info()/warning()/error() 透传 $force 参数, 保持 API 完整 (默认 false, 行为不变).
  - UserController ExtractAffMoney / ExtractRefMoney 的 ->info() 改为 ->broadcast(),
    注释更新为"强制送达 (无视 min_level 过滤)".
  - 不改 .config.php (用户手工配置, 代码不写); 不污染 error 语义; 其他 info/warning 过滤策略不变.
must:
  - broadcast/force 仅绕过 minLevel, 仍必须有 enabled()=true 且凭证齐全才发 (不发空 token 请求)
  - 强制投递级别仍记 INFO, 不新增 Level 常量, 不影响 error(故障)/warning(关注) 语义
  - 单渠道异常不影响主业务 (提现申请主流程在通知之前已完成, 异常被 try-catch 吞掉仅记 Log)
  - PHP 7.4 兼容 (手动声明属性 + 位置参数, 无 8+ 语法)
test:
  - tests/test_notification.php 新增 [7] 强制投递 7 用例 (broadcast 绕过 ERROR 过滤 / isForce /
    级别仍 INFO / 普通 info 仍被过滤回归 / force 值对象构造); 修正 [2] 凭证已配置断言
  - 全套 32 用例通过
  - 端到端真实验证: broadcast() 投递返回 1, Telegram Bot API ok=true, email_log type=3 status=1
status: done
