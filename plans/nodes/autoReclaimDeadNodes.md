# Auto Reclaim Dead Nodes (死节点过期回收删除机制)

## 概述

新增**比例制 + 绝对下限**的死节点硬删除机制,根治"临时节点频繁增减导致节点 id / 行数暴涨"问题。

当前系统**已有**:
- DNS 记录定期清理(`AutoDeleteExpiredDns`,每日 04:10)—— 只删 `dns_records`,**不删 `ss_node` 行**
- 注册热路径的"死节点 → clone 复用"(`NodeApiController::register()`)—— 回收池设计,节点行刻意保留

当前系统**缺失**:
- 节点本体的硬删除机制 —— 死节点行永久留存,临时节点高频增减时行数与 id 无界增长

本计划补齐缺失的一环,且**不破坏现有 DNS 清理与 clone 回收设计**,三者共享同一套"死节点"定义与阈值配置。

### 设计基石:删除安全由 `status=0` 保证

删除安全性的核心不在心跳,而在 **`status=0` 这道闸门**:
- `status` 由 `AutoCheckNodeStatus` / `AutoCheckNodeTCP` 在健康检查失败时**自动翻 0**,也可人工停用
- 只要节点仍在服务(活跃),`status` 必为 1,**永远不会进入删除候选集**
- 因此删除条件锁定 `status=0`,从源头排除"误删在用节点"的可能

心跳阈值(`heartbeat_at < dns_expire_days`)是第二道过滤,确认 `status=0` 不是瞬时抖动而是长期停用。两条件 AND = 双重确认。心跳同步与否无需特别担心——`status=0` 已是充分闸门。

### 第一性原理:简单条件 vs 精确复杂条件

判定标准 = **"删错的代价"**。逐项评估"删错一个死节点"的后果:

| 删错情形 | 后果 | 可恢复性 |
|---------|------|---------|
| 删了本会被回收的节点 | 下次注册改走 `new SsNode()` | ✅ 自愈,代价 = 多 1 个 id |
| 删了某主节点 `node_ids` 里的 id | 该主节点订阅临时少 1 个节点 | ✅ 主节点下次注册自动重建 |
| 删了通知节点 | 通知节点消失 | ⚠️ 需手动重建(但通知节点 status=1,本就不在死池) |

**结论:删错代价低且自愈 → 简单条件足够,精确复杂方案(如 node_ids 拆解保护)是过度设计,本计划不采用。**

---

## where

| 位置 | 说明 |
|------|------|
| `app/Http/Controllers/Api/NodeApiController.php` | 注册热路径 `register()` 与 `applyId()` 的死节点查询(止血优化) |
| `app/Console/Commands/AutoReclaimDeadNodes.php` | **新建**:死节点比例回收定时命令 |
| `app/Console/Kernel.php` | 注册命令 + 每日调度 |
| `config.default.php` / `.config.php` | 新增 `node_recycle_*` 配置项(与 `dns_expire_days` 同源) |
| `tests/` | 新增测试脚本 |

涉及模型:`App\Http\Models\SsNode` 及 `delNode()` 级联的 9 张子表。

---

## why

1. **临时节点高频增减导致 id / 行数无界增长**
   死节点要等满 `dns_expire_days`(默认 30 天)才进入 clone 回收池。若临时节点增减周期 < 30 天,死节点来不及被回收,每次注册被迫 `new SsNode()`,行数与 id 双暴涨。
   实测:本库 id 跨度 `10398~97020`(跨 8.6 万)却仅 282 行,印证历史已反复发生。

2. **比例制优于固定阈值**
   固定阈值(如"超过 1000 死节点才删")无法适配不同规模系统。比例制(死/总 > 50%)自适配:小系统管几十个,大系统管几千个,无需人工调参。

3. **回收池有效需求极小,比例可放心裁剪**
   单次注册最多消费 ~5 个死节点槽位(`slots = IP类型 × 协议数`)。即便 50% 上限,对绝大多数系统仍是回收需求的数十倍冗余,不会饿死回收。

4. **止血优化先行,零风险见效**
   注册热路径 `deadNodes->get()` 当前全量载入死节点(9.6KB/行),filesort + 内存随死节点数线性恶化。加 `LIMIT` + 改排序键可立即把临界点从 ~1000 推到 ~5000+,与删除命令解耦,可独立先行上线。

---

## how

### 阶段一:热路径止血(独立可上线,零风险)

`NodeApiController::register()` 的 `deadNodes` 查询(约 `:493-507`):

```php
// 现状 (全量载入 + filesort):
$deadNodes = SsNode::where(function ($query) use ($cutoff) {
    $query->where("heartbeat_at", "<", $cutoff)
          ->orWhere(function ($q) use ($cutoff) {
              $q->whereNull("heartbeat_at")->where("created_at", "<", $cutoff);
          });
})
    ->where("id", "!=", $nodeId)
    ->where("is_clone", "!=", $nodeId)
    ->orderBy("heartbeat_at", "desc")   // ← filesort 元凶
    ->get();                            // ← 全量载入内存

// 改为 (status=0 过滤 + id DESC + LIMIT):
$deadNodes = SsNode::where("status", 0)            // 新增: 排除 status=1 通知节点
    ->where(function ($query) use ($cutoff) {
        $query->where("heartbeat_at", "<", $cutoff)
              ->orWhere(function ($q) use ($cutoff) {
                  $q->whereNull("heartbeat_at")->where("created_at", "<", $cutoff);
              });
    })
    ->where("id", "!=", $nodeId)
    ->where("is_clone", "!=", $nodeId)
    ->orderBy("id", "desc")                         // 改: 主键有序, 零 filesort, 保留小 id
    ->limit(self::RECYCLE_POOL_LIMIT)              // 新增: 帽住单次内存 (默认 500)
    ->get();
```

`NodeApiController::applyId()` 的死节点查询(约 `:136-153`):

```php
// 现状: ->orderBy("heartbeat_at", "desc")->first();
// 改为:
->orderBy("id", "desc")->first();
```

**排序方案选型(已定)**:

| 选项 | 内容 | 结论 |
|------|------|------|
| A | `id DESC` | 零 filesort,保留小 id,但通知节点仅靠排序"尽量不挑" |
| **B(采用)** | **`status=0` 过滤 + `id DESC` + `LIMIT`** | 过滤从根上排除通知节点,排序仅作二级保险;实测 dead&&status=1=0,加过滤不改变现有行为 |
| C | 保留 `heartbeat_at DESC` + `LIMIT` | 行为变化最小,但不解决通知节点风险,filesort 仍在 |
| D | 去 ORDER BY + `LIMIT` | 极简但回收 id 不可控,破坏订阅 ID 局部性 |

> 第一性原理:回收前 `resetToDefaults()` 把节点 name/v2_*/server/ip **全部清空重写**,被回收节点是白纸,**唯一继承的是数字 id**。因此排序的业务意义几乎为零,真正要解决的只有:(1) 别 filesort;(2) 别踩通知节点。方案 B 用过滤解决(2),用 `id` 主键解决(1)。

### 阶段二:比例回收命令

```php
// app/Console/Commands/AutoReclaimDeadNodes.php
class AutoReclaimDeadNodes extends Command
{
    const DEFAULT_RATIO        = 0.50;  // 死/总 触发比例
    const DEFAULT_MIN_DEAD     = 500;   // 死节点绝对下限, 低于不折腾

    protected $signature = 'autoReclaimDeadNodes
        {--dry-run : 仅预览不删除}
        {--ratio= : 覆盖触发比例, 默认读配置}
        {--min-dead= : 覆盖绝对下限, 默认读配置}';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $cutoff = $this->resolveCutoff();  // 复用 dns_expire_days (Helpers::systemConfig)

        // —— 死节点基础查询 (复用现有 32d 定义) ——
        $deadQuery = SsNode::where('status', 0)
            ->where(function ($q) use ($cutoff) {
                $q->where('heartbeat_at', '<', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('heartbeat_at')->where('created_at', '<', $cutoff);
                  });
            })
            ->where('id', '>', 99);  // 保留区: PingController 预留 id<100

        $total = SsNode::count();
        $dead  = (clone $deadQuery)->count();
        $alive = $total - $dead;     // 不变量: 删死节点不影响 alive

        $ratio  = $this->resolveRatio();
        $minRun = $this->resolveMinDead();

        // —— 判断条件 ——
        if ($dead < $minRun) {
            $this->info("死节点 {$dead} < 下限 {$minRun}, 无存储压力, 跳过");
            return 0;
        }
        if ($dead <= $alive) {  // 等价 dead/total <= 50%
            $this->info("死节点 {$dead} <= 活节点 {$alive}, 占比未超 {$ratio}, 跳过");
            return 0;
        }

        // —— 配额: 删到 dead == alive (恰好回归 50%) ——
        // 推导: 删后 dead_new/(total-D) = alive/(total-D+alive) = alive/(2*alive) = 50%
        $deleteCount = $dead - $alive;

        // —— 按 created_at 升序删最老的 (走 idx_created_at, 零 filesort) ——
        $victims = (clone $deadQuery)
            ->orderBy('created_at', 'asc')
            ->limit($deleteCount)
            ->get(['id', 'name', 'heartbeat_at', 'created_at']);

        foreach ($victims as $node) {
            if ($dryRun) {
                $this->line("  [DRY] del #{$node->id} \"{$node->name}\" hb={$node->heartbeat_at}");
                continue;
            }
            $this->cascadeDelete($node->id);  // 复用 delNode 级联 (见下)
        }
        // 输出 summary + Log::info
    }
}
```

**级联删除 `cascadeDelete($id)`** —— **删前先调 `DnsSyncer::cleanupNodeRecords` 清理 Cloudflare 远端 DNS**(不只删本地行,避免 CF 孤儿记录),再事务级联 13 张表(完整删/留清单见"关联表清理审计"):

```php
protected function cascadeDelete($id, $tag)
{
    // 1. 先清理 Cloudflare 远端 DNS (复用 NodeApiController::safeCleanupNodeDns 同一入口)
    //    DnsSyncer 内部按记录 root_domain 解析对应 cf_token (跨账号域名),
    //    失败会强制删本地并告警, 不抛异常, 不阻断节点删除.
    try {
        $syncer = new DnsSyncer();
        $syncer->cleanupNodeRecords($id, $tag . '.cascadeDelete');
    } catch (\Exception $e) {
        Log::warning("{$tag} DnsSyncer cleanup exception for node#{$id}: " . $e->getMessage());
    }

    // 2. 级联删本地表 (事务包裹, 失败回滚保留节点)
    DB::beginTransaction();
    try {
        SsNode::where('id', $id)->delete();
        SsGroupNode::where('node_id', $id)->delete();
        SsNodeLabel::where('node_id', $id)->delete();
        SsNodeInfo::where('node_id', $id)->delete();
        SsNodeOnlineLog::where('node_id', $id)->delete();
        SsNodeTrafficDaily::where('node_id', $id)->delete();
        SsNodeTrafficHourly::where('node_id', $id)->delete();
        UserTrafficDaily::where('node_id', $id)->delete();
        UserTrafficHourly::where('node_id', $id)->delete();
        UserTrafficLog::where('node_id', $id)->delete();
        DnsRecord::where('node_id', $id)->delete();   // 兜底: DnsSyncer 只清 A/AAAA, 此处清剩余类型 + 残留
        SsNodeIp::where('node_id', $id)->delete();
        DB::table('ss_node_deny')->where('node_id', $id)->delete();  // 节点封禁记录 (无模型, 用 DB facade)
        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("{$tag} cascade 失败 node#{$id}: " . $e->getMessage());
        throw $e;
    }
}
```

### 阶段三:配置与调度

`config.default.php` 新增(与 `dns_expire_days` 同区块):

```php
'node_recycle_ratio'      => '0.50',  // 死节点/总节点 触发比例, 超过则回收
'node_recycle_min_dead'   => '500',   // 死节点绝对下限, 低于此值不触发
```

> **关于热路径 `LIMIT` 上限**:不做成配置项,直接作为 `NodeApiController` 的类常量 `RECYCLE_POOL_LIMIT = 500`。
> 它是**纯性能安全帽**——防止注册时 `deadNodes->get()` 载入过多死节点撑爆内存。
> 注册一次最多消费 ~5 个 clone(`slots = IP类型 × 协议数`),500 已是 100 倍冗余,
> 不属于需要运维调参的业务参数,做成常量更诚实,也减少配置面。

`Kernel.php` 调度(排在 DNS 清理之后,确保先释放 DNS):

```php
$schedule->command('autoReclaimDeadNodes')->dailyAt('04:20')->withoutOverlapping();
```

---

## 关联表清理审计(删 / 留清单)

全库扫描所有含 `node_id` 的表(12 张)+ `ss_node` 本体,逐表判定删 / 留。

### 必须级联删除(13 张)

| 表 | 删除原因 | 现有清理机制 | 删除增益 |
|----|---------|------------|---------|
| `ss_node` | 节点本体 | 无 | 核心 |
| `ss_node_traffic_daily` | 节点日流量 | AutoClearLog 保留 32d | **主要残留源**(实测 3640 条,因 32d ≈ cutoff 30d 存在窗口残留) |
| `ss_node_traffic_hourly` | 节点小时流量 | AutoClearLog 保留 7d | 兜底残留(实测 39 条) |
| `dns_records` | 节点 DNS 记录 | AutoDeleteExpiredDns | **删前先调 DnsSyncer 清 CF 远端**(避免孤儿), 再删本地行; 兜底 DNS 命令失败的残留(实测 7 条) |
| `ss_node_label` | 节点标签关联 | 无 | delNode 已清理 |
| `ss_node_info` | 节点负载信息 | AutoClearLog 保留 30min | 基本已空,兜底 |
| `ss_node_online_log` | 节点在线人数 | AutoClearLog 保留 1h | 基本已空,兜底 |
| `ss_node_ip` | 用户连接 IP | AutoClearLog 保留 3d | 兜底 |
| `ss_group_node` | 分组关联(旧) | 无 | delNode 已清理,当前 0 行 |
| `ss_node_deny` | 节点封禁记录 | 无 | **易遗漏!**(当前 0 行、无模型、无代码引用,但避免未来启用后残留) |
| `user_traffic_log` | 用户流量明细 | AutoClearLog 保留 3d | delNode 已清理,兜底 |
| `user_traffic_daily` | 用户日流量 | AutoClearLog 保留 1 月 | delNode 已清理,兜底 |
| `user_traffic_hourly` | 用户小时流量 | AutoClearLog 保留 10d | delNode 已清理,兜底 |

### 保留(不主动清理)

| 残留位置 | 保留原因 |
|---------|---------|
| 其他存活主节点的 `node_ids` 字符串(可能含被删 id) | `whereIn()` 订阅生成自动跳过缺失 id,**主节点下次注册自动重建**,属自愈性残留;主动清理需解析所有主节点字符串,代价过大不值 |
| `ss_node.is_clone` 中的悬挂指向 | 被删主节点的 clone 若仍存活,其 `is_clone` 指向已删 id;但死 clone 独立命中删除条件会被同批删除,无残留 |

> **结论**:无需保留任何"历史归档"表——本系统所有节点关联表都是**运维瞬时数据**,且 `AutoClearLog` 已按时间清理;删除命令只需补齐 `AutoClearLog` 时间窗外的残留(主要是 `ss_node_traffic_daily` 的 32d 窗口)。

---

## input

**定时命令输入**:
- `--dry-run`:仅预览,不写库
- `--ratio=0.5`:覆盖触发比例(留空读配置 `node_recycle_ratio`)
- `--min-dead=500`:覆盖绝对下限(留空读配置 `node_recycle_min_dead`)

**阈值来源**(复用,不新增):
- `cutoff` 天数 ← `Helpers::systemConfig()['dns_expire_days']`(默认 30),与 `AutoDeleteExpiredDns` / clone 回收完全一致

---

## output

命令输出:
- 当前节点统计:`total / dead / alive / ratio`
- 触发判定结果(跳过原因 或 配额 `deleteCount`)
- 逐节点删除日志(dry-run 列表 / 实际删除)
- summary:扫描数、删除数、失败数

日志(`Log::info`):
- `tag`、`cutoff`、`total`、`dead`、`alive`、`ratio`、`delete_count`、`deleted_ids`、`failed`

---

## do

1. **阶段一止血**:改 `register()` 与 `applyId()` 的死节点查询(加 `status=0`、`id DESC`、`LIMIT`)。在 `NodeApiController` 定义类常量 `RECYCLE_POOL_LIMIT = 500`(纯性能安全帽,不做配置)。
2. **阶段二命令**:新建 `AutoReclaimDeadNodes`,实现比例判定 + 配额计算 + 级联删除。
3. **配置**:在 `config.default.php` 加 3 个 `node_recycle_*` 项。
4. **调度**:`Kernel.php` 注册,每日 04:20,`withoutOverlapping()`。
5. **测试**:见"测试与验证"。

---

## must

1. **PHP 7.4 兼容**:严禁命名参数、`match`、nullsafe、union type、`mixed`、构造器属性提升、`readonly`、`enum` 等 8+ 特性。
2. **复用 `dns_expire_days`**:cutoff 必须读 `dns_expire_days`,与 DNS 清理 / clone 回收三者阈值完全一致,避免窗口错位。
3. **死节点定义不变**:`status=0 AND (heartbeat_at < cutoff OR (heartbeat_at IS NULL AND created_at < cutoff)) AND id > 99`。不引入 `node_ids` 拆解等复杂条件。
4. **必须 `--dry-run`**:dry-run 下不写库,完整预览影响范围。
5. **必须分批**:删除用 `limit($deleteCount)` 单次取,逐条级联(事务包裹);禁止一次性全表扫无上限。
6. **必须 `withoutOverlapping()`**:删除耗时不可控,防重叠。
7. **删除前二次校验**(可选加固):逐条删除前 re-fetch 确认仍为 dead(防命令运行期间节点恢复心跳)。
8. **不动 `id < 100`**:保留区,`PingController::getNewNode()` 依赖。
9. **阶段一独立可上线**:止血改动不依赖阶段二,可先行提交,先行见效。
10. **通知节点保护**:回收查询必须含 `status=0` 过滤(通知节点 status=1,heartbeat 恒 NULL 但被过滤挡在死池外)。

---

## 判定条件速查

### 回收(删除)条件 —— 节点被硬删必须**同时**满足
```sql
status   = 0                                              -- 停用 (健康检查自动翻 0 / 人工)
AND ( heartbeat_at < :cutoff                              -- 心跳超期
      OR (heartbeat_at IS NULL AND created_at < :cutoff) )-- 无心跳看创建时间
AND id > 99                                               -- 保留区
```

### 触发条件 —— 命令是否真正执行删除
```
dead < node_recycle_min_dead(500)    → 跳过 (无存储压力, 不折腾)
dead <= targetDead                   → 跳过 (占比 ≤ ratio)
否则                                  → 删除 (dead - targetDead) 个, 回归 ratio

其中 targetDead = floor(ratio * alive / (1 - ratio))   # alive 为不变量 (删死节点不影响)
     ratio=0.5 时 targetDead = alive   (回归 dead==alive, 恰好 50%)
     ratio=0.3 时 targetDead ≈ 0.43*alive
```

### 回收 vs 删除 的排序方向(相反)
| 操作 | 目标 | 排序 | 索引 |
|------|------|------|------|
| 回收(register 复用) | 复用高 id(回收池"活跃端") | `id DESC` | 主键,零 filesort |
| 删除(命令清冗余) | 清除低 id(回收池"死寂端") | `id ASC` | 主键,零 filesort |

> **为什么删除用 `id ASC` 而非 `created_at ASC`**:回收与删除是**同一根轴(`id`)上的对称操作**。
> 回收总从高 id 端取(`id DESC`),所以高 id 是回收池的"活跃端"(被反复消费/补充);
> 低 id 端是回收从不触及的"死寂端"。删除应从死寂端(`id ASC`)裁剪——删掉回收本来就不会用的节点,
> 对回收能力零损耗。两者同键、反向、都走主键索引,逻辑自洽且零 filesort。
> `created_at ASC` 虽近似 id 顺序但不保证一致,且引入第二根轴增加理解负担。

---

## 测试与验证

### 自动测试覆盖
- dry-run 不写库,不级联删除
- `dead < min_dead` 时跳过(构造 < 500 死节点的 mock)
- `dead <= alive` 时跳过(占比 ≤ 50%)
- `dead > alive` 时删除数 = `dead - alive`,删后 ratio = 50%
- 级联删除覆盖全部 11 张表(9 张 delNode 表 + dns_records + ss_node_ip)
- `id < 100` 永不被删
- `status=1` 节点永不被删(通知节点保护)
- 阈值复用 `dns_expire_days`(改配置后 cutoff 跟随)
- 阶段一:`register()` deadNodes 查询返回行数 ≤ `pool_limit`
- 阶段一:`applyId()` 不再 filesort(EXPLAIN 验证)

### 手工验证
```bash
# 预览 (不改任何数据)
podman exec -e APP_ENV=test php7-npanel php /var/www/NPanel/artisan autoReclaimDeadNodes --dry-run

# 覆盖阈值预览
podman exec -e APP_ENV=test php7-npanel php /var/www/NPanel/artisan autoReclaimDeadNodes --dry-run --ratio=0.3 --min-dead=10

# 确认注册热路径已止血 (EXPLAIN 无 filesort)
podman exec php7-npanel php -r '... EXPLAIN ...'
```

### 跑前自检(可选,确认当前规模无需触发)
当前实测:死节点 81 个 < 下限 500 → **命令上线后首轮即跳过,零删除**,可作为安全上线验证。

---

## 文件变更清单

| 操作 | 文件路径 | 说明 |
|------|----------|------|
| 修改 | `app/Http/Controllers/Api/NodeApiController.php` | `register()` / `applyId()` 死节点查询加 `status=0` + `id DESC` + `LIMIT` |
| 新建 | `app/Console/Commands/AutoReclaimDeadNodes.php` | 比例回收定时命令 |
| 修改 | `app/Console/Kernel.php` | 注册命令,每日 04:20,`withoutOverlapping()` |
| 修改 | `config.default.php` | 新增 `node_recycle_ratio` / `node_recycle_min_dead` |
| 新建 | `tests/test_auto_reclaim_dead_nodes.php` | 覆盖跳过/触发/级联/dry-run/通知节点保护 |

> 新建 PHP 类后执行:`podman exec php7-npanel php /var/www/NPanel/composer.phar dump-autoload`

---

## 风险点

1. **删除不可逆**
   级联删除 13 张表(清单见"关联表清理审计")。`--dry-run` 必须先跑;建议首轮上线时配置 `min_dead=99999`(永不触发)观察日志输出与判定逻辑,确认无误后再调回 500。

2. **回收池被裁瘦的极端情况**
   若系统经历大规模节点下线(死节点暴增到触发删除),删后回收池 = alive。若随后大量新节点注册,回收池可能短期不够 → 改走 `new SsNode()`(自愈,只是多几个 id)。非崩溃性,可接受。

3. **status=0 的瞬时抖动**
   `AutoCheckNodeStatus` / `AutoCheckNodeTCP` 在 TCP 抖动时会临时翻 status=0。但删除要求 **status=0 AND 心跳超 30 天**,瞬时抖动的节点心跳必然新鲜 → 不会被删。双条件纵深防御已覆盖。

4. **阈值窗口一致性**
   cutoff 必须复用 `dns_expire_days`,与 DNS 清理 / clone 回收三处完全一致。若各自用不同天数,会出现"DNS 已删但节点还在回收池"或"节点已删但 DNS 残留"的窗口。本计划强制复用同一配置项消除该风险。

5. **阶段一改动的行为变化**
   `register()` 加 `status=0` 过滤:实测 dead&&status=1 = 0,加过滤不改变现有回收行为,只是显式化保护通知节点。但属热路径改动,上线后需观察注册成功率与 clone 分配是否正常。

---

## 实施顺序

| 步骤 | 内容 | 风险 | 收益 | 可独立提交 |
|-----|------|------|------|-----------|
| ① | 热路径止血(`register`/`applyId` 加 LIMIT + id DESC) | 极低(2-4 行) | 临界点 ~1000 → ~5000+,立即消除内存隐患 | ✅ |
| ② | `AutoReclaimDeadNodes` 命令 + 配置 + 调度 | 低(dry-run 验证) | 根治 id 暴涨,自适配容量 | ✅ |
| ③ | 测试脚本 | — | 回归保护 | ✅ |

建议**先 ① 后 ②**,① 上线观察 1-2 天注册正常后再上 ②。
