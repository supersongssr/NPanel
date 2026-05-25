-- ============================================================
-- NPanel 数据库完整基准 Schema
-- 整理日期: 2026-05-18
-- 说明: 本文件为全新安装的完整数据库结构
--       所有历史 ALTER TABLE 已合并到对应的 CREATE TABLE 中
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';


-- ============================================================
-- 节点相关表
-- ============================================================

-- -----------------------------------------------------------
-- 表: ss_node | 节点信息表
-- 说明: 存储所有代理节点的完整信息，包括裂变克隆节点
-- -----------------------------------------------------------
CREATE TABLE `ss_node` (
  `id`                  INT(11) NOT NULL AUTO_INCREMENT,
  `type`                TINYINT(4) NOT NULL DEFAULT '1'       COMMENT '服务类型：1-SS、2-VMess、3-VLESS、4-Trojan、5-Hysteria2',
  `name`                VARCHAR(128) NOT NULL DEFAULT ''      COMMENT '名称',
  `v2_name`             VARCHAR(255) NOT NULL DEFAULT 'vision-hy2-ws-grpc' COMMENT '协议组合名',
  `group_id`            INT(11) NOT NULL DEFAULT '0'          COMMENT '所属分组(旧)',
  `node_group`          INT(11) NOT NULL DEFAULT '1'          COMMENT '节点分组(新)',
  `country_code`        CHAR(5) NOT NULL DEFAULT 'un'         COMMENT '国家代码',
  `node_country`        VARCHAR(64) NULL                      COMMENT '国家名',
  `node_city`           VARCHAR(64) NULL                      COMMENT '城市名',
  `server`              VARCHAR(128) NULL DEFAULT ''           COMMENT '服务器域名地址',
  `ip`                  CHAR(15) NULL DEFAULT ''               COMMENT '服务器IPv4地址',
  `ipv6`                CHAR(128) NULL DEFAULT ''              COMMENT '服务器IPv6地址',
  `desc`                VARCHAR(255) NULL DEFAULT ''           COMMENT '节点简单描述',
  `method`              VARCHAR(32) NOT NULL DEFAULT 'aes-256-cfb' COMMENT '加密方式',
  `protocol`            VARCHAR(128) NOT NULL DEFAULT 'origin' COMMENT '协议',
  `protocol_param`      VARCHAR(128) NULL DEFAULT ''           COMMENT '协议参数',
  `obfs`                VARCHAR(128) NOT NULL DEFAULT 'plain'  COMMENT '混淆',
  `obfs_param`          VARCHAR(128) NULL DEFAULT ''           COMMENT '混淆参数',
  `traffic_rate`        FLOAT NOT NULL DEFAULT '1.00'          COMMENT '流量比率',
  `bandwidth`           INT(11) NOT NULL DEFAULT '100'         COMMENT '出口带宽(M)',
  `traffic`             BIGINT(20) NOT NULL DEFAULT '1000'     COMMENT '流量',
  `traffic_limit`       BIGINT(20) NOT NULL DEFAULT '1099511627776' COMMENT '流量限制(字节, 默认1TB)',
  `traffic_lasthour`    BIGINT(20) NOT NULL DEFAULT '0'        COMMENT '1小时流量标记',
  `traffic_lastday`     BIGINT(20) NOT NULL DEFAULT '0'        COMMENT '1天流量标记',
  `traffic_used`        BIGINT(20) NOT NULL DEFAULT '0'        COMMENT '已用流量(字节)',
  `traffic_used_daily`  BIGINT(20) NOT NULL DEFAULT '0'        COMMENT '已用流量日均(字节)',
  `traffic_left_daily`  BIGINT(20) NOT NULL DEFAULT '0'        COMMENT '剩余流量日均(字节)',
  `monitor_url`         VARCHAR(255) NULL DEFAULT NULL         COMMENT '监控地址',
  `is_subscribe`        TINYINT(4) NULL DEFAULT '1'            COMMENT '允许订阅：0-否、1-是',
  `is_nat`              TINYINT(4) NOT NULL DEFAULT '0'        COMMENT 'NAT机：0-否、1-是',
  `is_transit`          TINYINT(4) NOT NULL DEFAULT '0'        COMMENT '允许CDN中转：0-否、1-是',
  `ssh_port`            SMALLINT(6) UNSIGNED NOT NULL DEFAULT '22' COMMENT 'SSH端口',
  `is_tcp_check`        TINYINT(4) NOT NULL DEFAULT '1'        COMMENT '开启检测：0-否、1-是',
  `compatible`          TINYINT(4) NOT NULL DEFAULT '0'        COMMENT '兼容SS',
  `single`              TINYINT(4) NOT NULL DEFAULT '0'        COMMENT '单端口多用户：0-否、1-是',
  `single_force`        TINYINT(4) NULL DEFAULT NULL           COMMENT '模式：0-兼容、1-严格',
  `single_port`         VARCHAR(50) NULL DEFAULT ''             COMMENT '端口号(逗号分隔)',
  `single_passwd`       VARCHAR(50) NULL DEFAULT ''             COMMENT '单端口密码',
  `single_method`       VARCHAR(50) NULL DEFAULT ''             COMMENT '单端口加密方式',
  `single_protocol`     VARCHAR(50) NOT NULL DEFAULT ''         COMMENT '单端口协议',
  `single_obfs`         VARCHAR(50) NOT NULL DEFAULT ''         COMMENT '单端口混淆',
  `sort`                INT(11) NOT NULL DEFAULT '0'            COMMENT '排序(越大越靠前)',
  `level`               INT(11) NOT NULL DEFAULT '1'            COMMENT '节点等级',
  `status`              TINYINT(4) NOT NULL DEFAULT '1'         COMMENT '状态：0-维护、1-正常',
  `node_cost`           FLOAT NOT NULL DEFAULT '5'              COMMENT '服务器成本',
  `node_online`         INT(11) NOT NULL DEFAULT '0'            COMMENT '在线人数',
  `node_onload`         FLOAT NOT NULL DEFAULT '1'              COMMENT '人数成本负载比率',
  `node_unlock`         VARCHAR(500) NOT NULL DEFAULT ''        COMMENT '媒体解锁配置',
  `info`                VARCHAR(255) NOT NULL DEFAULT ''        COMMENT '节点信息',
  `node_cpu`            INT(11) NULL                           COMMENT 'CPU核数',
  `node_memory`         FLOAT NULL                             COMMENT '内存(GB)',
  `node_disk`           FLOAT NULL                             COMMENT '磁盘(GB)',
  `node_rxtx`           VARCHAR(255) NULL DEFAULT 'tx'          COMMENT '计费方向：tx/rxtx',
  `node_health`         INT(11) NOT NULL DEFAULT '1'            COMMENT '健康度：0-超标、1-正常',
  `last_raw_total`      BIGINT(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '流量计量缓存值',
  `reset_day`           TINYINT(4) NOT NULL DEFAULT '1'         COMMENT '流量重置日(每月第N天)',
  `v2_alter_id`         INT(11) NOT NULL DEFAULT '16'           COMMENT 'V2Ray额外ID',
  `v2_port`             INT(11) NOT NULL DEFAULT '0'            COMMENT 'V2Ray端口',
  `v2_method`           VARCHAR(32) NOT NULL DEFAULT 'aes-128-gcm' COMMENT 'V2Ray加密方式',
  `v2_net`              VARCHAR(16) NOT NULL DEFAULT 'tcp'      COMMENT 'V2Ray传输协议',
  `v2_type`             VARCHAR(32) NOT NULL DEFAULT 'none'     COMMENT 'V2Ray伪装类型',
  `v2_host`             VARCHAR(255) NOT NULL DEFAULT ''        COMMENT 'V2Ray伪装域名',
  `v2_path`             VARCHAR(255) NOT NULL DEFAULT ''        COMMENT 'V2Ray WS/H2路径',
  `v2_tls`              TINYINT(4) NOT NULL DEFAULT '0'         COMMENT 'TLS：0-关、1-TLS、2-XTLS',
  `v2_flow`             VARCHAR(255) NULL                      COMMENT '流控(如xtls-rprx-vision)',
  `v2_sni`              VARCHAR(255) NULL                      COMMENT 'SNI',
  `v2_alpn`             VARCHAR(255) NULL                      COMMENT 'ALPN',
  `v2_encryption`       VARCHAR(255) NOT NULL DEFAULT 'none'    COMMENT 'VLESS加密(默认none)',
  `v2_fp`               VARCHAR(255) NOT NULL DEFAULT ''        COMMENT 'uTLS指纹',
  `v2_mode`             VARCHAR(255) NULL                      COMMENT 'gRPC模式(auto/multi)',
  `v2_servicename`      VARCHAR(255) NULL                      COMMENT 'gRPC服务名',
  `v2_cdn`              VARCHAR(255) NOT NULL DEFAULT ''        COMMENT 'CDN地址',
  `v2_cdn_ip`           VARCHAR(255) NOT NULL DEFAULT ''        COMMENT 'CDN IP',
  `v2_hop_ports`        VARCHAR(255) NULL                      COMMENT '端口跳跃范围(如 20000-50000)',
  `v2_insider_port`     INT(11) NOT NULL DEFAULT '10550'        COMMENT '内部端口(v2_port=0时有效)',
  `v2_outsider_port`    INT(11) NOT NULL DEFAULT '443'          COMMENT '外部端口(v2_port=0时有效)',
  `node_uuid`           VARCHAR(255) NOT NULL DEFAULT ''        COMMENT '独立节点UUID(无用户版)',
  `heartbeat_at`        DATETIME NULL                          COMMENT '心跳时间(后端API)',
  `server_uptime`       BIGINT(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '服务器运行时间(秒)',
  `server_total_traffic` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '服务器累计流量(字节)',
  `is_clone`            INT(11) NOT NULL DEFAULT '0'            COMMENT '克隆来源节点ID(0=主节点)',
  `node_ids`            TEXT NULL                              COMMENT '裂变矩阵: 主节点下的所有节点ID',
  `created_at`          DATETIME NOT NULL,
  `updated_at`          DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_group` (`group_id`),
  INDEX `idx_sub` (`is_subscribe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='节点信息表';


-- -----------------------------------------------------------
-- 表: ss_node_info | 节点负载信息
-- -----------------------------------------------------------
CREATE TABLE `ss_node_info` (
  `id`        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `node_id`   INT(11) NOT NULL DEFAULT '0' COMMENT '节点ID',
  `uptime`    INT(11) NOT NULL COMMENT '在线时长(秒)',
  `load`      VARCHAR(64) NOT NULL COMMENT '负载',
  `log_time`  INT(11) NOT NULL COMMENT '记录时间(Unix)',
  PRIMARY KEY (`id`),
  INDEX `idx_node_id` (`node_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='节点负载信息';


-- -----------------------------------------------------------
-- 表: ss_node_online_log | 节点在线信息
-- -----------------------------------------------------------
CREATE TABLE `ss_node_online_log` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `node_id`      INT(11) NOT NULL COMMENT '节点ID',
  `online_user`  INT(11) NOT NULL COMMENT '在线用户数',
  `log_time`     INT(11) NOT NULL COMMENT '记录时间(Unix)',
  PRIMARY KEY (`id`),
  INDEX `idx_node_id` (`node_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='节点在线信息';


-- -----------------------------------------------------------
-- 表: ss_node_label | 节点标签关联
-- -----------------------------------------------------------
CREATE TABLE `ss_node_label` (
  `id`       INT(11) NOT NULL AUTO_INCREMENT,
  `node_id`  INT(11) NOT NULL DEFAULT '0' COMMENT '节点ID',
  `label_id` INT(11) NOT NULL DEFAULT '0' COMMENT '标签ID',
  PRIMARY KEY (`id`),
  INDEX `idx_node_label` (`node_id`, `label_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='节点标签关联';


-- -----------------------------------------------------------
-- 表: ss_node_traffic_daily | 节点每日流量统计
-- -----------------------------------------------------------
CREATE TABLE `ss_node_traffic_daily` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `node_id`    INT(11) NOT NULL DEFAULT '0'   COMMENT '节点ID',
  `u`          BIGINT(20) NOT NULL DEFAULT '0' COMMENT '上传流量(字节)',
  `d`          BIGINT(20) NOT NULL DEFAULT '0' COMMENT '下载流量(字节)',
  `total`      BIGINT(20) NOT NULL DEFAULT '0' COMMENT '总流量(字节)',
  `traffic`    VARCHAR(255) DEFAULT ''         COMMENT '总流量(带单位)',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_node_id` (`node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='节点每日流量统计';


-- -----------------------------------------------------------
-- 表: ss_node_traffic_hourly | 节点每小时流量统计
-- -----------------------------------------------------------
CREATE TABLE `ss_node_traffic_hourly` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `node_id`    INT(11) NOT NULL DEFAULT '0'   COMMENT '节点ID',
  `u`          BIGINT(20) NOT NULL DEFAULT '0' COMMENT '上传流量(字节)',
  `d`          BIGINT(20) NOT NULL DEFAULT '0' COMMENT '下载流量(字节)',
  `total`      BIGINT(20) NOT NULL DEFAULT '0' COMMENT '总流量(字节)',
  `traffic`    VARCHAR(255) DEFAULT ''         COMMENT '总流量(带单位)',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_node_id` (`node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='节点每小时流量统计';


-- -----------------------------------------------------------
-- 表: ss_node_ip | 节点在线IP记录
-- -----------------------------------------------------------
CREATE TABLE `ss_node_ip` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `node_id`    INT(11) NOT NULL DEFAULT '0'              COMMENT '节点ID',
  `user_id`    INT(11) NOT NULL DEFAULT '0'              COMMENT '用户ID',
  `port`       INT(11) NOT NULL DEFAULT '0'              COMMENT '端口',
  `type`       CHAR(10) NOT NULL DEFAULT 'tcp'           COMMENT '类型：all/tcp/udp',
  `ip`         TEXT                                    COMMENT '连接IP(逗号分隔)',
  `created_at` INT(11) NOT NULL DEFAULT '0'              COMMENT '上报时间(Unix)',
  PRIMARY KEY (`id`),
  INDEX `idx_node` (`node_id`),
  INDEX `idx_port` (`port`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='节点在线IP';


-- -----------------------------------------------------------
-- 表: ss_node_deny | 节点访问规则关联
-- -----------------------------------------------------------
CREATE TABLE `ss_node_deny` (
  `id`      INT(11) NOT NULL AUTO_INCREMENT,
  `node_id` INT(11) NOT NULL DEFAULT '0' COMMENT '节点ID',
  `rule_id` INT(11) NOT NULL DEFAULT '0' COMMENT '规则ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='节点访问规则关联';


-- -----------------------------------------------------------
-- 表: dns_records | DNS记录(本地缓存+Cloudflare同步)
-- 新增: 2026-04-27
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dns_records` (
  `id`            INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `node_id`       INT(11) UNSIGNED NOT NULL     COMMENT '关联节点ID',
  `root_domain`   VARCHAR(255) NOT NULL         COMMENT '根域名(如 example.com)',
  `subdomain`     VARCHAR(255) NOT NULL         COMMENT '子域名前缀(如 n1, ipv6n2)',
  `record_type`   VARCHAR(8) NOT NULL           COMMENT '记录类型：A / AAAA',
  `ip_addr`       VARCHAR(255) NOT NULL         COMMENT 'IP地址',
  `cf_record_id`  VARCHAR(255) NULL             COMMENT 'Cloudflare记录ID',
  `proxied`       TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否开启CF代理(橙色云)',
  `created_at`    TIMESTAMP NULL,
  `updated_at`    TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  INDEX `dns_records_node_id_index` (`node_id`),
  INDEX `dns_root_domain_index` (`root_domain`),
  UNIQUE KEY `dns_unique_record` (`subdomain`(64), `root_domain`(64), `record_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COLLATE=utf8mb4_unicode_ci COMMENT='DNS记录(本地缓存+Cloudflare同步)';


-- ============================================================
-- 用户相关表
-- ============================================================

-- -----------------------------------------------------------
-- 表: user | 用户
-- -----------------------------------------------------------
CREATE TABLE `user` (
  `id`                   INT(11) NOT NULL AUTO_INCREMENT,
  `username`             VARCHAR(128) NOT NULL DEFAULT ''         COMMENT '用户名(邮箱)',
  `password`             VARCHAR(64) NOT NULL DEFAULT ''          COMMENT '密码(bcrypt)',
  `port`                 INT(11) NOT NULL DEFAULT '0'             COMMENT '代理端口',
  `passwd`               VARCHAR(16) NOT NULL DEFAULT ''          COMMENT '代理密码',
  `vmess_id`             VARCHAR(64) NOT NULL DEFAULT ''          COMMENT 'V2Ray用户ID(UUID)',
  `transfer_enable`      BIGINT(20) NOT NULL DEFAULT '1099511627776' COMMENT '可用流量(字节, 默认1TiB)',
  `transfer_monthly`     BIGINT(20) NOT NULL DEFAULT '0'          COMMENT '每月套餐流量(字节)',
  `u`                    BIGINT(20) NOT NULL DEFAULT '0'          COMMENT '已上传(字节)',
  `d`                    BIGINT(20) NOT NULL DEFAULT '0'          COMMENT '已下载(字节)',
  `t`                    INT(11) NOT NULL DEFAULT '0'             COMMENT '最后使用时间(Unix)',
  `traffic_lasthour`     BIGINT(20) NOT NULL DEFAULT '0'          COMMENT '前1小时流量(字节)',
  `traffic_lastday`      BIGINT(20) NOT NULL DEFAULT '0'          COMMENT '前1天流量(字节)',
  `enable`               TINYINT(4) NOT NULL DEFAULT '1'          COMMENT '代理状态：0-禁、1-启',
  `method`               VARCHAR(30) NOT NULL DEFAULT 'aes-256-cfb' COMMENT '加密方式',
  `protocol`             VARCHAR(30) NOT NULL DEFAULT 'origin'    COMMENT '协议',
  `protocol_param`       VARCHAR(255) DEFAULT ''                  COMMENT '协议参数',
  `obfs`                 VARCHAR(30) NOT NULL DEFAULT 'plain'     COMMENT '混淆',
  `obfs_param`           VARCHAR(255) DEFAULT ''                  COMMENT '混淆参数',
  `speed_limit_per_con`  BIGINT(20) NOT NULL DEFAULT '10737418240' COMMENT '单连接限速(字节, 0=不限, 默认10G)',
  `speed_limit_per_user` BIGINT(20) NOT NULL DEFAULT '10737418240' COMMENT '单用户限速(字节, 0=不限, 默认10G)',
  `gender`               TINYINT(4) NOT NULL DEFAULT '1'          COMMENT '性别：0-女、1-男',
  `wechat`               VARCHAR(128) DEFAULT ''                  COMMENT '微信',
  `alipay`               VARCHAR(128) DEFAULT '0'                 COMMENT '支付宝',
  `qq`                   VARCHAR(20) DEFAULT ''                   COMMENT 'QQ',
  `usdt`                 VARCHAR(128) DEFAULT '0'                 COMMENT 'USDT钱包',
  `usage`                VARCHAR(10) NOT NULL DEFAULT '4'         COMMENT '用途：1-手机、2-电脑、3-路由器、4-其他',
  `pay_way`              TINYINT(4) NOT NULL DEFAULT '0'          COMMENT '付费方式：0-免费、1-季付、2-月付、3-半年付、4-年付',
  `balance`              INT(11) NOT NULL DEFAULT '0'             COMMENT '余额(分)',
  `credit`               INT(11) NOT NULL DEFAULT '0'             COMMENT '信用额度(分)',
  `credit_days`          TINYINT(4) NOT NULL DEFAULT '0'          COMMENT '延迟还款天数',
  `enable_time`          DATE DEFAULT NULL                        COMMENT '开通日期',
  `expire_time`          DATE NOT NULL DEFAULT '2099-01-01'       COMMENT '过期时间',
  `ban_time`             INT(11) NOT NULL DEFAULT '0'             COMMENT '封禁到期时间(Unix)',
  `remark`               TEXT                                    COMMENT '备注',
  `level`                TINYINT(4) NOT NULL DEFAULT '1'          COMMENT '等级',
  `is_admin`             TINYINT(4) NOT NULL DEFAULT '0'          COMMENT '管理员：0-否、1-是',
  `node_group`           INT(11) NOT NULL DEFAULT '0'             COMMENT '分组',
  `reg_ip`               VARCHAR(20) NOT NULL DEFAULT '127.0.0.1' COMMENT '注册IP',
  `last_login`           INT(11) NOT NULL DEFAULT '0'             COMMENT '最后登录时间(Unix)',
  `referral_uid`         INT(11) NOT NULL DEFAULT '0'             COMMENT '邀请人ID',
  `traffic_reset_day`    TINYINT(4) NOT NULL DEFAULT '0'          COMMENT '流量重置日(0=不重置)',
  `status`               TINYINT(4) NOT NULL DEFAULT '0'          COMMENT '状态：-1-禁用、0-未激活、1-正常',
  `remember_token`       VARCHAR(256) DEFAULT ''                  COMMENT '记住登录Token',
  `rss_ip`               VARCHAR(64) DEFAULT NULL                 COMMENT '订阅IP',
  `cncdn`                VARCHAR(64) DEFAULT '0'                  COMMENT 'CN自选入口',
  `cncdn_count`          INT(11) DEFAULT '0'                      COMMENT 'CN次数统计',
  `cfcdn`                VARCHAR(64) DEFAULT '0'                  COMMENT 'CF自选IP',
  `cfcdn_count`          INT(11) DEFAULT '0'                      COMMENT 'CF次数统计',
  `ban_times`            INT(11) NOT NULL DEFAULT '0'             COMMENT '封禁次数',
  `created_at`           DATETIME DEFAULT NULL,
  `updated_at`           DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `unq_username` (`username`),
  INDEX `idx_search` (`enable`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户';

-- 默认管理员
INSERT INTO `user` (`id`, `username`, `password`, `port`, `passwd`, `vmess_id`, `transfer_enable`, `u`, `d`, `t`, `enable`, `method`, `protocol`, `protocol_param`, `obfs`, `obfs_param`, `speed_limit_per_con`, `speed_limit_per_user`, `wechat`, `qq`, `usage`, `pay_way`, `balance`, `enable_time`, `expire_time`, `remark`, `is_admin`, `reg_ip`, `status`, `created_at`, `updated_at`)
VALUES (1, 'admin', '$2y$10$ryMdx5ejvCSdjvZVZAPpOuxHrsAUY8FEINUATy6RCck6j9EeHhPfq', 10000, '@123', 'c6effafd-6046-7a84-376e-b0429751c304', 1099511627776, 0, 0, 0, 1, 'aes-256-cfb', 'origin', '', 'plain', '', 204800, 204800, '', '', 1, 3, 0.00, '2017-01-01', '2099-01-01', NULL, 1, '127.0.0.1', 1, NOW(), NOW());


-- -----------------------------------------------------------
-- 表: level | 等级定义
-- -----------------------------------------------------------
CREATE TABLE `level` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `level`      INT(11) NOT NULL DEFAULT '1' COMMENT '等级值',
  `level_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '等级名称',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='等级定义';

INSERT INTO `level` (`id`, `level`, `level_name`) VALUES
  (1, 1, '普通用户'),
  (2, 2, 'VIP1'),
  (3, 3, 'VIP2'),
  (4, 4, 'VIP3');


-- -----------------------------------------------------------
-- 表: user_traffic_log | 用户流量日志
-- -----------------------------------------------------------
CREATE TABLE `user_traffic_log` (
  `id`       INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`  INT(11) NOT NULL DEFAULT '0' COMMENT '用户ID',
  `u`        INT(11) NOT NULL DEFAULT '0' COMMENT '上传流量(字节)',
  `d`        INT(11) NOT NULL DEFAULT '0' COMMENT '下载流量(字节)',
  `node_id`  INT(11) NOT NULL DEFAULT '0' COMMENT '节点ID',
  `rate`     FLOAT NOT NULL               COMMENT '流量比例',
  `traffic`  VARCHAR(32) NOT NULL         COMMENT '产生流量(带单位)',
  `log_time` INT(11) NOT NULL             COMMENT '记录时间(Unix)',
  PRIMARY KEY (`id`),
  INDEX `idx_user_node_time` (`user_id`, `node_id`, `log_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户流量日志';


-- -----------------------------------------------------------
-- 表: user_traffic_daily | 用户每日流量统计
-- -----------------------------------------------------------
CREATE TABLE `user_traffic_daily` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL DEFAULT '0'   COMMENT '用户ID(0=全部)',
  `node_id`    INT(11) NOT NULL DEFAULT '0'   COMMENT '节点ID(0=全部)',
  `u`          BIGINT(20) NOT NULL DEFAULT '0' COMMENT '上传(字节)',
  `d`          BIGINT(20) NOT NULL DEFAULT '0' COMMENT '下载(字节)',
  `total`      BIGINT(20) NOT NULL DEFAULT '0' COMMENT '总流量(字节)',
  `traffic`    VARCHAR(255) DEFAULT ''         COMMENT '总流量(带单位)',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_user_node` (`user_id`, `node_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户每日流量统计';


-- -----------------------------------------------------------
-- 表: user_traffic_hourly | 用户每小时流量统计
-- -----------------------------------------------------------
CREATE TABLE `user_traffic_hourly` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL DEFAULT '0'   COMMENT '用户ID(0=全部)',
  `node_id`    INT(11) NOT NULL DEFAULT '0'   COMMENT '节点ID(0=全部)',
  `u`          BIGINT(20) NOT NULL DEFAULT '0' COMMENT '上传(字节)',
  `d`          BIGINT(20) NOT NULL DEFAULT '0' COMMENT '下载(字节)',
  `total`      BIGINT(20) NOT NULL DEFAULT '0' COMMENT '总流量(字节)',
  `traffic`    VARCHAR(255) DEFAULT ''         COMMENT '总流量(带单位)',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_user_node` (`user_id`, `node_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户每小时流量统计';


-- -----------------------------------------------------------
-- 表: user_traffic_modify_log | 用户流量变动日志
-- -----------------------------------------------------------
CREATE TABLE `user_traffic_modify_log` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL DEFAULT '0'       COMMENT '用户ID',
  `order_id`   INT(11) NOT NULL DEFAULT '0'       COMMENT '关联订单ID',
  `before`     BIGINT(20) NOT NULL DEFAULT '0'    COMMENT '操作前流量(字节)',
  `after`      BIGINT(20) NOT NULL DEFAULT '0'    COMMENT '操作后流量(字节)',
  `desc`       VARCHAR(255) NOT NULL DEFAULT ''   COMMENT '描述',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户流量变动日志';


-- -----------------------------------------------------------
-- 表: user_balance_log | 用户余额变动日志
-- -----------------------------------------------------------
CREATE TABLE `user_balance_log` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL DEFAULT '0'   COMMENT '用户ID',
  `order_id`   INT(11) NOT NULL DEFAULT '0'   COMMENT '关联订单ID',
  `coupon_id`  INT(11) DEFAULT NULL           COMMENT '充值券ID',
  `before`     INT(11) NOT NULL DEFAULT '0'   COMMENT '变动前余额(分)',
  `after`      INT(11) NOT NULL DEFAULT '0'   COMMENT '变动后余额(分)',
  `amount`     INT(11) NOT NULL DEFAULT '0'   COMMENT '变动金额(分)',
  `desc`       VARCHAR(255) DEFAULT ''        COMMENT '描述',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户余额变动日志';


-- -----------------------------------------------------------
-- 表: user_ban_log | 用户封禁日志
-- -----------------------------------------------------------
CREATE TABLE `user_ban_log` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL DEFAULT '0'       COMMENT '用户ID',
  `minutes`    INT(11) NOT NULL DEFAULT '0'       COMMENT '封禁时长(分钟)',
  `desc`       VARCHAR(255) NOT NULL DEFAULT ''   COMMENT '描述',
  `status`     TINYINT(4) NOT NULL DEFAULT '0'    COMMENT '状态：0-未处理、1-已处理',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户封禁日志';


-- -----------------------------------------------------------
-- 表: user_label | 用户标签关联
-- -----------------------------------------------------------
CREATE TABLE `user_label` (
  `id`       INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`  INT(11) NOT NULL DEFAULT '0' COMMENT '用户ID',
  `label_id` INT(11) NOT NULL DEFAULT '0' COMMENT '标签ID',
  PRIMARY KEY (`id`),
  INDEX `idx_user_label` (`user_id`, `label_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户标签关联';


-- -----------------------------------------------------------
-- 表: user_subscribe | 用户订阅
-- -----------------------------------------------------------
CREATE TABLE `user_subscribe` (
  `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11) NOT NULL DEFAULT '0' COMMENT '用户ID',
  `code`        CHAR(5) DEFAULT ''            COMMENT '订阅唯一码',
  `times`       INT(11) NOT NULL DEFAULT '0'  COMMENT '总请求次数',
  `times_today` INT(11) NOT NULL DEFAULT '0'  COMMENT '今日请求次数',
  `status`      TINYINT(4) NOT NULL DEFAULT '1' COMMENT '状态：0-禁、1-启',
  `ban_time`    INT(11) NOT NULL DEFAULT '0'  COMMENT '封禁时间(Unix)',
  `ban_desc`    VARCHAR(50) NOT NULL DEFAULT '' COMMENT '封禁理由',
  `created_at`  DATETIME DEFAULT NULL,
  `updated_at`  DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `user_id` (`user_id`, `status`),
  INDEX `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户订阅';

INSERT INTO `user_subscribe` (`id`, `user_id`, `code`) VALUES (1, 1, 'SsXa1');


-- -----------------------------------------------------------
-- 表: user_subscribe_log | 用户订阅访问日志
-- -----------------------------------------------------------
CREATE TABLE `user_subscribe_log` (
  `id`             INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sid`            INT(11) DEFAULT NULL COMMENT '关联user_subscribe.id',
  `request_ip`     VARCHAR(20) DEFAULT NULL COMMENT '请求IP',
  `request_time`   DATETIME DEFAULT NULL    COMMENT '请求时间',
  `request_header` TEXT                     COMMENT '请求头',
  PRIMARY KEY (`id`),
  INDEX `sid` (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户订阅访问日志';


-- -----------------------------------------------------------
-- 表: user_login_log | 用户登录日志
-- -----------------------------------------------------------
CREATE TABLE `user_login_log` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL DEFAULT '0' COMMENT '用户ID',
  `ip`         CHAR(20) NOT NULL            COMMENT '登录IP',
  `country`    CHAR(20) NOT NULL            COMMENT '国家',
  `province`   CHAR(20) NOT NULL            COMMENT '省份',
  `city`       CHAR(20) NOT NULL            COMMENT '城市',
  `county`     CHAR(20) NOT NULL            COMMENT '区县',
  `isp`        CHAR(20) NOT NULL            COMMENT '运营商',
  `area`       CHAR(20) NOT NULL            COMMENT '地区',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户登录日志';


-- -----------------------------------------------------------
-- 表: invite | 邀请码
-- -----------------------------------------------------------
CREATE TABLE `invite` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `uid`        INT(11) NOT NULL DEFAULT '0'       COMMENT '邀请人ID',
  `fuid`       INT(11) NOT NULL DEFAULT '0'       COMMENT '受邀人ID',
  `code`       CHAR(32) NOT NULL                  COMMENT '邀请码',
  `status`     TINYINT(4) NOT NULL DEFAULT '0'    COMMENT '状态：0-未使用、1-已使用、2-已过期',
  `dateline`   DATETIME DEFAULT NULL              COMMENT '有效期至',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL              COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请码';


-- -----------------------------------------------------------
-- 表: verify | 账号激活校验
-- -----------------------------------------------------------
CREATE TABLE `verify` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `type`       TINYINT(4) NOT NULL DEFAULT '1' COMMENT '类型：1-自行激活、2-管理员激活',
  `user_id`    INT(11) NOT NULL                COMMENT '用户ID',
  `token`      VARCHAR(32) NOT NULL            COMMENT '校验Token',
  `status`     TINYINT(4) NOT NULL DEFAULT '0' COMMENT '状态：0-未使用、1-已使用、2-已失效',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='账号激活校验';


-- -----------------------------------------------------------
-- 表: verify_code | 注册验证码
-- -----------------------------------------------------------
CREATE TABLE `verify_code` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(128) NOT NULL            COMMENT '用户邮箱',
  `code`       CHAR(6) NOT NULL                 COMMENT '验证码',
  `status`     TINYINT(4) NOT NULL DEFAULT '0'  COMMENT '状态：0-未使用、1-已使用、2-已失效',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='注册验证码';


-- -----------------------------------------------------------
-- 表: referral_apply | 提现申请
-- -----------------------------------------------------------
CREATE TABLE `referral_apply` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL DEFAULT '0'       COMMENT '用户ID',
  `before`     INT(11) NOT NULL DEFAULT '0'       COMMENT '操作前可提现(分)',
  `after`      INT(11) NOT NULL DEFAULT '0'       COMMENT '操作后可提现(分)',
  `amount`     INT(11) NOT NULL DEFAULT '0'       COMMENT '本次提现(分)',
  `link_logs`  TEXT DEFAULT NULL                  COMMENT '关联返利日志ID(如1,3,4)',
  `status`     TINYINT(4) NOT NULL DEFAULT '0'    COMMENT '状态：-2-驳回换方式、-1-驳回、0-待审、1-通过待打款、2-已打款、3-代金券、4-微信、5-支付宝、6-USDT',
  `wechat`     VARCHAR(128) DEFAULT NULL          COMMENT '微信',
  `alipay`     VARCHAR(128) DEFAULT NULL          COMMENT '支付宝',
  `qq`         VARCHAR(20) DEFAULT NULL           COMMENT 'QQ',
  `usdt`       VARCHAR(128) DEFAULT NULL          COMMENT 'USDT',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='提现申请';


-- -----------------------------------------------------------
-- 表: referral_log | 消费返利日志
-- -----------------------------------------------------------
CREATE TABLE `referral_log` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11) NOT NULL DEFAULT '0'   COMMENT '用户ID',
  `ref_user_id` INT(11) NOT NULL DEFAULT '0'   COMMENT '推广人ID',
  `order_id`    INT(11) NOT NULL DEFAULT '0'   COMMENT '关联订单ID',
  `amount`      INT(11) NOT NULL DEFAULT '0'   COMMENT '消费金额(分)',
  `ref_amount`  INT(11) NOT NULL DEFAULT '0'   COMMENT '返利金额(分)',
  `status`      TINYINT(4) NOT NULL DEFAULT '0' COMMENT '状态：0-未提现、1-审核中、2-已提现、3-代金券、4-微信、5-支付宝、6-USDT',
  `created_at`  DATETIME DEFAULT NULL,
  `updated_at`  DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消费返利日志';


-- ============================================================
-- 商品 / 订单 / 支付相关表
-- ============================================================

-- -----------------------------------------------------------
-- 表: goods | 商品
-- -----------------------------------------------------------
CREATE TABLE `goods` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `sku`        VARCHAR(15) NOT NULL DEFAULT ''      COMMENT '商品SKU',
  `name`       VARCHAR(100) NOT NULL DEFAULT ''     COMMENT '商品名称',
  `logo`       VARCHAR(255) NOT NULL DEFAULT ''     COMMENT '图片地址',
  `traffic`    BIGINT(20) NOT NULL DEFAULT '0'      COMMENT '内含流量(MiB)',
  `type`       TINYINT(4) NOT NULL DEFAULT '1'      COMMENT '类型：1-流量包、2-套餐、3-余额充值',
  `price`      INT(11) NOT NULL DEFAULT '0'         COMMENT '售价(分)',
  `desc`       VARCHAR(255) DEFAULT ''              COMMENT '描述',
  `days`       INT(11) NOT NULL DEFAULT '30'        COMMENT '有效期(天)',
  `color`      VARCHAR(50) NOT NULL DEFAULT 'green' COMMENT '显示颜色',
  `sort`       INT(11) NOT NULL DEFAULT '0'         COMMENT '排序',
  `level`      INT(11) NOT NULL DEFAULT '1'         COMMENT '等级',
  `is_limit`   TINYINT(4) NOT NULL DEFAULT '0'      COMMENT '限购：0-否、1-是',
  `is_hot`     TINYINT(4) NOT NULL DEFAULT '0'      COMMENT '热销：0-否、1-是',
  `status`     TINYINT(4) NOT NULL DEFAULT '1'      COMMENT '状态：0-下架、1-上架',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL                COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品';


-- -----------------------------------------------------------
-- 表: goods_label | 商品标签关联
-- -----------------------------------------------------------
CREATE TABLE `goods_label` (
  `id`       INT(11) NOT NULL AUTO_INCREMENT,
  `goods_id` INT(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `label_id` INT(11) NOT NULL DEFAULT '0' COMMENT '标签ID',
  PRIMARY KEY (`id`),
  INDEX `idx_goods_label` (`goods_id`, `label_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品标签关联';


-- -----------------------------------------------------------
-- 表: coupon | 优惠券
-- -----------------------------------------------------------
CREATE TABLE `coupon` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(50) NOT NULL              COMMENT '优惠券名称',
  `logo`            VARCHAR(255) NOT NULL DEFAULT ''  COMMENT 'LOGO',
  `sn`              VARCHAR(64) NOT NULL DEFAULT ''   COMMENT '券码',
  `type`            TINYINT(4) NOT NULL DEFAULT '1'   COMMENT '类型：1-现金券、2-折扣券、3-充值券',
  `usage`           TINYINT(4) NOT NULL DEFAULT '1'   COMMENT '用途：1-一次性、2-可重复',
  `amount`          BIGINT(20) NOT NULL DEFAULT '0'   COMMENT '金额(分)',
  `discount`        DECIMAL(10,2) NOT NULL DEFAULT '0.00' COMMENT '折扣',
  `available_start` INT(11) NOT NULL DEFAULT '0'     COMMENT '有效期开始(Unix)',
  `available_end`   INT(11) NOT NULL DEFAULT '0'     COMMENT '有效期结束(Unix)',
  `status`          TINYINT(4) NOT NULL DEFAULT '1'  COMMENT '状态：0-未使用、1-已使用、2-已失效',
  `user_id`         INT(11) DEFAULT NULL             COMMENT '使用者ID',
  `creat_user`      INT(11) NOT NULL DEFAULT '0'     COMMENT '创建者用户ID',
  `created_at`      DATETIME DEFAULT NULL,
  `updated_at`      DATETIME DEFAULT NULL,
  `deleted_at`      DATETIME DEFAULT NULL             COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='优惠券';


-- -----------------------------------------------------------
-- 表: coupon_log | 优惠券使用日志
-- -----------------------------------------------------------
CREATE TABLE `coupon_log` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `coupon_id`  INT(11) NOT NULL DEFAULT '0' COMMENT '优惠券ID',
  `goods_id`   INT(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `order_id`   INT(11) NOT NULL DEFAULT '0' COMMENT '订单ID',
  `desc`       VARCHAR(50) NOT NULL DEFAULT '' COMMENT '备注',
  `user_id`    INT(11) DEFAULT NULL          COMMENT '使用者ID',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='优惠券使用日志';


-- -----------------------------------------------------------
-- 表: order | 订单
-- -----------------------------------------------------------
CREATE TABLE `order` (
  `oid`           INT(11) NOT NULL AUTO_INCREMENT,
  `order_sn`      VARCHAR(50) NOT NULL DEFAULT ''      COMMENT '订单编号',
  `user_id`       INT(11) NOT NULL DEFAULT '0'         COMMENT '用户ID',
  `goods_id`      INT(11) NOT NULL DEFAULT '0'         COMMENT '商品ID',
  `coupon_id`     INT(11) NOT NULL DEFAULT '0'         COMMENT '优惠券ID',
  `email`         VARCHAR(255) DEFAULT NULL            COMMENT '邮箱',
  `origin_amount` INT(11) NOT NULL DEFAULT '0'         COMMENT '原始总价(分)',
  `amount`        INT(11) NOT NULL DEFAULT '0'         COMMENT '实付总价(分)',
  `expire_at`     DATETIME DEFAULT NULL                COMMENT '过期时间',
  `is_expire`     TINYINT(4) NOT NULL DEFAULT '0'      COMMENT '已过期：0-否、1-是',
  `pay_way`       TINYINT(4) NOT NULL DEFAULT '1'      COMMENT '支付方式：1-余额、2-有赞云',
  `status`        TINYINT(4) NOT NULL DEFAULT '0'      COMMENT '状态：-1-关闭、0-待支付、1-待确认、2-已完成',
  `created_at`    DATETIME DEFAULT NULL,
  `updated_at`    DATETIME DEFAULT NULL,
  PRIMARY KEY (`oid`),
  INDEX `idx_order_search` (`user_id`, `goods_id`, `is_expire`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单';


-- -----------------------------------------------------------
-- 表: order_goods | 订单商品明细
-- -----------------------------------------------------------
CREATE TABLE `order_goods` (
  `id`           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `oid`          INT(11) NOT NULL DEFAULT '0' COMMENT '订单ID',
  `order_sn`     VARCHAR(20) NOT NULL DEFAULT '' COMMENT '订单编号',
  `user_id`      INT(11) NOT NULL DEFAULT '0' COMMENT '用户ID',
  `goods_id`     INT(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `num`          INT(11) NOT NULL DEFAULT '0' COMMENT '数量',
  `origin_price` INT(11) NOT NULL DEFAULT '0' COMMENT '原价(分)',
  `price`        INT(11) NOT NULL DEFAULT '0' COMMENT '实付价(分)',
  `is_expire`    TINYINT(4) NOT NULL DEFAULT '0' COMMENT '已过期：0-否、1-是',
  `created_at`   DATETIME DEFAULT NULL,
  `updated_at`   DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单商品明细';


-- -----------------------------------------------------------
-- 表: payment | 支付单
-- -----------------------------------------------------------
CREATE TABLE `payment` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `sn`            VARCHAR(50) DEFAULT NULL             COMMENT '支付单编号',
  `user_id`       INT(11) NOT NULL                     COMMENT '用户ID',
  `oid`           INT(11) DEFAULT NULL                 COMMENT '本地订单ID',
  `order_sn`      VARCHAR(50) DEFAULT NULL             COMMENT '订单编号',
  `pay_way`       TINYINT(4) NOT NULL DEFAULT '1'      COMMENT '方式：1-微信、2-支付宝',
  `amount`        INT(11) NOT NULL DEFAULT '0'         COMMENT '金额(分)',
  `qr_id`         INT(11) NOT NULL DEFAULT '0'         COMMENT '支付单ID(有赞)',
  `qr_url`        VARCHAR(255) DEFAULT NULL            COMMENT '二维码URL',
  `qr_code`       TEXT                                 COMMENT '二维码Base64',
  `qr_local_url`  VARCHAR(255) DEFAULT NULL            COMMENT '二维码本地URL',
  `status`        INT(11) NOT NULL DEFAULT '0'         COMMENT '状态：-1-失败、0-等待、1-成功',
  `created_at`    DATETIME NOT NULL,
  `updated_at`    DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付单';


-- -----------------------------------------------------------
-- 表: payment_callback | 有赞云回调日志
-- -----------------------------------------------------------
CREATE TABLE `payment_callback` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id`  VARCHAR(50) DEFAULT NULL COMMENT '客户端ID',
  `yz_id`      VARCHAR(50) DEFAULT NULL COMMENT '有赞ID',
  `kdt_id`     VARCHAR(50) DEFAULT NULL COMMENT '店铺ID',
  `kdt_name`   VARCHAR(50) DEFAULT NULL COMMENT '店铺名',
  `mode`       TINYINT(4) DEFAULT NULL  COMMENT '模式',
  `msg`        TEXT                      COMMENT '消息',
  `sendCount`  INT(11) DEFAULT NULL     COMMENT '发送次数',
  `sign`       VARCHAR(32) DEFAULT NULL COMMENT '签名',
  `status`     VARCHAR(30) DEFAULT NULL COMMENT '状态',
  `test`       TINYINT(4) DEFAULT NULL  COMMENT '测试',
  `type`       VARCHAR(50) DEFAULT NULL COMMENT '类型',
  `version`    VARCHAR(50) DEFAULT NULL COMMENT '版本',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='有赞云回调日志';


-- ============================================================
-- 工单 / 邮件 / 通知相关表
-- ============================================================

-- -----------------------------------------------------------
-- 表: ticket | 工单
-- -----------------------------------------------------------
CREATE TABLE `ticket` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL DEFAULT '0'      COMMENT '用户ID',
  `sort`       BIGINT(20) NOT NULL DEFAULT '0'   COMMENT '等级排序',
  `title`      VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '标题',
  `content`    TEXT NOT NULL                     COMMENT '内容',
  `status`     TINYINT(4) NOT NULL DEFAULT '0'   COMMENT '状态：0-待处理、1-已处理、2-已关闭',
  `open`       INT(11) NOT NULL DEFAULT '0'      COMMENT '公开工单：0-否、1-是',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工单';


-- -----------------------------------------------------------
-- 表: ticket_reply | 工单回复
-- -----------------------------------------------------------
CREATE TABLE `ticket_reply` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id`  INT(11) NOT NULL DEFAULT '0' COMMENT '工单ID',
  `user_id`    INT(11) NOT NULL             COMMENT '回复人ID',
  `content`    TEXT NOT NULL                COMMENT '回复内容',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工单回复';


-- -----------------------------------------------------------
-- 表: email_log | 邮件投递记录
-- -----------------------------------------------------------
CREATE TABLE `email_log` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`       TINYINT(4) NOT NULL DEFAULT '1'   COMMENT '类型：1-邮件、2-ServerChan',
  `address`    VARCHAR(255) NOT NULL             COMMENT '收信地址',
  `title`      VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '标题',
  `content`    TEXT NOT NULL                     COMMENT '内容',
  `status`     TINYINT(4) NOT NULL DEFAULT '0'   COMMENT '状态：-1-失败、0-等待、1-成功',
  `error`      TEXT                              COMMENT '异常信息',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邮件投递记录';


-- -----------------------------------------------------------
-- 表: marketing | 营销
-- -----------------------------------------------------------
CREATE TABLE `marketing` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`       TINYINT(4) NOT NULL               COMMENT '类型：1-邮件群发、2-订阅渠道群发',
  `receiver`   TEXT NOT NULL                     COMMENT '接收者',
  `title`      VARCHAR(255) NOT NULL             COMMENT '标题',
  `content`    TEXT NOT NULL                     COMMENT '内容',
  `error`      VARCHAR(255) DEFAULT NULL         COMMENT '错误信息',
  `status`     TINYINT(4) NOT NULL               COMMENT '状态：-1-失败、0-待发送、1-成功',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='营销';


-- ============================================================
-- 配置 / 字典 / 分组相关表
-- ============================================================

-- -----------------------------------------------------------
-- 表: config | 系统配置(KV)
-- 说明: name=键, value=值, comment=备注
-- -----------------------------------------------------------
CREATE TABLE `config` (
  `id`      INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`    VARCHAR(255) NOT NULL DEFAULT '' COMMENT '配置名',
  `value`   TEXT NULL                        COMMENT '配置值',
  `comment` TEXT                             COMMENT '备注',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置';

-- --- 网站基础 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('website_name', 'SSRPanel'),
  ('website_url', 'https://www.ssrpanel.com'),
  ('website_logo', ''),
  ('website_home_logo', ''),
  ('website_analytics', ''),
  ('website_customer_service', ''),
  ('website_security_code', '');

-- --- 注册 / 登录 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('is_register', '1'),
  ('is_invite_register', '2'),
  ('is_active_register', '1'),
  ('active_times', '3'),
  ('is_reset_password', '1'),
  ('reset_password_times', '3'),
  ('is_captcha', '0'),
  ('is_verify_register', '0'),
  ('register_ip_limit', '5'),
  ('is_ban_status', '0');

-- --- 邀请 / 推荐 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('invite_num', '3'),
  ('referral_traffic', '1024'),
  ('referral_percent', '0.2'),
  ('referral_money', '100'),
  ('referral_status', '1'),
  ('user_invite_days', '7'),
  ('admin_invite_days', '7');

-- --- 流量 / 端口 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('default_traffic', '1024'),
  ('default_days', '7'),
  ('reset_traffic', '1'),
  ('traffic_warning', '0'),
  ('traffic_warning_percent', '80'),
  ('expire_warning', '0'),
  ('expire_days', '15'),
  ('is_traffic_ban', '1'),
  ('traffic_ban_value', '10'),
  ('traffic_ban_time', '60'),
  ('traffic_limit_time', '1440'),
  ('is_rand_port', '0'),
  ('is_user_rand_port', '0'),
  ('min_port', '10000'),
  ('max_port', '20000'),
  ('auto_release_port', '1');

-- --- 签到 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('is_checkin', '1'),
  ('min_rand_traffic', '10'),
  ('max_rand_traffic', '500');

-- --- 订阅 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('subscribe_max', '3'),
  ('subscribe_domain', ''),
  ('subscribe_domains', '[]'),
  ('is_subscribe_ban', '1'),
  ('subscribe_ban_times', '20'),
  ('mix_subscribe', '0'),
  ('rand_subscribe', '0'),
  ('is_custom_subscribe', '0'),
  ('sub_rss_url', '');

-- --- 节点 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('is_clear_log', '1'),
  ('is_node_crash_warning', '0'),
  ('crash_warning_email', ''),
  ('is_tcp_check', '0'),
  ('tcp_check_warning_times', '3'),
  ('node_daily_report', '0'),
  ('is_forbid_china', '0'),
  ('is_forbid_oversea', '0');

-- --- 支付: 支付宝 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('is_alipay', '0'),
  ('alipay_sign_type', 'MD5'),
  ('alipay_partner', ''),
  ('alipay_key', ''),
  ('alipay_private_key', ''),
  ('alipay_public_key', ''),
  ('alipay_transport', 'http'),
  ('alipay_currency', 'USD');

-- --- 支付: F2FPay ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('is_f2fpay', '0'),
  ('f2fpay_app_id', ''),
  ('f2fpay_private_key', ''),
  ('f2fpay_public_key', ''),
  ('f2fpay_subject_name', '');

-- --- 支付: PayPal ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('paypal_status', '0'),
  ('paypal_client_id', ''),
  ('paypal_client_secret', '');

-- --- 支付: 有赞云 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('is_youzan', '0'),
  ('youzan_client_id', ''),
  ('youzan_client_secret', ''),
  ('kdt_id', '');

-- --- 支付: TrimePay ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('is_trimepay', '0'),
  ('trimepay_appid', ''),
  ('trimepay_appsecret', ''),
  ('pay_notify_url', '');

-- --- 支付: 发卡 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('fakapay', ''),
  ('fakapay_10url', ''),
  ('fakapay_100url', '');

-- --- 支付: CP代付 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('clonepay', ''),
  ('clonepay_token', ''),
  ('clonepay_safeip', ''),
  ('clonepay_safeipv6', ''),
  ('clonepay_homeurl', ''),
  ('clonepay_syncurl', ''),
  ('clonepay_webs', ''),
  ('clonepay_apis', '');

-- --- 通知: ServerChan / PushBear / Telegram ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('is_server_chan', '0'),
  ('server_chan_key', ''),
  ('is_push_bear', '0'),
  ('push_bear_send_key', ''),
  ('push_bear_qrcode', ''),
  ('is_telegram', '0'),
  ('telegram_bot_token', ''),
  ('telegram_chat_id', '');

-- --- 验证码 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('geetest_id', ''),
  ('geetest_key', ''),
  ('google_captcha_sitekey', ''),
  ('google_captcha_secret', '');

-- --- 其他 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('is_free_code', '0'),
  ('is_forbid_robot', '0'),
  ('is_namesilo', '0'),
  ('namesilo_key', ''),
  ('wechat_qrcode', ''),
  ('alipay_qrcode', ''),
  ('initial_labels_for_user', ''),
  ('goods_purchase_limit_strategy', 'none');

-- --- 流量统计 ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('all_traffic_daily_mark', ''),
  ('all_traffic_daily_supply', ''),
  ('group1_traffic_daily_mark', ''),
  ('group1_traffic_daily_supply', ''),
  ('group2_traffic_daily_mark', ''),
  ('group2_traffic_daily_supply', ''),
  ('traffic_record_group0', ''),
  ('traffic_record_group1', ''),
  ('traffic_record_group2', '');

-- --- 节点 API (2026-04 ~ 2026-05 新增) ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('node_domain_pool', '[]'),
  ('node_protocol_presets', '{"threshold_mb":2048,"high":"xhttp-hy2-ws-grpc","low":"vision-hy2-ws-grpc"}'),
  ('host_pools', '{}'),
  ('pow_base_difficulty', '10000');

-- --- 解锁服务 (每个服务4项: address/port/password/method) ---
INSERT INTO `config` (`name`, `value`) VALUES
  ('unlock_netflix_address', ''),   ('unlock_netflix_port', '8388'),   ('unlock_netflix_password', ''),   ('unlock_netflix_method', 'chacha20-ietf-poly1305'),
  ('unlock_openai_address', ''),    ('unlock_openai_port', '8388'),    ('unlock_openai_password', ''),    ('unlock_openai_method', 'chacha20-ietf-poly1305'),
  ('unlock_disney_address', ''),    ('unlock_disney_port', '8388'),    ('unlock_disney_password', ''),    ('unlock_disney_method', 'chacha20-ietf-poly1305'),
  ('unlock_tiktok_address', ''),    ('unlock_tiktok_port', '8388'),    ('unlock_tiktok_password', ''),    ('unlock_tiktok_method', 'chacha20-ietf-poly1305'),
  ('unlock_bahamut_address', ''),   ('unlock_bahamut_port', '8388'),   ('unlock_bahamut_password', ''),   ('unlock_bahamut_method', 'chacha20-ietf-poly1305'),
  ('unlock_claude_address', ''),    ('unlock_claude_port', '8388'),    ('unlock_claude_password', ''),    ('unlock_claude_method', 'chacha20-ietf-poly1305'),
  ('unlock_google_scholar_address', ''), ('unlock_google_scholar_port', '8388'), ('unlock_google_scholar_password', ''), ('unlock_google_scholar_method', 'chacha20-ietf-poly1305');


-- -----------------------------------------------------------
-- 表: ss_config | 通用配置(加密方式/协议/混淆字典)
-- -----------------------------------------------------------
CREATE TABLE `ss_config` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(50) NOT NULL DEFAULT ''       COMMENT '配置名',
  `type`       TINYINT(4) NOT NULL DEFAULT '1'       COMMENT '类型：1-加密方式、2-协议、3-混淆',
  `is_default` TINYINT(4) NOT NULL DEFAULT '0'       COMMENT '默认：0-否、1-是',
  `sort`       INT(11) NOT NULL DEFAULT '0'          COMMENT '排序(越大越前)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通用配置(字典)';

-- 加密方式 (type=1)
INSERT INTO `ss_config` (`id`, `name`, `type`, `is_default`, `sort`) VALUES
  (1,  'none',                    1, 0, 0),
  (2,  'rc4',                     1, 0, 0),
  (3,  'rc4-md5',                 1, 0, 0),
  (4,  'rc4-md5-6',               1, 0, 0),
  (5,  'bf-cfb',                  1, 0, 0),
  (6,  'aes-128-cfb',             1, 0, 0),
  (7,  'aes-192-cfb',             1, 0, 0),
  (8,  'aes-256-cfb',             1, 1, 0),
  (9,  'aes-128-ctr',             1, 0, 0),
  (10, 'aes-192-ctr',             1, 0, 0),
  (11, 'aes-256-ctr',             1, 0, 0),
  (12, 'camellia-128-cfb',        1, 0, 0),
  (13, 'camellia-192-cfb',        1, 0, 0),
  (14, 'camellia-256-cfb',        1, 0, 0),
  (15, 'salsa20',                 1, 0, 0),
  (16, 'xsalsa20',                1, 0, 0),
  (17, 'chacha20',                1, 0, 0),
  (18, 'xchacha20',               1, 0, 0),
  (19, 'chacha20-ietf',           1, 0, 0),
  (20, 'chacha20-ietf-poly1305',  1, 0, 0),
  (21, 'chacha20-poly1305',       1, 0, 0),
  (22, 'xchacha-ietf-poly1305',   1, 0, 0),
  (23, 'aes-128-gcm',             1, 0, 0),
  (24, 'aes-192-gcm',             1, 0, 0),
  (25, 'aes-256-gcm',             1, 0, 0),
  (26, 'sodium-aes-256-gcm',      1, 0, 0);

-- 协议 (type=2)
INSERT INTO `ss_config` (`id`, `name`, `type`, `is_default`, `sort`) VALUES
  (27, 'origin',          2, 1, 0),
  (28, 'auth_sha1_v4',    2, 0, 0),
  (29, 'auth_aes128_md5', 2, 0, 0),
  (30, 'auth_aes128_sha1', 2, 0, 0),
  (31, 'auth_chain_a',    2, 0, 0),
  (32, 'auth_chain_b',    2, 0, 0),
  (38, 'auth_chain_c',    2, 0, 0),
  (39, 'auth_chain_d',    2, 0, 0),
  (40, 'auth_chain_e',    2, 0, 0),
  (41, 'auth_chain_f',    2, 0, 0);

-- 混淆 (type=3)
INSERT INTO `ss_config` (`id`, `name`, `type`, `is_default`, `sort`) VALUES
  (33, 'plain',                  3, 1, 0),
  (34, 'http_simple',            3, 0, 0),
  (35, 'http_post',              3, 0, 0),
  (36, 'tls1.2_ticket_auth',     3, 0, 0),
  (37, 'tls1.2_ticket_fastauth', 3, 0, 0);


-- -----------------------------------------------------------
-- 表: label | 标签
-- -----------------------------------------------------------
CREATE TABLE `label` (
  `id`   INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '名称',
  `sort` INT(11) NOT NULL DEFAULT '0'     COMMENT '排序',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='标签';

INSERT INTO `label` (`id`, `name`, `sort`) VALUES
  (1, '电信', 0),
  (2, '联通', 0),
  (3, '移动', 0),
  (4, '教育网', 0),
  (5, '其他网络', 0),
  (6, '免费体验', 0);


-- -----------------------------------------------------------
-- 表: level | 等级 (已在前方定义)
-- -----------------------------------------------------------


-- -----------------------------------------------------------
-- 表: ss_group | 节点分组
-- -----------------------------------------------------------
CREATE TABLE `ss_group` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(50) NOT NULL        COMMENT '分组名称',
  `level`      TINYINT(4) NOT NULL DEFAULT '1' COMMENT '分组级别',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='节点分组';


-- -----------------------------------------------------------
-- 表: ss_group_node | 分组节点关联
-- -----------------------------------------------------------
CREATE TABLE `ss_group_node` (
  `id`       INT(11) NOT NULL AUTO_INCREMENT,
  `group_id` INT(11) NOT NULL DEFAULT '0' COMMENT '分组ID',
  `node_id`  INT(11) NOT NULL DEFAULT '0' COMMENT '节点ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分组节点关联';


-- -----------------------------------------------------------
-- 表: country | 国家代码
-- -----------------------------------------------------------
CREATE TABLE `country` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `country_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '名称',
  `country_code` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '代码',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家代码';

INSERT INTO `country` (`id`, `country_name`, `country_code`) VALUES
  (1,  '澳大利亚',   'au'), (2,  '巴西',     'br'), (3,  '加拿大',   'ca'),
  (4,  '瑞士',       'ch'), (5,  '中国',     'cn'), (6,  '德国',     'de'),
  (7,  '丹麦',       'dk'), (8,  '埃及',     'eg'), (9,  '法国',     'fr'),
  (10, '希腊',       'gr'), (11, '香港',     'hk'), (12, '印度尼西亚', 'id'),
  (13, '爱尔兰',     'ie'), (14, '以色列',   'il'), (15, '印度',     'in'),
  (16, '伊拉克',     'iq'), (17, '伊朗',     'ir'), (18, '意大利',   'it'),
  (19, '日本',       'jp'), (20, '韩国',     'kr'), (21, '墨西哥',   'mx'),
  (22, '马来西亚',   'my'), (23, '荷兰',     'nl'), (24, '挪威',     'no'),
  (25, '纽西兰',     'nz'), (26, '菲律宾',   'ph'), (27, '俄罗斯',   'ru'),
  (28, '瑞典',       'se'), (29, '新加坡',   'sg'), (30, '泰国',     'th'),
  (31, '土耳其',     'tr'), (32, '台湾',     'tw'), (33, '英国',     'uk'),
  (34, '美国',       'us'), (35, '越南',     'vn'), (36, '波兰',     'pl'),
  (37, '哈萨克斯坦', 'kz'), (38, '乌克兰',   'ua'), (39, '罗马尼亚', 'ro'),
  (40, '阿联酋',     'ae'), (41, '南非',     'za'), (42, '缅甸',     'mm'),
  (43, '冰岛',       'is'), (44, '芬兰',     'fi'), (45, '卢森堡',   'lu'),
  (46, '比利时',     'be'), (47, '保加利亚', 'bg'), (48, '立陶宛',   'lt'),
  (49, '哥伦比亚',   'co'), (50, '澳门',     'mo'), (51, '肯尼亚',   'ke'),
  (52, '捷克',       'cz'), (53, '摩尔多瓦', 'md'), (54, '西班牙',   'es'),
  (55, '巴基斯坦',   'pk'), (56, '葡萄牙',   'pt'), (57, '匈牙利',   'hu'),
  (58, '阿根廷',     'ar');


-- -----------------------------------------------------------
-- 表: rule | 规则表
-- -----------------------------------------------------------
CREATE TABLE `rule` (
  `id`      INT(11) NOT NULL AUTO_INCREMENT,
  `type`    CHAR(10) NOT NULL DEFAULT 'domain' COMMENT '类型：domain/ipv4/ipv6/reg',
  `regular` VARCHAR(255) NOT NULL              COMMENT '规则内容',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='规则表';


-- -----------------------------------------------------------
-- 表: sensitive_words | 敏感词(临时邮箱域名)
-- -----------------------------------------------------------
CREATE TABLE `sensitive_words` (
  `id`    INT(11) NOT NULL AUTO_INCREMENT,
  `words` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '敏感词',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='敏感词(临时邮箱)';

INSERT INTO `sensitive_words` (`words`) VALUES
  ('chacuo.com'), ('chacuo.net'), ('1766258.com'), ('3202.com'),
  ('4057.com'), ('4059.com'), ('a7996.com'), ('bccto.me'),
  ('bnuis.com'), ('chaichuang.com'), ('cr219.com'), ('cuirushi.org'),
  ('dawin.com'), ('jiaxin8736.com'), ('lakqs.com'), ('urltc.com'),
  ('027168.com'), ('10minutemail.net'), ('11163.com'), ('1shivom.com'),
  ('auoie.com'), ('bareed.ws'), ('bit-degree.com'), ('cjpeg.com'),
  ('cool.fr.nf'), ('courriel.fr.nf'), ('disbox.net'), ('disbox.org'),
  ('fidelium10.com'), ('get365.pw'), ('ggr.la'), ('grr.la'),
  ('guerrillamail.biz'), ('guerrillamail.com'), ('guerrillamail.de'),
  ('guerrillamail.net'), ('guerrillamail.org'), ('guerrillamailblock.com'),
  ('hubii-network.com'), ('hurify1.com'), ('itoup.com'), ('jetable.fr.nf'),
  ('jnpayy.com'), ('juyouxi.com'), ('mail.bccto.me'), ('www.bccto.me'),
  ('mega.zik.dj'), ('moakt.co'), ('moakt.ws'), ('molms.com'),
  ('moncourrier.fr.nf'), ('monemail.fr.nf'), ('monmail.fr.nf'),
  ('nomail.xl.cx'), ('nospam.ze.tc'), ('pay-mon.com'), ('poly-swarm.com'),
  ('sgmh.online'), ('sharklasers.com'), ('shiftrpg.com'), ('spam4.me'),
  ('speed.1s.fr'), ('tmail.ws'), ('tmails.net'), ('tmpmail.net'),
  ('tmpmail.org'), ('travala10.com'), ('yopmail.com'), ('yopmail.fr'),
  ('yopmail.net'), ('yuoia.com'), ('zep-hyr.com'), ('zippiex.com'),
  ('lrc8.com'), ('1otc.com'), ('emailna.co'), ('mailinator.com'),
  ('nbzmr.com'), ('awsoo.com'), ('zhcne.com'), ('0box.eu'),
  ('contbay.com'), ('damnthespam.com'), ('kurzepost.de'), ('objectmail.com'),
  ('proxymail.eu'), ('rcpt.at'), ('trash-mail.at'), ('trashmail.at'),
  ('trashmail.com'), ('trashmail.io'), ('trashmail.me'), ('trashmail.net'),
  ('wegwerfmail.de'), ('wegwerfmail.net'), ('wegwerfmail.org'),
  ('nwytg.net'), ('despam.it'), ('spambox.us'), ('spam.la'),
  ('mytrashmail.com'), ('mt2014.com'), ('mt2015.com'),
  ('thankyou2010.com'), ('trash2009.com'), ('mt2009.com'),
  ('trashymail.com'), ('tempemail.net'), ('slopsbox.com'),
  ('mailnesia.com'), ('ezehe.com'), ('tempail.com'), ('newairmail.com'),
  ('temp-mail.org'), ('linshiyouxiang.net'), ('zwoho.com'), ('mailboxy.fun');


-- -----------------------------------------------------------
-- 表: article | 文章/公告/教程
-- -----------------------------------------------------------
CREATE TABLE `article` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(100) NOT NULL DEFAULT ''     COMMENT '标题',
  `author`     VARCHAR(50) DEFAULT ''               COMMENT '作者',
  `summary`    VARCHAR(255) DEFAULT ''              COMMENT '简介',
  `logo`       VARCHAR(255) DEFAULT ''              COMMENT 'LOGO',
  `content`    TEXT                                 COMMENT '内容',
  `type`       TINYINT(4) DEFAULT '1'               COMMENT '类型：1-文章、2-公告、3-购买说明、4-使用教程',
  `sort`       INT(11) NOT NULL DEFAULT '0'         COMMENT '排序',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL                COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章/公告/教程';

INSERT INTO `article` (`title`, `author`, `content`, `type`, `sort`) VALUES
  ('购买说明', '管理员',
   '<h4>购买流程：</h4><ol class=" list-paddingleft-2"><li><p>第一步：先购买基础套餐。</p></li><li><p>第二步：按需求，选择是否购买流量包。</p></li></ol><h4>基础套餐：</h4><ol class=" list-paddingleft-2"><li><p>在套餐生效的时间内，您将获得「套餐对应的网络速度」、「套餐内相应的流量」及其它特权。</p></li><li><p>基础套餐每月将会重置一次流量，重置日为购买日。</p></li><li><p>如在套餐未到期的情况下购买新套餐，则会导致旧套餐的所有配置立即失效，新套餐的配置立即生效。</p></li></ol><h4>流量包：</h4><ol class=" list-paddingleft-2"><li><p>当您在基础套餐重置日之前将流量耗尽，您可以选择购买流量包解燃眉之急。</p></li><li><p>流量包只在固定时间内增加可用流量，不会更改账户的配置，并且即时生效可以多个叠加。</p></li></ol>',
   3, 0);


-- -----------------------------------------------------------
-- 表: device | 设备型号
-- -----------------------------------------------------------
CREATE TABLE `device` (
  `id`       INT(11) NOT NULL AUTO_INCREMENT,
  `type`     TINYINT(4) NOT NULL DEFAULT '1'  COMMENT '类型：0-兼容、1-SS(R)、2-V2Ray',
  `platform` TINYINT(4) NOT NULL DEFAULT '1'  COMMENT '平台：0-其他、1-iOS、2-Android、3-Mac、4-Windows、5-Linux',
  `name`     VARCHAR(50) NOT NULL             COMMENT '设备名称',
  `status`   TINYINT(4) NOT NULL DEFAULT '1'  COMMENT '允许订阅：0-否、1-是',
  `header`   VARCHAR(100) NOT NULL            COMMENT '请求头识别码',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备型号';

INSERT INTO `device` (`id`, `type`, `platform`, `name`, `status`, `header`) VALUES
  (1,  1, 1, 'Quantumult',          1, 'Quantumult'),
  (2,  1, 1, 'Shadowrocket',        1, 'Shadowrocket'),
  (3,  1, 3, 'ShadowsocksX-NG-R',   1, 'ShadowsocksX-NG-R'),
  (4,  1, 1, 'Pepi',                1, 'Pepi'),
  (5,  1, 1, 'Potatso 2',           1, 'Potatso'),
  (6,  1, 1, 'Potatso Lite',        1, 'Potatso'),
  (7,  1, 4, 'ShadowsocksR',        1, 'ShadowsocksR'),
  (8,  2, 4, 'V2RayW',              1, 'V2RayW'),
  (9,  2, 4, 'V2RayN',              1, 'V2RayN'),
  (10, 2, 4, 'V2RayS',              1, 'V2RayS'),
  (11, 2, 4, 'Clash for Windows',   1, 'Clash'),
  (12, 2, 3, 'V2RayX',              1, 'V2RayX'),
  (13, 2, 3, 'V2RayU',              1, 'V2RayU'),
  (14, 2, 3, 'V2RayC',              1, 'V2RayC'),
  (15, 2, 3, 'ClashX',              1, 'ClashX'),
  (16, 2, 1, 'Kitsunebi',           1, 'Kitsunebi'),
  (17, 2, 1, 'Kitsunebi Lite',      1, 'Kitsunebi'),
  (18, 2, 1, 'i2Ray',               1, 'i2Ray'),
  (19, 2, 2, 'BifrostV',            1, 'BifrostV'),
  (20, 2, 2, 'V2RayNG',             1, 'V2RayNG'),
  (21, 2, 2, 'ShadowsocksR',        1, 'okhttp'),
  (22, 2, 2, 'SSRR',                1, 'okhttp');


-- -----------------------------------------------------------
-- 表: cncdn | CN CDN 自选节点
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cncdn` (
  `id`      INT(11) NOT NULL AUTO_INCREMENT,
  `area`    VARCHAR(128) NOT NULL COMMENT '地区',
  `areaid`  VARCHAR(128) NOT NULL COMMENT '地区编号',
  `server`  VARCHAR(64) NOT NULL  COMMENT '域名',
  `cdnip`   VARCHAR(64) NOT NULL  COMMENT 'CDN IP',
  `ipmd5`   VARCHAR(64) NOT NULL  COMMENT 'IP MD5',
  `host`    VARCHAR(64) NOT NULL  COMMENT '解析域名',
  `show`    INT(11) NOT NULL DEFAULT '1' COMMENT '展示：1-是、0-否',
  `status`  INT(11) NOT NULL DEFAULT '1' COMMENT '启用：1-是、0-否',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='CN CDN自选节点';


-- ============================================================
-- Laravel 队列 / 迁移相关表
-- ============================================================

-- -----------------------------------------------------------
-- 表: jobs | 队列任务
-- -----------------------------------------------------------
CREATE TABLE `jobs` (
  `id`           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue`        VARCHAR(255) NOT NULL  COMMENT '队列名',
  `payload`      LONGTEXT NOT NULL      COMMENT '任务数据',
  `attempts`     TINYINT(3) UNSIGNED NOT NULL COMMENT '重试次数',
  `reserved_at`  INT(10) UNSIGNED DEFAULT NULL COMMENT '保留时间',
  `available_at` INT(10) UNSIGNED NOT NULL COMMENT '可用时间',
  `created_at`   INT(10) UNSIGNED NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='队列任务';


-- -----------------------------------------------------------
-- 表: failed_jobs | 失败任务
-- -----------------------------------------------------------
CREATE TABLE `failed_jobs` (
  `id`         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `connection` TEXT NOT NULL COMMENT '连接',
  `queue`      TEXT NOT NULL COMMENT '队列',
  `payload`    LONGTEXT NOT NULL COMMENT '数据',
  `exception`  LONGTEXT NOT NULL COMMENT '异常',
  `failed_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '失败时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='失败任务';


-- -----------------------------------------------------------
-- 表: migrations | 迁移记录
-- -----------------------------------------------------------
CREATE TABLE `migrations` (
  `id`        INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL COMMENT '迁移文件名',
  `batch`     INT(11) NOT NULL COMMENT '批次',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='迁移记录';


SET FOREIGN_KEY_CHECKS = 1;
