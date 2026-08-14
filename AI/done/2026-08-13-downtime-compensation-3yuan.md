when: 2026-08-13T21:40:00
where:
  - app/Console/Commands/SendDowntimeCompensation.php
  - tests/test_downtime_compensation.php
why:
  - 运营需求: 之前的网站停机故障, 给所有受影响用户补偿 3 元到账户余额.
  - 必须满足三条硬约束: (1) 可记录 (写入 user_balance_log); (2) 仅发送一次 (不可重复执行);
    (3) 不影响其他业务系统, 且对已删除用户无副作用.
how:
  - 新增一次性 artisan 命令 downtime:compensation (SendDowntimeCompensation), 不进 schedule,
    仅人工手动执行. 金额常量 AMOUNT_CENTS=300 (balance 字段单位为「分」, 3 元=300 分).
  - 范围: status IN (0,1) AND is_admin=0 AND id>1 (排除系统管理员与已封禁 status=-1).
  - 处理: chunkById(500) 流式分批, 每用户单独事务 + lockForUpdate 行锁读余额,
    increment('balance',300) + 写入 UserBalanceLog (before/after/amount=300/desc=固定批次串).
  - 仅发送一次 (双重保障):
      (a) 永久锁 storage/downtime_compensation.lock —— 整批成功完成后写入,
          再次运行检测到锁存在直接 exit(1) 拒绝;
      (b) user_balance_log.desc 固定批次串作为每用户幂等标记, 写入前查重已补则跳过,
          保证万一中途崩溃 (尚未写锁) 也可安全重跑补齐, 绝不重复发放.
  - 运行期并发锁: flock(storage/downtime_compensation.running.lock) 防两个终端同时跑.
  - 支持 --dry-run (仅统计不写入) 与 --yes (跳过交互确认, 默认需输入 yes).
  - 复用既有 addUserBalanceLog 同构字段; 无新增表/无迁移/无 model 改动.
must:
  - 可记录: 每笔补偿均写 user_balance_log (desc=停机补偿3元 #DOWNTIME_COMP_20250813_3YUAN)
  - 仅一次: .lock 永久锁 (整批完成才写) + desc 幂等查重 (崩溃自愈), 二者缺一不可
  - 对其他系统无影响: 返佣/封禁/下单/credit 均已逐一排查 (返佣不读余额; 加余额不触发任何 observer)
  - 删除用户无影响: User 为硬删除且级联删其日志, 批次只遍历现存 user 行, 已删用户自然排除
  - 金额单位严格为「分」, 常量 300, 不暴露参数防误操作
  - PHP 7.4 兼容 (无 match/命名参数/nullsafe/联合类型等 8+ 语法)
test:
  - tests/test_downtime_compensation.php 16 用例全部通过, 覆盖:
      dry-run 不写入 / 真实运行到账+日志正确 / 二次运行被锁拦截(exit=1) /
      删锁重跑幂等跳过 / 排除管理员 / 自清理还原
  - 测试自清理 (无残留日志/锁/余额改动), 可重复运行
impact:
  - 返佣系统: 无影响 (返佣在下单时按 amount×referral_percent 计算, 不读余额)
  - AutoBanUserNoMoney: 加 3 元可能让部分负余额用户转正不被封, 属补偿应有之意, 无副作用
  - 边界点: UserController 下单返佣条件 balance>=0 可能因此对个别原欠费用户生效, 影响极小且合理
deploy:
  - 命令须在生产服务器执行 (.env 指向生产库); 本地 .env 为 test-npanel 测试库
  - 执行: ssh 到生产机 → cd 项目 → php artisan downtime:compensation --dry-run 先看数量
    → 确认后 php artisan downtime:compensation (按提示输入 yes)
  - 验证: 后台 用户余额变动记录 页面可见 desc=停机补偿3元 的记录; storage/downtime_compensation.lock 已生成
status: done
