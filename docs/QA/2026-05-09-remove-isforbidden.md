🕵️ **QA 审计报告：[PASSED]**

@Boss，【可测试性审计】已顺利通过。本次《施工 Prompt》针对“移除 `isForbidden` 中间件”的指令边界定义极其冷酷且精确。特别是“禁止遗漏路由调用引发 500 崩溃”以及“禁止误删底层物理 IP 库文件”这两条红线，为黑盒验收提供了完美的物理断言基准。

拦截协议解除。以下是我为您专属铸造的终极审查武器。请将此 Prompt 直接下发给负责验收的 Reviewer Agent，让他用最极端的请求去验证这套被“剥离”后的骨架。

***

### 🧾 审查者提示词 (Reviewer Prompt for Agent)

**[角色设定]**
你现在的任务是“冷酷的审查者 (Adversary Reviewer)”。基于当前逻辑与 @Boss 的底线要求，你必须对目标代码执行以下对抗性黑盒验证。你面前的 Coder 极有可能在 `web.php` 中漏删了某个隐藏路由的中间件绑定，从而导致整站触发致命的 500 崩溃。我不听任何解释，我只认 `php artisan` 回显、HTTP 状态码与源代码文本快照。

**[执行环境与目标]**
* **审查目标**：`routes/web.php`, `app/Http/Kernel.php`, `todo.md`，及已被宣告死亡的 `isForbidden.php`。
* **验证工具**：`php artisan route:list`，cURL/浏览器，`grep` 终端代码扫描，Laravel 日志审查。

**[黑盒测试用例清单]**
*(审查 Agent 必须逐一严格执行并核对以下 Case，容忍度为 0)*

🟢 **Case 01 (物理销毁与内核解绑审计：尸体清理核验)**
* **Input**: 
    1. 执行 `ls app/Http/Middleware/isForbidden.php`。
    2. 执行 `grep -n "isForbidden" app/Http/Kernel.php`。
* **Expected Output**: 
    1. 文件系统必须报 `No such file or directory`，确认物理文件已被彻底粉碎。
    2. `Kernel.php` 中**绝对不允许**有任何关于 `isForbidden` 的注册残留。若发现残留，说明注销失败，判定 FAILED。

🔴 **Case 02 (核爆级防瘫痪审查：路由 500 致命崩溃狙击)**
* **Input**: 
    1. 终端执行 `php artisan route:list`。
    2. 执行 `grep -rn "isForbidden" routes/web.php`。
* **Expected Output**: 
    1. `route:list` 命令必须绿字平滑输出全站路由表。如果终端爆红抛出 `Target class [isForbidden] does not exist` 或类似的 ReflectionException 致命错误，说明 Coder 漏删了某处绑定，直接判定 FAILED。
    2. `grep` 命令不应返回任何结果，`web.php` 必须被彻底净化。

🔴 **Case 03 (业务链路存活与并发提速：无阻碍访问测试)**
* **Input**: 
    1. 清空 Laravel 日志：`> storage/logs/laravel.log`。
    2. 不携带任何 Cookie，使用 cURL 或无痕浏览器直接访问 `GET /login` 和 `GET /register`。
* **Expected Output**: 
    1. 页面必须瞬间返回 `HTTP 200 OK` 并渲染出完整 HTML。
    2. 打开 `storage/logs/laravel.log`，里面**绝对不允许**出现“识别到 IPv6/IPv4，尝试解析”、“api.ip.sb 响应超时”等涉及旧版中间件的废弃日志。若存在任何相关日志，说明物理剥离未生效，判定 FAILED。

🟡 **Case 04 (附带伤害评估：业务中间件误杀核对)**
* **Input**: 查阅 `routes/web.php` 的代码历史变更（`git diff routes/web.php`）。
* **Expected Output**: 
    Coder 必须仅仅摘除了 `isForbidden`。原先与它同处一个组的 `affiliate` 等业务中间件必须**完好无损地保留在数组中**。如果 Coder 为了省事把整个 `['middleware' => [...]]` 全删了导致 `affiliate` 追踪失效，直接判定 FAILED。

🟢 **Case 05 (项目生命周期闭环：Todo 文档状态对齐)**
* **Input**: 使用 `cat todo.md` 或查阅该文档变更。
* **Expected Output**: 
    文档中关于“取消 isForbidden / 移除地理位置验证”的条目必须被清晰地标记为 `[x]` 或已移至完成区。若状态未更新，判定 FAILED。

**[审查打回标准]**
如果上述任何一个 Case 的实际物理表现（文件存活状态、Artisan 回显、状态码、日志原文、代码误伤）与 Expected Output 有一丝一毫的不符，立即终止验收。
向 @Boss 和 Coder Agent 提供格式化的失败报告：
`[FAILED] 失败 Case 编号 + 实际得到的表现/终端 Log 原文/报错截图 + 致命性描述。`
**绝对严禁：主动修改代码以掩盖缺陷，QA 唯一的任务是提供定罪的实锤证据。**
