# APP_URL 工作机制与使用位置

## URL 生成链路

```
.env (APP_URL) → config/app.php → url() / asset() / URL:: facade
```

所有 URL 生成的源头是 `.env` 文件中的 `APP_URL` 配置项。

### 链路详解

1. `env('APP_URL')` 在 `config/app.php:59` 中被读取并赋值给 `config('app.url')`
2. Laravel 的 `url()` 辅助函数基于 `config('app.url')` 拼接完整 URL
3. `asset()` 辅助函数同样基于该值生成静态资源路径
4. `URL::` Facade 提供了额外的 URL 操作能力（如强制 HTTPS）

---

## 1. `env('APP_URL')` — 配置文件直接引用（2 处）

| 文件 | 行号 | 代码 |
|---|---|---|
| `config/app.php` | 59 | `'url' => env('APP_URL', 'http://localhost')` |
| `config/filesystems.php` | 54 | `'url' => env('APP_URL').'/storage'` |

> 注意：`filesystems.php` 中直接拼接 `env('APP_URL').'/storage'`，若 `APP_URL` 末尾带 `/`，会产生 `//storage` 双斜杠。

---

## 2. `config('app.url')` — 通过配置读取（2 处）

| 文件 | 行号 | 代码 |
|---|---|---|
| `resources/views/vendor/mail/markdown/message.blade.php` | 4 | `@component('mail::header', ['url' => config('app.url')])` |
| `resources/views/vendor/mail/html/message.blade.php` | 4 | `@component('mail::header', ['url' => config('app.url')])` |

用于邮件模板的 header 组件，显示站点 logo 链接。

---

## 3. `url()` 辅助函数 — 视图中大量使用（90+ 处）

主要用途：表单 action、页面链接、AJAX 请求地址。

### 认证模块 (auth/)

| 文件 | 行号 | 用途 |
|---|---|---|
| `auth/login.blade.php` | 21 | 登录表单 action |
| `auth/register.blade.php` | 21 | 注册表单 action |
| `auth/resetPassword.blade.php` | 17 | 重置密码表单 action |
| `auth/activeUser.blade.php` | 19 | 激活用户表单 action |
| `auth/layouts.blade.php` | 46-73 | 语言切换链接（多组） |

### 用户模块 (user/)

| 文件 | 行号 | 用途 |
|---|---|---|
| `user/help.blade.php` | 27, 34 | 文章图片、文章链接 |
| `user/invoices.blade.php` | 69 | 支付状态 AJAX 查询 |
| `user/nodeMonitor.blade.php` | 337 | 节点监控 AJAX 请求 |
| `user/subscribe.blade.php` | 多处 | 订阅相关链接 |

### 支付模块 (payment/)

| 文件 | 行号 | 用途 |
|---|---|---|
| `payment/detail.blade.php` | 73 | 支付完成后跳转 |

### 管理模块 (admin/)

| 文件 | 行号 | 用途 |
|---|---|---|
| `admin/editArticle.blade.php` | 33 | 编辑文章表单 action |
| `admin/articleList.blade.php` | 57 | 文章列表链接 |
| `admin/applyList.blade.php` | 136 | 申请详情弹窗链接 |

### 工单模块 (ticket/)

| 文件 | 行号 | 用途 |
|---|---|---|
| `ticket/replyTicket.blade.php` | 160 | 关闭工单 AJAX |
| `ticket/ticketList.blade.php` | 94 | 跳转到工单回复页 |

---

## 4. `URL::` Facade（2 处）

| 文件 | 行号 | 代码 |
|---|---|---|
| `app/Providers/AppServiceProvider.php` | 20 | `\URL::forceScheme('https')` |
| `config/app.php` | 215 | `'URL' => Illuminate\Support\Facades\URL::class`（Facade 别名注册） |

`URL::forceScheme('https')` 强制所有生成的 URL 使用 HTTPS 协议，在反向代理（如 Nginx）后面时尤为重要。

---

## 5. `asset()` 辅助函数 — 静态资源引用（15+ 处）

基于 `APP_URL` 生成静态资源路径。

### favicon

| 文件 | 行号 |
|---|---|
| `user/layouts.blade.php` | 33 |
| `auth/layouts.blade.php` | 29 |
| `auth/free.blade.php` | 32 |
| `admin/layouts.blade.php` | 33 |

### 其他资源

| 文件 | 行号 | 用途 |
|---|---|---|
| `user/subscribe.blade.php` | 73 | 客户端下载链接 |
| `user/nodeMonitor.blade.php` | 26 | 国旗图标 |
| `user/help.blade.php` | 29 | 无图片时的占位图 |

---

## 汇总

| 模式 | 数量 | 分布 |
|---|---|---|
| `env('APP_URL')` | 2 | config 文件 |
| `config('app.url')` | 2 | 邮件模板 |
| `url()` | 90+ | 全部视图（表单、链接、AJAX） |
| `URL::` | 2 | AppServiceProvider, config alias |
| `asset()` | 15+ | 全部视图（静态资源） |

---

## 注意事项

- 修改 `.env` 中的 `APP_URL` 即可全局切换站点域名，所有 `url()` 和 `asset()` 调用会自动更新
- `config:cache` 后 `env()` 函数不再直接可用，确保只在 config 文件中使用 `env()`，业务代码中使用 `config()`
- `filesystems.php` 中的 `env('APP_URL').'/storage'` 拼接缺少尾部斜杠处理，需确保 `APP_URL` 不以 `/` 结尾
