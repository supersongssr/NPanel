<?php

/**
 * v2 后端节点 API(/api/v2/backend/*, xray-plugin-api 节点插件 ↔ 面板)相关配置。
 *
 * 与 nodeapi.php 同理收敛 env() → config: 运行时直读 env() 在 config:cache 后
 * 恒返回 null, 安全相关取值会静默回退默认; 经 config() 读取对缓存免疫,
 * 改动 .env 后需重建 config 缓存(php artisan config:cache)。
 */
return [
    // BackendRateLimit 每窗口(60s)最大请求数, 按 REMOTE_ADDR 计数。
    // 默认 60 的口径基于「一个节点一个 id、一台机器一个独立出口 IP」的部署前提
    // (单节点每轮 3 请求, 即使分钟级上报周期也远低于阈值); 多节点共享单一出口
    // IP 的形态出现时, 调高本值即可(如 600), 无需改代码。
    'rate_limit' => (int)env('BACKEND_API_RATE_LIMIT', 60),
];
