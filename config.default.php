<?php
/**
 * config.default.php — 系统默认配置 (随版本发布, 提交 git)
 *
 * 结构: 扁平 [name => value], 与 DB config 表 1:1, value 全部为字符串.
 *       JSON 类配置 (node_protocol_presets / host_pools / clonepay_webs /
 *       clonepay_apis) 仍以 JSON 字符串保存, 消费点 json_decode 零改动.
 *       原生数组配置 (新版): node_domain_map (关联数组 domain=>meta) /
 *       subscribe_domain_list (索引数组) 为新版写法, 优先于同名 JSON 旧键;
 *       旧键 node_domain_pool / subscribe_domains 保留为 JSON 字符串以兼容
 *       历史配置, 已标注废弃. 解析规则: 新键非空则用新键 (替代旧键),
 *       否则回退旧键 (JSON 字符串) — 实现“有新配置则替代, 无则兼容旧版”.
 *
 * 覆盖: .config.php (git ignore, 仅手工编辑, 代码不修改) 的同名 key 整体替换本文件值.
 * 动态: traffic_record_group1 / traffic_record_group2 由定时任务
 *       AutoStatisticsNodeDailyTraffic 每日写入 DB, 代码以白名单从 DB 读取;
 *       此处保留空字符串仅作兜底防 undefined index, 实际值来自 DB.
 */
return [

    /* ============================================================
     * 网站基础
     * ============================================================ */
    'website_name'             => 'NPanel',  // 网站名称, 发邮件时展示
    'website_url'              => '',        // 网站地址, 生成重置密码/邀请码/充值专用 URL
    'website_logo'             => '',        // 站内 LOGO, 推荐尺寸 150x30 透明背景 (路径如 /upload/image/xxx.png)
    'website_home_logo'        => '',        // 首页 LOGO, 推荐尺寸 300x90 透明背景
    'website_analytics'        => '',        // 网站统计代码 (如 Google Analytics / 百度统计 JS)
    'website_customer_service' => '',        // 在线客服代码
    'website_security_code'    => '',        // 网站安全码; 非空时需通过 /login?securityCode=安全码 访问
    'wechat_qrcode'            => '',        // 微信二维码图片路径
    'alipay_qrcode'            => '',        // 支付宝收款二维码图片路径

    /* ============================================================
     * 注册 / 登录
     * ============================================================ */
    'is_register'          => '1',  // 用户注册开关 (1启用 0关闭), 关闭后无法注册
    'is_invite_register'   => '2',  // 邀请注册 (0关闭 1可选 2必须)
    'is_active_register'   => '1',  // 激活账号; 启用后用户需通过邮件激活账号
    'active_times'         => '3',  // 激活账号次数; 24小时内可通过邮件激活的次数
    'is_reset_password'    => '1',  // 重置密码; 启用后用户可通过邮件重置密码
    'reset_password_times' => '3',  // 重置密码次数; 24小时内可通过邮件重置的次数
    'is_captcha'           => '0',  // 验证码 (0关闭 1普通验证码 2极验Geetest 3Google reCAPTCHA)
    'is_verify_register'   => '0',  // 注册校验验证码; 注册前需邮件获取验证码 (启用后"激活账号"失效)
    'register_ip_limit'    => '5',  // 同IP注册限制; 24小时内允许注册数量, 0不限制
    'is_ban_status'        => '0',  // 过期自动封禁 (慎重); 封禁会重置账号数据且无法登录

    /* ============================================================
     * 邀请 / 推广返利
     * ============================================================ */
    'invite_num'                => '3',    // 可生成邀请码数; 用户可生成的邀请码数量
    'referral_traffic'          => '1024', // 注册送流量 (MiB); 经推广链接/邀请码注册赠送的流量
    'referral_percent'          => '0.2',  // 返利比例; 推广链接注册账号每笔消费推广人分成 (0.2=20%)
    'referral_money'            => '100',  // 提现限制; 满多少元才可申请提现
    'referral_status'           => '1',    // 推广返利开关 (1开 0关); 关闭后用户不可见但不影响已发生的返利
    'referral_register_reward'  => '0',    // 推广注册奖励
    'referral_enable_uid'       => '',     // 推广启用的指定 UID
    'user_invite_days'          => '7',    // 邀请码有效期-用户 (天); 用户自行生成邀请码的有效期
    'admin_invite_days'         => '7',    // 邀请码有效期-管理员 (天); 管理员生成邀请码的有效期

    /* ============================================================
     * 流量 / 端口
     * ============================================================ */
    'default_traffic'         => '1024',  // 初始流量 (MiB); 用户注册时默认可用流量
    'default_days'            => '7',     // 初始有效期 (天); 用户注册时默认账户有效期, 0即当天到期
    'reset_traffic'           => '1',     // 流量自动重置; 用户按套餐日期自动重置可用流量
    'traffic_warning'         => '0',     // 用户流量警告开关; 启用后超阈值发邮件提醒
    'traffic_warning_percent' => '80',    // 流量警告阈值 (%); 已用流量超此比例发警告, 建议 70~90
    'expire_warning'          => '0',     // 用户过期警告开关; 启用后距到期阈值发邮件提醒
    'expire_days'             => '15',    // 过期警告阈值 (天); 距过期还差多少天发警告邮件
    'is_traffic_ban'          => '1',     // 异常自动封号; 1小时内流量异常的用户分组-1
    'traffic_ban_value'       => '10',    // 流量异常阈值 (GB); 1小时内流量限制
    'traffic_ban_time'        => '60',    // 封号时长 (分钟); 触发异常被封时长, 到期自动解封
    'traffic_limit_time'      => '1440',  // 签到时间间隔 (分钟); 间隔多久才可再次签到
    'is_rand_port'            => '0',     // 随机端口; 注册/添加用户时在端口范围内随机生成端口
    'is_user_rand_port'       => '0',     // 自定义端口; 用户可自定义端口
    'min_port'                => '10000', // 端口范围下限 (1000-65535)
    'max_port'                => '20000', // 端口范围上限 (1000-65535)
    'auto_release_port'       => '1',     // 端口自动释放; 被封禁/过期一个月的用户端口自动释放

    /* ============================================================
     * 签到
     * ============================================================ */
    'is_checkin'       => '1',   // 签到加流量; 登录时按流量范围随机得到流量
    'min_rand_traffic' => '10',  // 签到流量下限 (M)
    'max_rand_traffic' => '500', // 签到流量上限 (M)

    /* ============================================================
     * 订阅
     * ============================================================ */
    'subscribe_max'       => '3',   // 订阅节点数; 客户端订阅取几个节点, 0返回全部
    'subscribe_domain'      => '',    // 节点订阅地址; 防 DNS 投毒, 需带 http:// 或 https://
    'subscribe_domain_list' => [],    // 多订阅域名列表 (PHP 索引数组, 如 ['https://1.example.com','https://2.example.com']); 用户订阅页展示多个订阅地址, 每个需带 http(s)://. 新版写法, 优先于 subscribe_domains
    'subscribe_domains'     => '[]',  // [已废弃] 多订阅域名列表 (JSON 字符串); 已被 subscribe_domain_list 替代, 保留以兼容旧版
    'is_subscribe_ban'    => '1',   // 订阅异常自动封禁; 订阅异常用户自动分组-1
    'subscribe_ban_times' => '20',  // 订阅请求阈值; 24小时内订阅链接请求次数限制
    'mix_subscribe'       => '0',   // 混合订阅; 订阅信息含 V2Ray 节点 (仅 Shadowrocket/Quantumult/v2rayN)
    'rand_subscribe'      => '0',   // 随机订阅; 订阅时随机返回节点, 否则按排序返回
    'is_custom_subscribe' => '0',   // 高级订阅; 订阅顶部显示过期时间/剩余流量
    'sub_rss_url'         => '',    // 订阅转换地址

    /* ============================================================
     * 节点 / 监控
     * ============================================================ */
    'is_clear_log'            => '1',     // 自动清除日志 (推荐); 自动清除无用日志
    'is_node_crash_warning'   => '0',     // 节点离线提醒; 节点离线通过 ServerChan 推送
    'crash_warning_email'     => '',      // 管理员收信地址; 节点离线/用户回复工单自动提醒
    'is_tcp_check'            => '0',     // TCP 阻断检测; 每 30~60 分钟随机检测节点是否被 TCP 阻断
    'tcp_check_warning_times' => '3',     // 阻断检测提醒次数; 提醒几次后自动下线节点, 0不限制, 不超过 12
    'node_daily_report'       => '0',     // 节点使用报告; 每天早上9点推送昨日节点使用情况
    'is_forbid_china'         => '0',     // 阻止大陆访问; 大陆 IP 禁止访问
    'is_forbid_oversea'       => '0',     // 阻止海外访问; 海外 IP(含港澳台) 禁止访问
    'node_fallback_host'      => '',      // 回落基础域名; 伪装站域名不含协议, 推导 __httpProxyHost__/__v2Fallback__/__HYSTERIA_URL__
    'node_root_domain'        => '',      // 节点根域名; 域名池为空时的兜底主域名
    'dns_expire_days'         => '30',    // DNS 自动清理阈值 (天); 心跳超期无活跃节点的 DNS 记录被定时清理, 默认 32
    'node_recycle_ratio'      => '0.50',  // 死节点回收触发比例; 死节点/总节点 超此值则硬删除冗余 (见 autoReclaimDeadNodes)
    'node_recycle_min_dead'   => '500',   // 死节点回收绝对下限; 死节点低于此值不触发回收 (无存储压力不折腾)
    'pow_base_difficulty'     => '10000', // PoW 基础难度; 客户端工作量证明基础难度
    'node_protocol_presets'   => '{"threshold_mb":2048,"high":"xhttp-hy2-ws-grpc","low":"vision-hy2-ws-grpc"}',  // 节点协议预设 (JSON: threshold_mb/high/low)
    'node_default_v2_name'    => 'vision-curvePreferences',  // 默认 v2 name; 节点注册时未指定 v2_name 的兜底值
    /*
    | -----------------------------------------------------------------
    | node_domain_map  节点域名池 (PHP 关联数组: domain => meta)
    | -----------------------------------------------------------------
    | 新版写法 (优先于已废弃的 node_domain_pool JSON 字符串). 默认空数组;
    | 真实配置 (含 cf_token 等敏感数据) 放 .config.php, 代码不修改该文件.
    |
    | meta 支持的键 (均在 production 读取, 唯 provider 例外):
    |   zone_id        str  必填. CF Zone ID; 缺失则该域名无法增删 DNS 记录.
    |   cf_token       str  可选. 该域名所在 CF 账号的 API Token (跨账号域名必填);
    |                        留空则用 .env 的全局 CLOUDFLARE_TOKEN. 与 cdn 正交.
    |   cdn            bool 可选. true=该域名走 CF CDN, 仅供 xhttp-cdn 协议节点选用
    |                        (resolveDomainAffinity cdnOnly). 默认 false.
    |   records_limit  int  可选. 该域名下 DNS 记录数上限, 默认 180.
    |   proxied        bool 可选. DNS 记录是否 CF 橙云代理. 默认 false (灰云直连源站);
    |                        UI 不暴露此键, 仅手写 .config.php 时可设 true.
    |   ech            str  可选. ECH 规格 `ech.{domain}+udp://1.1.1.1`; 留空用默认.
    |   expire_date    str  可选. 域名到期日 (YYYY-MM-DD), 仅记录/展示用.
    |   provider       str  ⚠️ 僵尸字段: 代码不读取, 恒为 'cloudflare'.
    |                        仅 UI/历史数据保留, 手写时可省略.
    |
    | 参考配置 (复制到 .config.php 的 'node_domain_map' 键下):
    |
    |   'node_domain_map' => [
    |       // 1) 普通域名: 只需 zone_id, 用 .env 全局 CLOUDFLARE_TOKEN
    |       'ssmail.win' => [
    |           'zone_id' => '11112222333344445555666677778888',
    |           'records_limit' => 200,
    |       ],
    |
    |       // 2) 跨账号普通域名 (如 vvup.top): 非独立 CDN, 但托管在别的 CF 账号
    |       //    → 必须填该账号的 cf_token, 不勾 cdn. 这就是 "不同 domain 用不同 Token" 的场景.
    |       'vvup.top' => [
    |           'zone_id'  => 'aaaabbbbccccddddeeeeffff00001111',
    |           'cf_token' => 'vvup-account-api-token',   // 该域名所在 CF 账号的 Token
    |           'records_limit' => 180,
    |       ],
    |
    |       // 3) CDN 域名: 走 CF CDN, 供 xhttp-cdn 协议节点选用 (防封隔离)
    |       //    通常用独立账号 Token 隔离; cdn 与 cf_token 正交, 也可共用全局 Token.
    |       'sspcccdn.xyz' => [
    |           'zone_id'  => '99998888777766665555444433332222',
    |           'cdn'      => true,
    |           'cf_token' => 'cdn-account-api-token',
    |           'records_limit' => 1000,
    |           'ech'      => 'ech.sspcccdn.xyz+udp://1.1.1.1',
    |           'expire_date' => '2026-12-31',
    |       ],
    |   ],
    */
    'node_domain_map'         => [],    // 默认空; 真实配置 (含 cf_token) 放 .config.php
    'node_domain_pool'        => '[]',  // [已废弃] 节点域名池 (JSON 字符串); 已被 node_domain_map 替代, 保留以兼容旧版. 含敏感数据, 真实值放 .config.php
    'host_pools'              => '{}',    // 多站点配置 (JSON: host => website_name/website_url/subscribe_domain)

    /* ============================================================
     * 支付: 支付宝国际
     * ============================================================ */
    'is_alipay'          => '0',    // 支付宝国际支付开关
    'alipay_sign_type'   => 'MD5',  // 支付宝加密方式 (MD5 / RSA)
    'alipay_partner'     => '',     // 支付宝 partner (PID)
    'alipay_key'         => '',     // 支付宝 key (MD5 校验密钥)
    'alipay_private_key' => '',     // 支付宝 RSA 私钥
    'alipay_public_key'  => '',     // 支付宝 RSA 公钥
    'alipay_transport'   => 'http', // 支付宝传输协议; HTTPS 站点需用 https (启用 SSL 验证)
    'alipay_currency'    => 'USD',  // 支付宝结算币种

    /* ============================================================
     * 支付: 支付宝当面付 (F2FPay)
     * ============================================================ */
    'is_f2fpay'           => '0',  // 支付宝当面付开关
    'f2fpay_app_id'       => '',   // 当面付应用 ID (APPID)
    'f2fpay_private_key'  => '',   // 当面付 RSA 私钥 (不含首尾格式)
    'f2fpay_public_key'   => '',   // 当面付支付宝公钥 (注意不是 RSA 公钥)
    'f2fpay_subject_name' => '',   // 自定义商品名称; 用户支付宝客户端显示

    /* ============================================================
     * 支付: PayPal
     * ============================================================ */
    'paypal_status'        => '0',  // PayPal 开关
    'paypal_client_id'     => '',   // PayPal Client ID
    'paypal_client_secret' => '',   // PayPal Client Secret

    /* ============================================================
     * 支付: 有赞云
     * ============================================================ */
    'is_youzan'            => '0',  // 有赞云支付开关
    'youzan_client_id'     => '',   // 有赞云 client_id
    'youzan_client_secret' => '',   // 有赞云 client_secret
    'kdt_id'               => '',   // 有赞云 kdt_id (授权店铺 id)

    /* ============================================================
     * 支付: TrimePay
     * ============================================================ */
    'is_trimepay'       => '0',  // TrimePay 开关
    'trimepay_appid'    => '',   // TrimePay app_id
    'trimepay_appsecret'=> '',   // TrimePay app_secret
    'pay_notify_url'    => '',   // 支付回调域名; 生成重置密码/在线支付必备, 如 https://www.ssrpanel.com

    /* ============================================================
     * 支付: 发卡 (fakapay)
     * ============================================================ */
    'fakapay'        => '',  // 发卡支付开关 (值 on / off)
    'fakapay_10url'  => '',  // 发卡 10 元链接
    'fakapay_100url' => '',  // 发卡 100 元链接

    /* ============================================================
     * 支付: CP代付 (clonepay)
     * ============================================================ */
    'clonepay'          => '',   // CP代付开关 (值 on / off)
    'clonepay_token'    => '',   // CP代付 token
    'clonepay_safeip'   => '',   // CP代付安全 IPv4
    'clonepay_safeipv6' => '',   // CP代付安全 IPv6
    'clonepay_homeurl'  => '',   // CP代付 homeurl
    'clonepay_syncurl'  => '',   // CP代付 syncurl
    'clonepay_webs'     => '',   // CP代付 webs 配置 (JSON); 开启哪些 web
    'clonepay_apis'     => '',   // CP代付 apis 配置 (JSON); web 的 apis 配置

    /* ============================================================
     * 通知: Telegram (管理员告警, 统一由 app/Services/Notification 投递)
     *   级别: error(系统故障) > warning(需关注) > info(日常事件)
     *   Telegram 默认仅推送 error; 可由 min_level 放宽
     *   .config.php 覆盖时支持只写部分键 (systemConfig 已递归合并 telegram 子键)
     * ============================================================ */
    'telegram' => [
        'enabled'   => false,   // 开关; 仅通知管理员
        'bot_token' => '',      // Bot Token (从 @BotFather 获取)
        'chat_id'   => '',      // 接收通知的 Chat ID (个人或群组)
        'min_level' => 'error', // 接受的最低级别; error(仅故障) / warning / info
    ],

    /* ============================================================
     * 验证码: 极验 Geetest / Google reCAPTCHA
     * ============================================================ */
    'geetest_id'             => '',  // 极验 ID
    'geetest_key'            => '',  // 极验 KEY
    'google_captcha_sitekey' => '',  // Google reCAPTCHA 网站密钥 (sitekey)
    'google_captcha_secret'  => '',  // Google reCAPTCHA 密钥 (secret)

    /* ============================================================
     * DNS: Namesilo
     * ============================================================ */
    'is_namesilo'  => '0',  // Namesilo 开关; 添加/编辑节点域名时自动更新 DNS 记录为节点 IP
    'namesilo_key' => '',   // Namesilo API KEY

    /* ============================================================
     * 其他
     * ============================================================ */
    'is_free_code'                  => '0',    // 免费邀请码; 关闭后免费邀请码不可见
    'is_forbid_robot'               => '0',    // 阻止机器人访问; 机器人/爬虫/代理访问抛 404
    'initial_labels_for_user'       => '',     // 用户初始标签 (逗号分隔标签 ID); 注册用户的初始标签, 用于关联节点
    'goods_purchase_limit_strategy' => 'none', // 商品限购策略 (none/package/free/package&free/all); 限制 24 小时内重复购买

    /* ============================================================
     * 流量统计 (历史滚动数据)
     * traffic_record_group1/2 由定时任务 AutoStatisticsNodeDailyTraffic 每日写入 DB,
     * 代码以白名单从 DB 读取. 此处保留空字符串仅作兜底, 实际值来自 DB.
     * ============================================================ */
    'all_traffic_daily_mark'      => '',  // 全局每日流量标记 (历史运行时数据)
    'all_traffic_daily_supply'    => '',  // 全局每日流量补充 (历史运行时数据)
    'group1_traffic_daily_mark'   => '',  // 分组1 每日流量标记 (历史运行时数据)
    'group1_traffic_daily_supply' => '',  // 分组1 每日流量补充 (历史运行时数据)
    'group2_traffic_daily_mark'   => '',  // 分组2 每日流量标记 (历史运行时数据)
    'group2_traffic_daily_supply' => '',  // 分组2 每日流量补充 (历史运行时数据)
    'traffic_record_group0'       => '',  // 分组0 流量滚动记录 (历史)
    'traffic_record_group1'       => '',  // 分组1 流量滚动记录 (定时任务每日写入, 实际值来自 DB)
    'traffic_record_group2'       => '',  // 分组2 流量滚动记录 (定时任务每日写入, 实际值来自 DB)

    /* ============================================================
     * 解锁服务 (每个服务 4 项: address / port / password / method)
     * 只有节点 node_unlock 包含对应服务时才下发. address/password 为用户自有
     * 解锁服务器, 真实值放 .config.php.
     * ============================================================ */
    'unlock_netflix_address'        => '',  'unlock_netflix_port' => '8388',  'unlock_netflix_password' => '',  'unlock_netflix_method' => 'chacha20-ietf-poly1305',  // Netflix 解锁
    'unlock_openai_address'         => '',  'unlock_openai_port'  => '8388',  'unlock_openai_password'  => '',  'unlock_openai_method'  => 'chacha20-ietf-poly1305',  // OpenAI / ChatGPT 解锁
    'unlock_disney_address'         => '',  'unlock_disney_port'  => '8388',  'unlock_disney_password'  => '',  'unlock_disney_method'  => 'chacha20-ietf-poly1305',  // Disney+ 解锁
    'unlock_tiktok_address'         => '',  'unlock_tiktok_port'  => '8388',  'unlock_tiktok_password'  => '',  'unlock_tiktok_method'  => 'chacha20-ietf-poly1305',  // TikTok 解锁
    'unlock_bahamut_address'        => '',  'unlock_bahamut_port' => '8388',  'unlock_bahamut_password' => '',  'unlock_bahamut_method' => 'chacha20-ietf-poly1305',  // 动画疯 Bahamut 解锁
    'unlock_claude_address'         => '',  'unlock_claude_port'  => '8388',  'unlock_claude_password'  => '',  'unlock_claude_method'  => 'chacha20-ietf-poly1305',  // Claude 解锁
    'unlock_google_scholar_address' => '',  'unlock_google_scholar_port'  => '8388',  'unlock_google_scholar_password' => '',  'unlock_google_scholar_method' => 'chacha20-ietf-poly1305',  // Google Scholar 解锁
];
