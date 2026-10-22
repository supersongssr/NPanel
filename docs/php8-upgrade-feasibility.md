# NPanel 升级 PHP 8 可行性分析报告

> 分析日期: 2026-10-22 | 分析方式: 全量实测(非纸面评估)
> **状态更新 2026-10-22: 升级已落地。** 运行时 PHP 8.5.12(php8-npanel 容器, 端口 9006),
> 补丁固化于 `patches/` 目录(composer post-autoload-dump 钩子自动重应用),
> 支付组件已修复。
>
> **2026-10-22 已正式上线**: nginx `fastcgi_pass` 9001→9006, `/etc/crontab` 调度器切换至 php8-npanel。
> 回滚: nginx conf 备份 `test-npanel.freessr.bid.conf.bak-20261022-php7`, crontab 备份 `/etc/crontab.bak-20261022`。
>
> **pm.max_requests 实测(ab, c=10)**: mr=1 吞吐掉 50-68%(轻API 67→210 rps, 重页 50→102 rps),
> 且 mr=1 时也仅比 php7 现网快一倍; 最终取 **mr=500**(纵深防御窗口限制, 性能无感损失,
> 复测 246 rps / p95 47ms, 是 php7 现网的 4-8 倍)。

## 1. 漏洞背景 (CVE-2026-109618 / CVE-2026-109619)

即 Wiz 披露的 **PHP2Shell** 系列漏洞(CVSS 9.8/9.2 CRITICAL),核心 PHP 引擎内存安全问题,可被远程未认证利用导致 RCE 与内存泄露,**与应用层代码无关**。

| 项目 | 说明 |
|---|---|
| 受影响版本 | `8.2.* < 8.2.35`、`8.3.* < 8.3.36`、`8.4.* < 8.4.27`、`8.5.* < 8.5.12` |
| **PHP 7.4(本项目现状)** | **同样受影响**(Wiz: 回溯至 5.4 的所有版本均受影响),但 7.4 已 EOL,**永远不会收到补丁** |
| 修复版本 | 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12(2026-10-21 发布) |

**结论: 唯一修复路径 = 升级到已打补丁的维护分支。** 宿主机已有 `localhost/php8-spanel:8.2.35` 镜像(PHP 8.2.35, 即修复版),本次实测直接复用。

## 2. 总体结论: ✅ 可行(需 2 个 vendor 补丁 + 3 处业务代码修复)

在 PHP 8.2.35 容器中挂载项目副本实测:

| 验证项 | PHP 7.4 基线 | PHP 8.2.35 实测 | 结果 |
|---|---|---|---|
| `artisan route:list` 全量路由加载 | ✅ | ✅ | 一致 |
| 业务测试脚本 16 个(clash/singbox/订阅/邮件/节点API/通知/购买限制/DNS等) | 14 过 2 预存在失败 | **完全相同** | **零回归** |
| 节点注册+心跳 API 测试(真实 DB 读写) | 14 passed / 0 failed | 14 passed / 0 failed | 一致 |
| HTTP `/` → 302 登录跳转 | 302, 7942B | 302, 7942B | 字节级一致 |
| HTTP `/login` Blade 完整渲染 | 200, 7942B | 200, 7942B | 字节级一致 |
| JSON API `POST /api/simple_api_tools` | `{"err":"token-empty"}` | `{"err":"token-empty"}` | 一致 |

差异仅 3 处且全部无害: PHP 8.2 错误消息更详细(preg/reflection 带上下文)、自增 ID 动态值不同。
预存在的失败(`test_singbox_edge_cases`、`test_clash_special_chars`、`test_subscription_formats` 的 Clash 生成)在 7.4 下同样失败,与升级无关。

## 3. 必要修改清单

### 3.1 vendor 补丁 ×2(补丁文件已固化于 `patches/`, 经 composer post-autoload-dump 钩子自动重应用)

| # | 文件 | 原因 | 说明 |
|---|---|---|---|
| 1 | `laravel/framework` 5.6.39 `Bootstrap/HandleExceptions.php` | L5.6 `error_reporting(-1)` 且把 E_DEPRECATED 也抛成 ErrorException → PHP 8.2 下启动即静默崩溃(exit 255) | handleError 忽略 E_DEPRECATED/E_USER_DEPRECATED(与 Laravel 9+ 官方行为一致) |
| 2 | `nesbot/carbon` 1.25.0 `Carbon/Carbon.php` | PHP 8.2 起 `DateTime::getLastErrors()` 无错误时返回 `false`(7.4 返回数组) → `setLastErrors(array $x)` TypeError 致命错误 | 归一化 false 为 PHP7 形状数组 |

⚠️ vendor 补丁会被 `composer install` 覆盖,落地时需用 `cweagans/composer-patches` 插件或 fork 包固化。

### 3.2 业务代码修复 ×2 文件(支付回调链路, PHP 8 下 Fatal)

| 文件 | 问题 | 修法 |
|---|---|---|
| `app/Components/AlipayNotify.php` (L176, L184, L225) | `each()` 已移除 + `get_magic_quotes_gpc()` 已移除 | `while (list($k,$v) = each($a))` → `foreach ($a as $k => $v)`;`get_magic_quotes_gpc()` → `false` 常量 |
| `app/Components/AlipaySubmit.php` (L114, L152, L160, L177, L185, L245) | 同上 | 同上 |

> 该调用链在 `routes/api.php` 支付回调路由 (Yzy/Trimepay/Alipay/F2fpay) 上,生产收到回调即 500,必须修复。

## 4. 残留风险(本次未覆盖)

- **phpspreadsheet 1.6.0**(后台导出)、**swiftmailer 6.2**(发信)、**guzzle 6.3.3**(出站 HTTP)运行路径未实测,建议上线后在预发环境补测
- **PHPUnit 7.5.8 无法在 PHP 8 上运行**(仅影响 `tests/Feature` 少量 PHPUnit 用例;项目主测试为独立脚本,已验证可跑)
- Laravel 5.6 / Symfony 4.2 / PHPUnit 7 均已 EOL,后续 CVE 只能靠 vendor 补丁维持 —— **长期应规划框架升级**(Laravel 10+,原生支持 PHP 8.2+)

## 5. 镜像注意事项

- `php8-spanel:8.2.35` 的 `disable_functions` 比 php7 镜像更严格(额外禁 `exec,shell_exec,curl_multi_exec,parse_ini_file,show_source`),已扫描确认 NPanel 业务代码不依赖这些函数,可直接复用该镜像策略
- `artisan serve` 在两个镜像下均不可用(passthru 被禁),与升级无关

## 6. 建议迁移路径

1. **短期(可立即执行)**: 应用 §3 全部修改 → 基于 `php8-spanel:8.2.35` 构建 `php8-npanel` 镜像 → 预发验证 §4 风险点 → 切换 FPM 上游
2. **长期**: 升级 Laravel 5.6 → 10+(原生 PHP 8.2+),同时解除全部 vendor 补丁债务

## 附录: 实测环境

- 测试副本: `/tmp/npanel-php8`(原目录未改动), 容器 `php8-npanel-test`(已清理)
- 容器网络接入 `main` 网络以访问 mysql/redis
- 测试命令模式: `podman exec -e APP_ENV=test <容器> php tests/test_xxx.php`
