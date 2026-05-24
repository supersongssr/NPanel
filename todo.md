
- [] status=0 但是 在 12小时内有 heartbeat_at 的节点, 标注为 红色

what : 将 status=0 的节点, 在 12小时内有 heartbeat_at 的节点, 标注为 红色
why : 这些节点很可能是流量耗尽,但是还有心跳的, 需要单独筛选出来,然后方便统一检查
how : 
must : 

- [] 弃用警告
legacy special outbounds 已在 sing-box 1.11.0 中被弃用，且将在 sing-box 1.13.0 中被移除，请参阅迁移指南。

如果您不明白此消息意味着什么：您的配置文件已过时，且将很快不可用。请联系您的配置提供者以更新配置。


- [v] 白名单问题
    - 取消 名单限制功能; 
        - 更新
        - 上报
        - 删除所有 正式站点中的 所有的屏蔽掉的黑名单. 这个可以有,允许所有人注册的方式很不错.
    - 设定为黑名单比较好 , 需要删除所有的 名单数据

- [v] bug: 检查 singbox订阅中是否正确支持了 vless-xhttp , vmess-ws 
    - why: 避免 singbox 中出现报错信息. 
    - where: @app/Http/Controllers/SubscribeController.php
    - how: 
        - 参考 @tmp/vmess.md 和 @tmp/ws.md 中对 singbox 处理 v2ray 中对 xhttp 协议 和 vmess 协议 和 ws 协议的正确描述. 
        - 参考 @tmp/vmess.md 对 xhttp协议的正确描述
            - warn: 在 sing-box 的官方语料库中，并没有 xhttp 这个名字，它对应的是 httpupgrade。
    - must : 只修改 singbox 订阅
        - 要求订阅输出格式符合 singbox 规范.


- [v] feature: 根据 host请求的不同的 x-forword-host 网站使用不同的 website_name subscribe_domain website_url 
    - why: 网站需要 允许 反向代理,根据不同的 反代 请求 host , 网站显示不同的 url 和 名字 和 rss
    - where: @App\Components\Helpers::systemConfig() 
    - how:
        - 在数据库中添加 host_pools 不同的 host 映射不同的 website_name website_url subscribe_domain 
        - 当没有设置 host_pools 的时候, 应该使用默认的配置
        - 添加 HTTP_HOST 检测
        - 在 前端面板添加 host_pools 的设置项
        - 不要修改 .env 的 APP_URL
    - must :
        - 兼容旧版,兼容没有这些参数的情况


- [] 安全加固
    - nginx
        - 请求频率限制
    - 无需登录页面
        - [] nginx缓存(静态页面缓存) claude --resume 53615de0-49c0-4e1d-965f-7c765ea690de
          - prompt: docs/plan/2026-05-08-nginx-cache-analysis.md
          - QA: 
    - 登录/注册页面
        - [v] pow证明: 在 登录 注册 验证码 找回密码的接口处 添加POW工作量证明
            - what : 前端 登录 注册 邮箱验证码 找回密码 等 post请求 添加 pow工作量证明
            - why : 防止未登录页面的  post请求被cc攻击
            - where: @app/Http/Controllers/AuthController.php  @resources/views/auth routes/web.php 或 routes/api.php 
            - how:
                - pow 证明
                    - 使用 平滑方案. 取 前6位做成数字 然后对比 阈值
                    - 默认最低难度是 10000(即预期是 10000次计算) 在 配置文件中可设置
                    - 难度 = 服务器负载 * 160000. 
                    - 计算代码一定要精简.不能复杂.
                - 网页端:
                    - 1 创建一个 pow函数; 在页面加载后运行一次
                    - 2 在 post 请求中调用 pow 函数, 验证工作量证明, 并重置 pow函数计数器
                        - 避免同一个页面有多个post请求比如 注册页面 验证邮箱需要一个pow, 点击注册也需要一个 pow
                    - 必须使用浏览器自带的 sha265
                    - 必须不能卡住网页,要使用 内联 Web Worker
                - 接口端:
                    - 发送验证:
                        - ip 时间戳 slat 签名发给前端,弄一个post请求接口
                    - 接受验证:
                        - 先验证时间, 如果和当前时间 完全相等,不行! 超过15分钟也不行! 
                        - 再验证签名
                        - 再验证 pow 工作量证明是否有效
                        - 然后将有效的 pow工作量证明存入 redis 有效期和POW有效期相同 15分钟, 每个工作量证明只允许使用一次
            - must:
            - input:
            - output:   
    - 已登陆页面
        - [v] redis验证 isLogin 中间件 中在数据库查询之前
            - 防止 cookies 多ip攻击, 限制 10分钟 5 ip.
                - sadd sid ip 检查返回值
                    - 新ip : 检查 数量, 超过5 , 踢出登录状态,删除 sid
                        - 没超过5, 就给 sadd 设置10分钟过期时间
                    - 已存在的ip: 跳过处理


- 功能
    - 工单
        - [v] 关闭提交工单扣费功能
            - what: 关闭 提交工单扣费功能. 关闭 回复工单 加钱功能.这个功能太蠢了
            - why: 这个功能太蠢了. 在消费记录中没有记录
            - where:@app/Http/Controllers/UserController.php    @app/Http/Controllers/TicketController.php  @resources/views/user/ticketList.blade.php  @resources/views/user/replyTicket.blade.php   @resources/views/ticket/replyTicket.blade.php  
            - how:
                - 关闭 提交工单扣费
                - 关闭 回复并公开工单 加钱
                - 前后端的提示 修改掉.
            - must:
            - input :
            - output:
        - [v] 防止用户频繁提交工单. 
          - claude --resume 1db9b4f1-7cd1-438c-b50d-0037f4dd5d6d
          - docs/2026-05-07-工单提交频率限制方案.md
    - [] isForbidden 取消掉这个功能,因为耗费大量的cpu计算ip地理位置 
      - gemini --resume e3067a51-e43d-4f50-bfed-7e6fcb7139a7  
      - prompt: docs/plan/2026-05-09-remove-isforbidden.md
      - QA:docs/QA/2026-05-09-remove-isforbidden.md


- [v] API v2 
    - docs/plan/2026-05-12-node-api-v2-development-summary.md
    - docs/api/node-api-v2.md
    - claude --resume dbb160bd-2a2e-40e5-8255-3978ca9ac369
    - status
        - [] 流量重置问题
            - [] 28 29 30 31 日问题
            - [] 错误重置日问题
    - register 
        - node unlock 
            - [] new 格式 
- [] 将 system config 中的数据 放入 redis 缓存,不必每次都查询 数据库 
    - gemini --resume 565311ba-6c74-4d47-94c2-b04d27413b11  
    - docs/plans/2026-05-12-cache-system-config-redis.md

- artisan 
    - [v] 获取节点列表
        - docs/plans/2026-05-15.md


- check
    - clone节点等级必须比 主节点  >= 主节点等级


- [v] reinstall & new install 

- api v2 
    - node 
        - [v] 回落的 url plans/api/v2/20260520_回落地址的确定.md

- [v] admin edit node面板添加可以修改 traffic_used 的地方.

- [v] hy2 支持动态 端口.
    - claude --resume 2e9c3f06-dc64-451b-ae1e-8b7f5d5db67e



- [v] php artisan 定期删除 超过 30天没有心跳的节点的 dns 信息,

what: 定期删除 超过 30天没有心跳的节点的 dns 信息; dns服务商那里删除掉 dns record
why: 每个域名的解析数量有限额 180个, 避免死节点占用 解析资源
how: 定时任务,每天检查超过30天没有心跳包的节点, 然后 删除节点的 dns解析记录在数据库中的 和 服务商dns解析. 
must: 删除 dns 服务商的 解析
