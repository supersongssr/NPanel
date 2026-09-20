<?php

/**
 * 节点配置下发(/api/node/config)相关开关。
 *
 * 收敛 env() → config 的原因: 控制器在运行时直接 env(NODE_API_*) 的话,
 * 部署侧一旦执行 php artisan config:cache, 运行时 env() 恒返回 null ——
 * 安全开关会静默失效(KEEP 回滚被忽略 / DROP 收尾不生效 / BASE_URL 静默
 * 回退 app.url)。收敛到 config 文件后, 值在缓存构建时固化, 控制器读
 * config() 始终正确; 改动 .env 后需重建 config 缓存(config:cache)。
 */
return [
    // /api/node/* 的全局共享 key (env API_TOKEN, 节点装机/心跳鉴权).
    // 收敛动机同下: 控制器/中间件运行时 env() 在 config:cache 后恒为 null,
    // 全部节点接口会 401; 经 config 读取后对 config:cache 免疫。
    // (PingController 的旧路由仍直读 env, 未在本收敛范围。)
    'api_token' => env('API_TOKEN'),

    // 旧式 ssrpanel 段直连库凭据 (config() 下发占位符 __db*__ 的来源):
    // DB_REMOTE_HOST 为节点侧直连库地址 (面板与节点异构网络时与 DB_HOST 不同),
    // 其余与面板自身同源。config:cache 后运行时 env() 恒为 null, 会把回退
    // 默认值 (127.0.0.1/root/空密码) 填进下发给旧插件的配置, 故一并收敛。
    'db_host' => env('DB_REMOTE_HOST', env('DB_HOST', '127.0.0.1')),
    'db_user' => env('DB_USERNAME', 'root'),
    'db_password' => env('DB_PASSWORD', ''),
    'db_name' => env('DB_DATABASE', 'npanel'),

    // panelApi.api.baseURL 的面板地址: 专用域名优先(NODE_API_BASE_URL), 回退 app.url
    'panel_base_url' => env('NODE_API_BASE_URL'),

    // 回滚开关: true 时已签发 api_token 的节点恢复双段下发(保留 ssrpanel/DB 凭据),
    // 供临时换回旧直连库插件; 默认 false —— 已走 HTTP API 的节点不再持有库凭据
    'keep_mysql_credentials' => env('NODE_API_KEEP_MYSQL_CREDENTIALS', false),

    // Phase 4 收尾开关: true 时删除 ssrpanel 段, 全网不再下发 DB 凭据。
    // 仅对已签发 api_token(有 panelApi 数据源)的节点摘除; 未签发节点保守保留
    // 并记 warning —— 否则其新旧插件均无用户数据源, 用户同步会静默冻结。
    'drop_mysql_credentials' => env('NODE_API_DROP_MYSQL_CREDENTIALS', false),
];
