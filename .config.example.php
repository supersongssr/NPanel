<?php
/**
 * .config.example.php — 用户配置覆盖模板
 *
 * 使用方法: 复制为 .config.php 后手工填写真实值.
 *   cp .config.example.php .config.php
 *
 * 规则:
 *   - .config.php 已加入 .gitignore, 不会提交, 代码也不会修改此文件 (仅手工编辑).
 *   - 此处出现的同名 key 会整体覆盖 config.default.php 的默认值.
 *   - 结构与 config.default.php 一致: 扁平 [name => value], value 为字符串.
 *
 * 覆盖优先级: DB(仅 traffic_record_group1/2 等动态项) > .config.php > config.default.php
 */
return [
    // 网站基础
    //'website_name' => '我的面板',
    //'website_url'  => 'https://my-panel.com',

    // 节点域名池 (JSON 字符串; 含 cf_token 等敏感数据, 仅在此填写)
    //'node_domain_pool' => '{"example.com":{"provider":"cloudflare","records_limit":1000,"zone_id":"xxxx","expire_date":"2099-12-31","cdn":false}}',

    // 支付密钥等敏感项
    //'is_alipay'        => '1',
    //'alipay_partner'   => '2088xxxxxxxx',
    //'alipay_private_key' => '...',
];
