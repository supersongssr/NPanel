# NPanel 节点生命周期管理：物理删除与资源池模式深度分析报告

## 1. 背景概述
在分布式节点管理系统中，处理长期无心跳（死亡）节点的方式决定了系统的稳定性、数据库性能以及外部资源（如 Cloudflare DNS 解析记录）的利用率。

本报告对比了 **“物理删除（Delete & Create）”** 与 **“资源池回收（Pool & Reuse）”** 两种核心策略。

---

## 2. 策略对比分析

### 方案 A：物理删除模式 (Delete & Create)
每次发现 32/64 天无心跳节点时，直接从 `ss_node` 表中物理删除该行，并在需要新节点时插入新行。

*   **数据库压力**：**高**。
    - `DELETE` 操作在 MySQL 中并非真正抹除数据，而是标记删除，会导致 B+ 树索引频繁调整。
    - 随后的 `INSERT` 会引发页分裂（Page Split）和随机 I/O，产生大量表空间碎片。
*   **ID 空间**：**碎片化且膨胀**。
    - 主键 ID 会迅速从 100 变成 10,000 再到 1,000,000。
*   **审计风险**：**断裂**。
    - 删除节点行会导致关联的流量日志（Traffic Log）、负载记录变成“孤儿数据”，失去外键约束，导致历史统计数据无法追溯。

### 方案 B：资源池回收模式 (Pool & Reuse) - *推荐方案*
不删除记录，而是将“死亡”节点标志为离线，放入“节点池”。新机器申请 ID 时，通过 `UPDATE` 修改该行数据（协议、IP、归属）进行“转世”。

*   **数据库压力**：**低**。
    - 使用 `UPDATE` 修改已有行，索引结构非常稳定。
    - 数据库文件紧凑，I/O 开销最小。
*   **ID 空间**：**紧凑且有序**。
    - ID 始终维持在活跃节点总数的 1.5 - 2 倍左右。
*   **审计风险**：**连续**。
    - 记录被保留，历史负载趋势可以作为同一“坑位”的参考。

---

## 3. DNS 解析资源的深度优化 (64天清理逻辑)

Cloudflare 免费版通常有 **1000 条解析记录** 的硬上限。

### 当前瓶颈：
如果采用纯回收池，只有在节点被“捡走”的瞬间才会清理旧 DNS。这意味着如果一个节点死了 100 天都没人捡，它的 DNS 记录会白白占用 Cloudflare 配额 100 天。

### 优化方案：三段式生命周期 (可配阈值清理)

> ⚠️ 清理阈值「可自由配置」：在 `config` 表新增一行 `name=dns_expire_days, value=整数天数`
> 即可调整，无需改代码。未配置时默认 `32` 天 (命令行 `--days` 可临时覆盖)。
> `AutoDeleteExpiredDns` 定时任务 (Console\Kernel 每日 04:10) 自动读取该配置。

建议将节点状态分为三个阶段管理，以最大限度节省 DNS 数量：

1.  **活跃期 (0 - 32天)**：
    - 正常心跳。DNS 记录必须保持。
2.  **回收池/冷却期 (32 - 64天)**：
    - 节点被视为“死亡”，进入可分配池。
    - **保留 DNS**：因为原节点主可能有短暂故障，32天后回来还能立刻恢复业务，无需重新解析。
3.  **强制释放期 (> 64天)**：
    - 节点依然留在池中，但执行 **“DNS 强制销毁”**。
    - **操作**：调用 Cloudflare API 删除该 ID 关联的所有 A/AAAA 记录，并将数据库中的 `cf_record_id` 设为 `null`。
    - **效果**：提前释放了宝贵的 DNS 槽位，而不需要等待该 ID 被别人捡走。

---

## 4. 死节点比例硬回收 (AutoReclaimDeadNodes)

资源池回收模式在「临时节点高频增减」场景下会暴露一个缺口：死节点要等满 `dns_expire_days`（默认 32）才进入 clone 回收池，若增减周期 < 32 天，死节点来不及被消费，每次注册被迫 `new SsNode()`，导致节点 **行数与 id 双暴涨**。

为此系统在「池回收」之上叠加了一层 **比例制硬删除安全帽** `AutoReclaimDeadNodes`（`Console\Kernel` 每日 04:20，排在 `autoDeleteExpiredDns` 之后）：

- **死节点定义复用**：`status=0 AND (heartbeat_at < cutoff OR (heartbeat_at IS NULL AND created_at < cutoff)) AND id > 99`，与 DNS 清理 / clone 回收三者共用 `dns_expire_days` 阈值。
- **触发条件（比例 + 绝对下限）**：`dead/total > node_recycle_ratio`（默认 `0.50`）**且** `dead >= node_recycle_min_dead`（默认 `500`）。低于下限视为「无存储压力」直接跳过。
- **配额**：删除 `dead - targetDead` 个最老的死节点（`id ASC`，裁回收池的「死寂端」，对回收零损耗），回归到 `targetDead = floor(ratio * alive / (1 - ratio))`（ratio=0.5 时即 `dead == alive`）。
- **删前先清 CF 远端 DNS**：每条 victim 删除前调 `DnsSyncer::cleanupNodeRecords`，避免留下 Cloudflare 孤儿记录；随后事务级联 13 张表（`ss_node` 本体 + `delNode` 9 张 + `dns_records` + `ss_node_ip` + `ss_node_deny`）。
- **安全闸门**：`status=0` 从源头排除在用节点；保留区 `id<100`（PingController 预留）永不删；强制支持 `--dry-run` 预演 + `withoutOverlapping` 防重叠。

> 调整阈值无需改代码：在 `config.default.php` / `.config.php` 修改 `node_recycle_ratio`、`node_recycle_min_dead`、`dns_expire_days` 即可。设计细节见 `plans/nodes/autoReclaimDeadNodes.md`。

因此当前架构实为 **混合模式**：常态下走池回收（id 紧凑、审计连续），仅在死节点占比失控时才硬裁冗余，二者并不冲突。

---

## 5. 结论与架构建议

### 分析结论：
**“将旧节点放入节点池，下次回收使用”是性能最优、最稳健的方法。** 但为了解决 DNS 资源限制，必须配合**“延迟强制清理”**逻辑。

### 最终架构建议：
1.  **池回收为主 + 比例硬删除为辅（已落地）**：常态走回收池模式（减小数据库压力、id 紧凑、审计连续）；死节点占比失控（`dead/total > node_recycle_ratio` 且 `dead >= node_recycle_min_dead`）时由 `AutoReclaimDeadNodes` 比例硬删除兜底，避免行数/id 无界膨胀（见第 4 节）。纯物理删除仅作为容量安全帽，不取代池回收。
2.  **实施 DNS 强力释放 (阈值可配)**：
    - `Console\Kernel` 每日 04:10 执行 `AutoDeleteExpiredDns`，扫描超过阈值 (`dns_expire_days`, 默认 32) 无心跳的节点。
    - 调用 Cloudflare API 删除这些不活跃节点关联的 A/AAAA 记录，提前释放 Cloudflare 槽位。
    - 这样可以保证 Cloudflare 的 1000 个名额始终只留给“近期活跃”的 500-800 个活跃 ID，让系统可以弹性支撑更大的节点规模。
    - **DNS 模块独立性**：CDN 域名 (`node_domain_pool` 中 `cdn:true + cf_token`) 使用独立 Cloudflare Token
      (与全局 Token 隔离防封号)。删除时按记录根域名解析对应 Token，否则 CDN 域名记录会因鉴权失败而残留。
      (逻辑统一走 `DnsRecordCleanupService::resolveProvider()`，与 `DnsSyncer` 创建/更新路径一致)
3.  **状态对齐**：在 ID 重新分配时，始终执行一遍“先删后建”解析，确保回收过程的数据一致性。
