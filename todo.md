



- [v] npanel 升级 多个 订阅接口. 方案.

what: 在 用户页面 订阅处显示多个 不同的订阅地址. 然后自动检测哪些订阅地址是可用的.
why: 订阅地址可能在大陆被墙,需要提供多个不同网络的地址.
how: 支持 多个订阅地址的域名, 然后 通过判断 https://rss.xx.xx/check.png 是否能加载,来判断 订阅是否可用. (自动判断) 无法加载的 加载 /error.png 图标. 请注意:  check.png 本身就是一个 绿色对勾. 所以, 用户的网络会自动判断,不需要我们判断.
must : 

- [v] status=0 但是 在 12小时内有 heartbeat_at 的节点, 标注为 红色


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



- [] vision xhttp 随机 sni

what: sni加前缀
why: host 可以追踪 具体的地址, sni的话 , 其实是为了让每个用户访问的网站不一致,减少 gfw的 追踪
where: app/Http/Controllers/SubscribeController.php
how: 在 sni 前 加 'u' . $user->id . 'u' . $sni ; 但是排除以下两种情况, grpc sni不处理; 如果节点是 cdn节点,sni不处理! 避免 cdn 识别出错. 
must: 


- [] 解决 vision fallback 的问题! 直接fallback到本地的 nginx 这里.
  - 在安装的时候,直接解决 nginx 版本的问题,直接升级到 最新版本!
  - 直接安装最新版本的 nginx
  - vision 协议反代到自己的网站上.


what:  vision fallback 到 本地 8088 端口. 
why: 直接fallback到本地更稳定
where: 
    - resources/templates/xray/vision-hy2-ws-grpc.json   resources/templates/nginx/vision-hy2-ws-grpc.conf
how:  
    - resources/templates/nginx/vision-hy2-ws-grpc.conf fallback dest 到 127.0.0.1:8088, 而且两个合并为一个即可.都是 8088端口. 可以合二为一 ; 
    - 在 resources/templates/nginx/vision-hy2-ws-grpc.conf 添加 8088 的反向代理, 使用新版的 nginx 监听 http2 监听方式 (不使用旧版的 所有客户端的nginx都会升级到新版的 http2 监听方式);
    - 反向代理参考: resources/templates/nginx/xhttp-hy2-ws-grpc.conf 的默认回落
must: 



- [] vision 的 grpc ws 也使用 nginx 反代 且只能使用 cf 的 ip访问

what: vision 的 grpc ws 改为 nginx反代
why: ws grpc 需要限制 只能由 cloudflare cdn的ip才能访问,其他ip不能访问. 但是 xray 回落的话,做不到ip限制,还是交给 nginx比较靠谱
where: 
how:
    - resources/templates/xray/vision-hy2-ws-grpc.json 中的 ws 和 grpc 改为 监听 10011 10012 端口, 让 nginx反代
    -  resources/templates/nginx/vision-hy2-ws-grpc.conf 监听 2053端口, 然后反代给后端 的 ws grpc 
    - app/Http/Controllers/SubscribeController.php 在订阅的时候, ws 和 grpc是 2053端口.
    - app/Http/Controllers/Api/NodeApiController.php 在 register阶段, 下发的 config.json 中 ws 和 grpc 监听的端口也要改为 10011 10012 , 下发的 nginx.conf中要添加 2053端口 的监听.
must:



- [] nginx 中设置 ws 和 grpc 协议,只允许来自 指定ip的 访问! 只允许来自 cloudflare 的 ip来访问! 这个可以限制
  - grpc 还是需要通过 nginx 前置代理 来 访问,在 nginx限制 只允许 来自 cloudflare的 ip来访问! 这个可以有.
  - ws也是,只允许来自 cloudflare的 ip来访问,其他的不允许.
  - [] npanel
    - [] vision 
    - [] xhttp 

what: ws 和 grpc 在 nginx中的反代, 只允许 cloudflare 的 ip来访问
why: ws 和 grpc 协议已经可以被识别, 所以不能用用户直连,避免用户直连导致ip被封锁
where: 
how:
    - 修改 resources/templates/nginx/vision-hy2-ws-grpc.conf 和 resources/templates/xray/xhttp-hy2-ws-grpc.json 中 关于 ws 和 grpc的部分, 只允许 cloudflare的 ip才能访问. 
must:


- [] 新增获取所有节点的 原始 配置信息的 json api
what: 新增 一个api 可以获取所有节点的原始的 json输出
why: 方便 其他应用交互查询节点信息
where: app/Http/Controllers/Api/NodeApiController.php
how: 
    - 新增一个路由
    - 直接查询数据库中 ss_node 中所有节点
    - 直接返回 json格式, 包含所有节点的信息. 


- [] 新增协议支持:

what: 新增 一些 v2_name
why: 当前只有 vision-hy2-ws-grpc xhttp-hy2-ws-grpc 太少了,新增一些
where:
how:
    - 新增 vision-hy2 
        - 复制 resources/templates/xray/vision-hy2-ws-grpc.json 
must:
