# 2026-05-09 彻底移除 isForbidden 中间件方案

## What (计划内容)
彻底移除应用中的 `isForbidden` 中间件及其相关的地理位置查询逻辑。这包括取消路由拦截、注销中间件定义以及清理不再使用的代码文件。

## Why (移除原因)
1. **性能损耗严重**：
    - **IPv4**：每次访问都会触发 QQWry 和 IPIP 库的双重解析，涉及大量磁盘 I/O 和二进制文件计算，增加 5ms-20ms 延迟。
    - **IPv6**：通过外部 API (`api.ip.sb`) 查询，延迟高达 100ms-2000ms+，且存在接口宕机导致网站打不开的单点故障风险。
2. **逻辑冗余**：代码中同时查询两个本地库进行校对，在 Web 框架层处理此类逻辑过于沉重。
3. **架构优化**：地区限制（如禁止大陆/海外访问）和防机器人功能更适合在 CDN (如 Cloudflare WAF) 或 Nginx 层实现，不应阻塞业务代码。
4. **既定目标**：`todo.md` 中已明确记录需取消此功能。

## Where (涉及位置)
1. **路由定义**：`routes/web.php` (受影响的路由组)
2. **内核注册**：`app/Http/Kernel.php` (中间件别名)
3. **中间件逻辑**：`app/Http/Middleware/isForbidden.php` (物理文件)
4. **任务清单**：`todo.md` (状态更新)

## How (实施步骤)
1. **修改路由**：在 `routes/web.php` 中，将包含 `isForbidden` 的中间件组改为仅保留 `affiliate` 或其他必要中间件。
2. **注销中间件**：在 `app/Http/Kernel.php` 的 `$routeMiddleware` 数组中删除 `isForbidden` 的注册项。
3. **删除文件**：彻底删除 `app/Http/Middleware/isForbidden.php` 文件。
4. **更新任务**：将 `todo.md` 中关于移除 `isForbidden` 的条目标记为已完成。

## Must (核心要求)
- 必须确保移除后 `/login`, `/register` 等关键入口页面能够正常访问。
- 必须确保移除后不产生 500 系统错误（如因路由找不到中间件导致的报错）。

## Input (输入/前提)
- 当前运行的代码库。
- `app/Http/Middleware/isForbidden.php` 存在的 IP 地理位置查询逻辑。

## Output (输出/结果)
- 访问登录、注册页面时，响应速度大幅提升。
- 系统的 CPU 和内存使用率在并发访问时有所下降。
- 移除不再需要的 IP 库查询代码，代码库更简洁。

## QA (质量保证/测试)
1. **访问测试**：在不同网络环境（IPv4 和 IPv6）下访问 `/login` 页面，验证页面能否秒开且无报错。
2. **日志验证**：观察 `storage/logs/laravel.log`，确认不再产生“识别到 IPv6/IPv4，尝试解析”等相关日志。
3. **配置验证**：确认后台设置的 `is_forbid_china` 等开关虽然还在数据库，但已不再起作用（符合预期）。


🧠 **逻辑对齐 (Logic Sync)**

* **业务目标**：执行性能越级优化。彻底从 Laravel 框架层物理拔除 `isForbidden` 中间件，消除极其昂贵的 IP 归属地双重解析（IPv4 本地 I/O 与 IPv6 外部 API 调用）带来的访问延迟。
* **高危防坑点**：
    1. **路由宕机风险**：如果在 `Kernel.php` 中删除了中间件注册，但忘记在 `routes/web.php` 的某个隐蔽路由组中将其移除，Laravel 容器将直接抛出致命错误，导致整站瘫痪。
    2. **连带误删**：`routes/web.php` 中 `isForbidden` 通常与 `affiliate` 绑定在同一个数组里，操作时绝不能破坏其他正常的业务中间件。

信息绝对完备，架构减负方向完全正确（应用层就该做应用层的事，WAF 拦截丢给 Cloudflare）。指令编译器已生成终极产物，请下发给 @agent 执行：

---

### 🧾 指令包 (Prompt for @agent)

#### 1. 🎯 核心目标 (What)  
彻底移除系统中的 `isForbidden` 中间件及其附属的 IP 地理位置查询逻辑。执行路由解绑、内核注销、物理文件删除及任务清单更新。

#### 2. 🧐 原因 (Why) 
该中间件导致了极严重的性能损耗：IPv4 需要读取双重本地库产生高昂磁盘 I/O，IPv6 依赖外部 API (`api.ip.sb`) 导致百毫秒甚至秒级的网络阻塞和单点故障风险。此类 WAF 拦截逻辑应由上游的 CDN 或 Nginx 承担，从 Web 框架层剥离能够大幅提升并发承载力并降低延迟。

#### 3. 📍 操作范围 (Where)  
* **必须修改的文件：** * `routes/web.php` (路由定义)
    * `app/Http/Kernel.php` (内核注册)
    * `todo.md` (项目任务清单)
* **必须删除的文件：** * `app/Http/Middleware/isForbidden.php`
* **严禁触碰的模块：** 数据库中关于地区屏蔽的配置项（如 `is_forbid_china`，让其静默留存即可，勿动数据库 Schema）；路由组中的 `affiliate` 等其他业务中间件。

#### 4. ⚙️ 核心逻辑流 (How)
1. **步骤 1：解除路由绑定**
    * 全局扫描 `routes/web.php` 文件，寻找所有调用了 `isForbidden` 的路由组（例如：`Route::group(['middleware' => ['isForbidden', 'affiliate']]...)`）。
    * 将其从数组中精准摘除，仅保留其余必须的中间件（如上例将变为 `['middleware' => ['affiliate']]`）。如果某个组只有这一个中间件，则整体移除 `middleware` 键。
2. **步骤 2：注销内核注册**
    * 打开 `app/Http/Kernel.php`。
    * 定位到 `$routeMiddleware` 数组。
    * 找到键为 `'isForbidden'` 的那行配置（如 `'isForbidden' => \App\Http\Middleware\isForbidden::class,`），将其彻底删除。
3. **步骤 3：物理删除阻断文件**
    * 直接删除文件：`app/Http/Middleware/isForbidden.php`，清理其内部所有针对 IPv4/IPv6 的解析逻辑。
4. **步骤 4：更新 Todo 状态**
    * 打开根目录的 `todo.md`。
    * 找到要求“取消 isForbidden / 移除地理位置验证”相关任务条目，将其标记为已完成（如打上 `[x]` 或移动到完成列表）。

#### 5. 📏 绝对约束 (Must & Must Not)  
* 🚫 **禁止**：遗漏 `routes/web.php` 中的任何一处 `isForbidden` 调用。这会触发 `Target class [isForbidden] does not exist` 致命错误。
* 🚫 **禁止**：修改或删除项目中原有的 IP 库文件（如 `qqwry.dat` 或 IPIP 库），仅需删除中间件调用即可，防连带破坏其他潜在依赖。

#### 6. 🧪 强制 QA 验收 (QA Assertions) 
*(Agent 必须完成以下验证才能宣告任务结束)*
* [ ] **路由存活断言**：运行 `php artisan route:list` 命令不报错，或使用浏览器/cURL 访问 `/login` 页面，断言页面能够正常输出 HTML，绝对未报 HTTP 500 系统错误。
* [ ] **日志静默断言**：请求任意无需登录的页面后，查看 `storage/logs/laravel.log`。断言：日志中不再出现“识别到 IPv6/IPv4，尝试解析”或与外部 IP API 报错相关的 debug/error 信息。
* [ ] **响应提速断言**：开启浏览器 DevTools，勾选禁用缓存并请求 `/login`，断言 TTFB (首字节时间) 相比之前有明显的下降（去除了耗时的 I/O 和网络请求）。
