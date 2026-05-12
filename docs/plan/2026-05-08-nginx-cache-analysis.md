# Nginx 缓存未登录页面 — 实施方案（方案 A）

> 日期：2026-05-08
> 方案：A（保留 CSRF + POST /token 异步刷新）
> 目标：所有无需登录的 GET HTML 页面在 Nginx 缓存 1 秒

---

## 原则

- GET 请求返回 HTML 的 = 页面，需要缓存
- 需要登录才能访问的 HTML 页面 = 已有验证方案，不需要缓存
- 通过 token 返回 JSON 的 = 不需要缓存
- POST 请求 = 天然不缓存

---

## 一、缓存页面清单

### 可直接缓存的页面（零改动，无风险）

| 页面 | 说明 |
|------|------|
| `GET /free` | 无表单、无 CSRF、无 Flash、无状态变更 |

### 需代码改造后缓存的页面

| 页面 | 存在的问题 |
|------|-----------|
| `GET /login` | CSRF token 交叉 + Session Cookie 丢失 + Flash 消息丢失 |
| `GET /register` | CSRF + Session + Flash + register_token/aff 冻结 |
| `GET /resetPassword` | CSRF + Session + Flash |
| `GET /activeUser` | CSRF + Session + Flash |
| `GET /reActiveUser` | CSRF + Session + Flash |
| `GET /reset/{token}` | CSRF + Session（DB 写入 1 秒内可忽略） |

### 绝对不能缓存的页面

| 页面 | 原因 |
|------|------|
| `GET /active/{token}` | GET 请求激活用户账号，缓存会导致激活操作被跳过 |
| `GET /s/{code}` | 频率限制和日志记录依赖每次请求都到达 PHP |

---

## 二、核心问题与解决思路

### 问题 1：CSRF Token 交叉（致命）

**what**：缓存的 HTML 中嵌入了用户 A 的 CSRF token，用户 B 拿到后提交表单 → 419 Page Expired。

**why**：CSRF token 绑定 session，不同用户的 token 不同。Nginx 缓存的是完整 HTML，token 被冻结。

**how**：页面加载后 JS 异步 `POST /token` 获取当前用户自己的 token，替换 DOM 中的旧值。

**must**：
- `/token` 端点必须排除 CSRF 校验（`VerifyCsrfToken.$except`）
- `/token` 必须用 POST 方法（Nginx 天然不缓存 POST）
- 注册页 JS 中硬编码的 `{{csrf_token()}}` 必须改为从 DOM 读取

**input**：浏览器发送 `POST /token`（带 session cookie 或无 cookie）

**output**：`{ "csrf_token": "xxx", "register_token": "yyy", "register_aff": "zzz" }`

### 问题 2：Session Cookie 丢失（致命）

**what**：`fastcgi_hide_header Set-Cookie` 导致缓存响应不含 Set-Cookie，新访客不收到 session cookie。

**why**：Nginx 缓存 HTML 时把 Set-Cookie 头隐藏了。

**how**：`POST /token` 走完整 PHP 流程，`StartSession` 中间件自动创建 session 并返回 Set-Cookie。页面加载时 JS 先调用 `/token`，用户填表前 session 已建立。

**must**：`/token` 端点必须在 `isForbidden` + `affiliate` 中间件组内，确保走完整 `web` 中间件栈。

**input**：新访客无 cookie 发送 `POST /token`

**output**：JSON + `Set-Cookie: ssrpanel_session=...` 响应头

### 问题 3：Flash 消息丢失 → 表单改为 AJAX 提交

**what**：POST 验证失败后 redirect 回 GET 页面，如果命中缓存则看不到错误提示和表单回填。同时要求 **所有 GET HTML 必须缓存**，不能通过 URL 参数绕过缓存。

**why**：Laravel 的 Flash 消息（`$errors`、`Session::get('successMsg')`、`Request::old()`）存在 session 中，需要 PHP 动态渲染到 HTML。但缓存的 HTML 是"干净"版本，不含这些值。

**how**：将所有表单从**传统 form submit + redirect** 改为 **AJAX submit + JSON response**。GET 页面永远是缓存版本，错误提示由 JS 从 JSON 中读取并动态插入 DOM。

**before**：
```
用户点击提交 → form POST → PHP 验证失败 → 302 redirect → GET /login（带Flash）→ PHP 渲染
```

**after**：
```
用户点击提交 → JS 拦截 → AJAX POST → PHP 返回 JSON → JS 显示错误
GET /login 永远是缓存版本，不走 PHP
```

**must**：
- 所有 5 个表单页面（login、register、resetPassword、activeUser、reActiveUser）的 POST 必须改为返回 JSON
- 所有 Blade 模板中的 `$errors`、`Session::get()`、`Request::old()` 块可以保留（缓存的 HTML 中这些为空，不影响），错误由 JS 动态插入
- 成功响应也返回 JSON，由 JS 执行 `window.location.href = redirect_url`

**input**：JS 发送 AJAX POST（序列化表单数据）

**output**：
- 失败：`{ "status": "fail", "message": "密码错误" }`（HTTP 200）
- 成功：`{ "status": "success", "message": "登录成功", "redirect": "/" }`（HTTP 200）

### 问题 4：语言锁定（低，不处理）

**what**：缓存 1 秒内所有用户看到同一语言。

**why**：`app()->getLocale()` 来自 session，缓存后被冻结。

**how**：不处理。1 秒窗口内出现不同语言用户的概率极低，影响可忽略。

### 问题 5：register_token / register_aff 冻结（中高）

**what**：注册页的 `register_token`（防重复提交）和 `register_aff`（推广追踪）来自 session，缓存后被冻结。

**why**：这两个值嵌入在隐藏字段中，缓存后所有用户拿到同一值。

**how**：`POST /token` 端点同时返回这两个值，JS 一并替换 DOM。

**must**：必须在用户填完注册表单前完成替换

**input**：`POST /token`

**output**：`register_token` 和 `register_aff` 写入对应隐藏字段

---

## 三、`POST /token` 端点服务端开销

**无数据库查询，仅 1-3 次 Redis 操作，约 15-25ms**。

| 步骤 | 操作 | DB/Redis | 耗时 |
|------|------|----------|------|
| Laravel 启动 | 框架加载 | 无 | ~5ms |
| StartSession | Redis 读写 session | Redis 1-3 次 | ~2ms |
| isForbidden | IP 库文件查询 | 无（本地文件） | ~5-10ms |
| token() 方法 | 生成/读取 token | 可能 Redis 1 次 | ~1ms |
| 返回 JSON | 构造响应 | 无 | <1ms |
| **合计** | | **0 次 MySQL** | **~15-25ms** |

> 对比：正常 GET /login 不缓存时 ~100-300ms（含 systemConfig 多次 DB 查询 + 模板渲染）。

---

## 四、实施步骤

### Step 1：Nginx 全局缓存配置

**where**：`/etc/nginx/nginx.conf`

**what**：添加 `fastcgi_cache_path` 指令。

**how**：在 `http { }` 块内添加：

```nginx
fastcgi_cache_path /var/cache/nginx/npanel
    levels=1:2
    keys_zone=NPCACHE:10m
    max_size=50m
    inactive=10m
    use_temp_path=off;
```

**must**：先创建缓存目录并设置权限：
```bash
mkdir -p /var/cache/nginx/npanel
chown www-data:www-data /var/cache/nginx/npanel
```

---

### Step 2：Nginx vhost 缓存配置

**where**：`/etc/nginx/conf.d/test-npanel.freessr.bid.conf`

**what**：添加缓存 map 规则和 fastcgi_cache 指令。

**how**：替换整个配置为：

```nginx
# === 缓存判断规则 ===
map $request_uri $skip_cache {
    default 0;                             # 默认缓存所有 GET 页面
    ~^/active/     1;                      # /active/{token} 不缓存（GET 修改数据库）
    ~^/s/          1;                      # /s/{code} 不缓存（频率限制+日志）
    # POST /token 不需要排除：Nginx 天然不缓存 POST
    # 表单 POST 改为 AJAX + JSON，不再 redirect 回 GET，无需任何排除规则
}

# HTTP + HTTPS 合并配置
server {
    listen 80;
    listen 443 ssl http2;
    server_name test-npanel.freessr.bid test-srp-ssn.freessr.bid www-test-npanel.freessr.bid rss-test-npanel.freessr.bid;

    # 证书路径
    ssl_certificate     /etc/ssl/freessr.bid.pem;
    ssl_certificate_key /etc/ssl/freessr.bid.key;

    # 优化 SSL 安全设置
    ssl_session_timeout 1d;
    ssl_session_cache shared:MozSSL:10m;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # 网站根目录
    root /var/www/test-npanel.freessr.bid/public;
    index index.php index.html;

    # URL 重写
    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    # 处理 PHP — 含缓存配置
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass 127.0.0.1:9001;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

        # 缓存配置
        fastcgi_cache NPCACHE;
        fastcgi_cache_key "$scheme$host$request_uri";
        fastcgi_cache_valid 200 1s;
        fastcgi_cache_bypass $skip_cache;
        fastcgi_no_cache $skip_cache;
        fastcgi_ignore_headers Set-Cookie Cache-Control;
        fastcgi_hide_header Set-Cookie;
        add_header X-Cache-Status $upstream_cache_status;
    }

    # 安全限制
    location ~ /\.(ht|git|env) {
        deny all;
    }
}
```

**must**：修改后 `nginx -t` 验证配置语法。

**output**：`X-Cache-Status: HIT` / `MISS` / `BYPASS` 响应头。

---

### Step 3：新增 `POST /token` 路由

**where**：`routes/web.php` line 5，在 `Route::group` 内第一条

**what**：新增 CSRF token 刷新端点。

**why**：缓存页面的 CSRF token 是旧值，需要 JS 异步获取当前用户自己的 token。

**how**：

```php
Route::group(['middleware' => ['isForbidden', 'affiliate']], function () {
    Route::post('token', 'AuthController@token'); // ← 新增这一行
    // ... 其余路由不变 ...
});
```

**must**：
- 必须用 `post`（不是 `get`），POST 天然不被 Nginx 缓存
- 必须在 `isForbidden` + `affiliate` 中间件组内，确保走完整 `web` 中间件栈

**input**：`POST /token`，无请求体，带 cookie 或无 cookie

**output**：`{ "csrf_token": "xxx", "register_token": "yyy|null", "register_aff": "zzz|null" }` + `Set-Cookie` 头（如果是新访客）

---

### Step 4：新增 `token()` 控制器方法

**where**：`app/Http/Controllers/AuthController.php`，新增方法

**what**：返回当前 session 的 CSRF token、register_token、register_aff。

**how**：

```php
/**
 * Nginx 缓存后 CSRF token 刷新端点
 * 被缓存页面的 JS 异步调用，返回当前用户自己的 token
 */
public function token()
{
    return response()->json([
        'csrf_token'     => csrf_token(),
        'register_token' => Session::get('register_token'),
        'register_aff'   => Session::get('register_aff'),
    ]);
}
```

**input**：无

**output**：JSON `{ csrf_token, register_token, register_aff }`

**must**：无数据库查询，无模板渲染，仅 Redis session 读写，保证极速（~15-25ms）

---

### Step 5：排除 `/token` 的 CSRF 校验

**where**：`app/Http/Middleware/VerifyCsrfToken.php`

**what**：在 `$except` 数组中排除 `token` 路由。

**why**：页面 HTML 是缓存的旧版本，CSRF token 是别人的。JS 调用 `POST /token` 时还没拿到新 token，鸡生蛋问题。`/token` 只返回 token 不执行写操作，安全风险极低。

**how**：

```php
protected $except = [
    "payment/*",
    "checkIn",
    "token",          // ← 新增这一行
];
```

---

### Step 6：布局模板注入 CSRF 刷新 JS

**where**：`resources/views/auth/layouts.blade.php`，在 `@yield('script')` (line 89) 之后、`<!-- 统计 -->` (line 101) 之前

**what**：注入 JS，页面加载后异步获取 CSRF token 并替换 DOM 中的旧值。

**why**：缓存的 HTML 中 CSRF token、register_token、register_aff 都是别人的值，需要替换为当前用户自己的。

**how**：在 `@yield('script')` 后插入：

```blade
    {{-- Nginx 缓存后动态刷新 session 相关 token --}}
    <script>
    (function(){
        fetch('{{url("token")}}', {method:'POST', credentials:'same-origin'})
            .then(function(r){ return r.json() })
            .then(function(d){
                if(d.csrf_token){
                    document.querySelectorAll('input[name="_token"]').forEach(function(el){
                        el.value = d.csrf_token;
                    });
                }
                var el;
                el = document.querySelector('input[name="register_token"]');
                if(el && d.register_token) el.value = d.register_token;
                el = document.querySelector('input[name="aff"]');
                if(el && d.register_aff) el.value = d.register_aff;
            });
    })();
    </script>
```

**must**：
- `fetch` 必须用 `method:'POST'`
- `credentials:'same-origin'` 确保发送 cookie
- 放在 `auth.layouts.blade.php` 而非逐页面，所有继承此布局的页面自动生效

**input**：页面 DOM 中存在 `input[name="_token"]`、`input[name="register_token"]`、`input[name="aff"]`

**output**：这些 input 的 value 被替换为当前用户自己的值

---

### Step 7：修复注册页 JS 中硬编码的 CSRF token

**where**：`resources/views/auth/register.blade.php` line 162

**what**：JS 中 `{{csrf_token()}}` 改为从 DOM 读取。

**why**：`{{csrf_token()}}` 在服务端渲染时被固定为缓存时的旧值。Step 6 的 JS 已刷新了 DOM 中的 token，这里应该读取刷新后的值。

**how**：

```javascript
// 原来（line 162）：
var data = $.extend({_token: '{{csrf_token()}}', username: username}, powParams);

// 改为：
var data = $.extend({_token: document.querySelector('input[name="_token"]').value, username: username}, powParams);
```

**must**：此 AJAX 在用户点击"发送验证码"按钮时触发，此时 Step 6 的 `POST /token` 早已完成（填表时间 >> token 请求时间）

---

### Step 8：POST 端点改为返回 JSON

**where**：`app/Http/Controllers/AuthController.php`，以下 5 个方法的 POST 分支

**what**：所有未登录表单的 POST 处理从 redirect 改为返回 JSON。

**why**：传统 form submit + redirect 依赖 Flash 消息（存在 session），缓存的 GET 页面无法显示。改为 AJAX + JSON 后，GET 页面永远是缓存版本，错误/成功信息由 JS 从 JSON 读取并动态显示。

**how**：

#### 8.1 `login()` POST 分支（~line 50-145）

所有 `Redirect::back()->withInput()->withErrors(...)` 改为：

```php
return response()->json(['status' => 'fail', 'message' => '错误信息']);
```

登录成功改为：

```php
// 原来：
return Redirect::to('/');  // 或 Redirect::to('admin')
// 改为：
return response()->json(['status' => 'success', 'redirect' => Auth::user()->is_admin ? 'admin' : '/']);
```

#### 8.2 `register()` POST 分支（~line 160-453）

验证失败：

```php
return response()->json(['status' => 'fail', 'message' => '错误信息']);
```

注册成功：

```php
// 原来：
Session::flash('regSuccessMsg', '注册成功');
return Redirect::to('login')->withInput();
// 改为：
return response()->json(['status' => 'success', 'message' => '注册成功', 'redirect' => 'login']);
```

#### 8.3 `resetPassword()` POST 分支（~line 465-530）

验证失败：`return response()->json(['status' => 'fail', 'message' => '...']);`

成功：`return response()->json(['status' => 'success', 'message' => '重置密码邮件已发送']);`

#### 8.4 `activeUser()` POST 分支（~line 599-655）

验证失败：`return response()->json(['status' => 'fail', 'message' => '...']);`

成功：`return response()->json(['status' => 'success', 'message' => '激活邮件已发送，请查看垃圾箱']);`

#### 8.5 `reActiveUser()` POST 分支（~line 920-942）

验证失败：`return response()->json(['status' => 'fail', 'message' => '...']);`

成功：`return response()->json(['status' => 'success', 'message' => '解封成功，请登录']);`

**must**：
- 所有 POST 分支返回 JSON，不能有任何 redirect（302）
- GET 分支不变，仍然 `return Response::view(...)`
- 登录成功的 redirect URL 由前端 JS 执行 `window.location.href`
- 错误消息用 `message` 字段，与现有 `sendCode` 的 JSON 格式保持一致

---

### Step 9：Blade 模板表单改为 AJAX 提交

**where**：5 个 Blade 模板

**what**：拦截表单 submit，改为 AJAX 提交，用 JS 动态显示错误/成功信息。

**why**：POST 返回 JSON 后，需要 JS 解析响应并更新 DOM，不能再用服务端渲染的 Flash 消息。

**how**：

#### 9.1 `login.blade.php` — 改造表单提交

现有的 PoW 提交逻辑（line 107-132）改为 AJAX：

```javascript
var _powSubmitted = false;
$('#login-form').submit(function(event){
    if (_powSubmitted) return true;
    event.preventDefault();

    // 验证码检查（保持不变）
    if ($('#g-recaptcha-response').val() === '') {
        Msg(false, "{{trans('login.required_captcha')}}", 'error');
        return false;
    }

    var $btn = $(this).find('button[type=submit]');
    var origText = $btn.html();
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 安全验证中...');

    PoW.consume(function () {
        // 注入 PoW 参数到表单
        _powSubmitted = true;
        $btn.prop('disabled', false).html(origText);

        // 改为 AJAX 提交（而非原始 form submit）
        $.ajax({
            type: "POST",
            url: "{{url('login')}}",
            data: $('#login-form').serialize(),
            dataType: 'json',
            success: function(ret) {
                if (ret.status === 'success') {
                    window.location.href = ret.redirect;
                } else {
                    Msg(false, ret.message, 'error');
                }
            },
            error: function() {
                Msg(false, '请求异常，请刷新页面重试', 'error');
            }
        });
    });
    return false;
});
```

**注意**：`Msg()` 函数已存在于 login.blade.php（line 135-152），可以直接复用。

#### 9.2 `register.blade.php` — 改造表单提交

现有的 PoW 提交逻辑（line 198-221）改为 AJAX，模式与 login 完全相同：

```javascript
var _powSubmitted = false;
$('#register-form').submit(function(event){
    if (_powSubmitted) return true;
    event.preventDefault();

    if ($('#g-recaptcha-response').val() === '') {
        Msg(false, "{{trans('login.required_captcha')}}", 'error');
        return false;
    }

    var $btn = $(this).find('button[type=submit]');
    var origText = $btn.html();
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 安全验证中...');

    PoW.consume(function () {
        _powSubmitted = true;
        $btn.prop('disabled', false).html(origText);

        $.ajax({
            type: "POST",
            url: "{{url('register')}}",
            data: $('#register-form').serialize(),
            dataType: 'json',
            success: function(ret) {
                if (ret.status === 'success') {
                    Msg(true, ret.message, 'success');
                    setTimeout(function(){ window.location.href = ret.redirect; }, 1500);
                } else {
                    Msg(false, ret.message, 'error');
                }
            },
            error: function() {
                Msg(false, '请求异常，请刷新页面重试', 'error');
            }
        });
    });
    return false;
});
```

**注意**：`Msg()` 函数也存在于 register.blade.php（line 224-241），可直接复用。

#### 9.3 `resetPassword.blade.php` — 改造表单提交

此页面有 PoW（line 57-72），改造模式相同。将 PoW 完成后的 `$('.forget-form').submit()` 改为 AJAX。

#### 9.4 `activeUser.blade.php` — 改造表单提交

此页面**没有 PoW**，改动更简单。将 form submit 直接拦截为 AJAX：

```javascript
$('.forget-form').submit(function(event){
    event.preventDefault();
    var $btn = $(this).find('button[type=submit]');
    $btn.prop('disabled', true);

    $.ajax({
        type: "POST",
        url: "{{url('activeUser')}}",
        data: $(this).serialize(),
        dataType: 'json',
        success: function(ret) {
            if (ret.status === 'success') {
                alert(ret.message);
            } else {
                alert(ret.message);
            }
            $btn.prop('disabled', false);
        },
        error: function() {
            alert('请求异常，请刷新页面重试');
            $btn.prop('disabled', false);
        }
    });
});
```

#### 9.5 `reActiveUser.blade.php` — 同 activeUser

模式完全相同。

**must**：
- 所有表单提交必须 `event.preventDefault()` 阻止默认行为
- AJAX 数据用 `$(form).serialize()` 自动收集所有字段（含 CSRF token、PoW 字段等）
- 错误提示复用各页面已有的 `Msg()` 函数或 `alert()`
- 成功时用 `window.location.href = ret.redirect` 跳转

---

## 五、改动文件清单

| # | 文件 | 改动 | 行数 |
|---|------|------|------|
| 1 | `/etc/nginx/nginx.conf` | 添加 `fastcgi_cache_path` | +6 行 |
| 2 | `/etc/nginx/conf.d/test-npanel.freessr.bid.conf` | 替换完整配置（map + cache 指令） | ~30 行 |
| 3 | `routes/web.php` (line 5 附近) | 新增 `Route::post('token', ...)` | +1 行 |
| 4 | `app/Http/Controllers/AuthController.php` | 新增 `token()` 方法 + 5 个 POST 分支改 JSON | 改 ~50 行 |
| 5 | `app/Http/Middleware/VerifyCsrfToken.php` | `$except` 加 `"token"` | +1 行 |
| 6 | `resources/views/auth/layouts.blade.php` (line 89 后) | 注入 CSRF 刷新 JS | +17 行 |
| 7 | `resources/views/auth/register.blade.php` (line 162) | `csrf_token()` → DOM 读取 | 改 1 行 |
| 8 | `resources/views/auth/login.blade.php` | form submit 改 AJAX | 改 ~20 行 |
| 9 | `resources/views/auth/register.blade.php` | form submit 改 AJAX | 改 ~20 行 |
| 10 | `resources/views/auth/resetPassword.blade.php` | form submit 改 AJAX | 改 ~15 行 |
| 11 | `resources/views/auth/activeUser.blade.php` | form submit 改 AJAX | 改 ~15 行 |
| 12 | `resources/views/auth/reActiveUser.blade.php` | form submit 改 AJAX | 改 ~15 行 |
| | | **合计 12 个文件** | **~190 行** |

---

## 六、验证清单

```bash
# 1. Nginx 配置语法检查
nginx -t

# 2. 重载 Nginx
nginx -s reload

# 3. 创建缓存目录
mkdir -p /var/cache/nginx/npanel && chown www-data:www-data /var/cache/nginx/npanel

# 4. 测试 /free 缓存
curl -I https://test-npanel.freessr.bid/free
# 第一次: X-Cache-Status: MISS
# 第二次: X-Cache-Status: HIT

# 5. 测试 /login 缓存
curl -I https://test-npanel.freessr.bid/login
# 第一次: MISS，第二次: HIT

# 6. 测试 POST /token 端点
curl -X POST https://test-npanel.freessr.bid/token
# 应返回: {"csrf_token":"...","register_token":null,"register_aff":null}

# 7. 测试 /active/{token} 不缓存
curl -I https://test-npanel.freessr.bid/active/sometoken
# 应始终为 MISS 或 302

# 8. 完整登录流程
# - 浏览器打开 /login
# - DevTools Network 确认: GET /login 命中缓存 (X-Cache-Status: HIT)
# - DevTools Network 确认: POST /token 返回 200 + csrf_token
# - 输入账密登录，确认不会 419
# - 故意输错密码，确认能看到 JS 动态插入的错误提示（不是页面刷新）
# - 登录成功后确认跳转到首页

# 9. 完整注册流程
# - 浏览器打开 /register?aff=123
# - DevTools Network 确认: POST /token 返回 register_aff
# - 填写注册信息提交，确认 AJAX 返回 JSON 而非页面刷新
# - 注册成功后确认跳转到登录页

# 10. 测试 resetPassword / activeUser / reActiveUser
# - 每个页面提交后确认返回 JSON + JS 显示提示，不刷新页面

# 11. 权限修复（如果有新 PHP 文件）
podman exec php7-npanel chown -R www-data:www-data /var/www/test-npanel.freessr.bid/app/
podman exec php7-npanel chmod -R 755 /var/www/test-npanel.freessr.bid/app/
podman exec php7-npanel php /var/www/test-npanel.freessr.bid/composer.phar dump-autoload
```

---

## 七、已排除页面的原因（不缓存）

| 页面 | 原因 |
|------|------|
| `GET /active/{token}` | **GET 修改数据库**（激活用户账号），缓存会导致激活操作被跳过。绝对不能缓存。 |
| `GET /s/{code}` | 频率限制（Redis per IP）和日志记录（MySQL INSERT）依赖每次请求都到达 PHP。缓存会导致这两项功能失效。 |
| 所有 `POST` 路由 | Nginx 天然不缓存 POST，无需额外处理。 |
| `GET /logout`、`/lang/{locale}` | 返回 302 重定向，无 HTML 页面。 |
| `GET /makePasswd` 等 | 返回随机字符串，非 HTML 页面。 |
