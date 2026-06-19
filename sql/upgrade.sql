-- ============================================================
-- NPanel 增量升级 SQL
-- 适用: 从 db.sql 基准版本(2017 ~ 2026-04-17) 升级到最新版本
-- 整理日期: 2026-05-18
-- 环境: 测试环境
-- ============================================================
--
-- 使用说明:
--   1. 执行前请备份数据库
--   2. 直接在 MySQL 客户端 source 本文件即可
--   3. 本脚本为结果式: 跳过中间态，直接变到最终 schema
--   4. 旧字段(cpu/memory/disk/billing_mode/rxtx_mode/health)先 DROP 再 ADD 新字段
--
-- ============================================================


-- ============================================================
-- 步骤 1: ss_node 表 — 删除旧字段
-- 说明: 先删掉即将被替换的旧字段和废弃字段
-- ============================================================

ALTER TABLE `ss_node` DROP COLUMN `cpu`;
ALTER TABLE `ss_node` DROP COLUMN `memory`;
ALTER TABLE `ss_node` DROP COLUMN `disk`;
ALTER TABLE `ss_node` DROP COLUMN `billing_mode`;
ALTER TABLE `ss_node` DROP COLUMN `rxtx_mode`;
ALTER TABLE `ss_node` DROP COLUMN `health`;


-- ============================================================
-- 步骤 2: ss_node 表 — 新增字段
-- ============================================================

ALTER TABLE `ss_node` ADD COLUMN `v2_name` VARCHAR(255) NOT NULL DEFAULT 'vision-hy2-ws-grpc' COMMENT '协议组合名' AFTER `name`;
ALTER TABLE `ss_node` ADD COLUMN `node_country` VARCHAR(64) NULL COMMENT '国家名' AFTER `country_code`;
ALTER TABLE `ss_node` ADD COLUMN `node_city` VARCHAR(64) NULL COMMENT '城市名' AFTER `node_country`;
ALTER TABLE `ss_node` ADD COLUMN `node_cpu` INT(11) NULL COMMENT 'CPU核数' AFTER `info`;
ALTER TABLE `ss_node` ADD COLUMN `node_memory` FLOAT NULL COMMENT '内存(GB)' AFTER `node_cpu`;
ALTER TABLE `ss_node` ADD COLUMN `node_disk` FLOAT NULL COMMENT '磁盘(GB)' AFTER `node_memory`;
ALTER TABLE `ss_node` ADD COLUMN `node_rxtx` VARCHAR(255) NULL DEFAULT 'tx' COMMENT '计费方向: tx/rxtx' AFTER `node_disk`;
ALTER TABLE `ss_node` ADD COLUMN `node_health` INT(11) NOT NULL DEFAULT 1 COMMENT '健康度: 0-超标、1-正常' AFTER `node_rxtx`;
ALTER TABLE `ss_node` ADD COLUMN `last_raw_total` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '流量计量缓存值' AFTER `node_health`;
ALTER TABLE `ss_node` ADD COLUMN `node_ids` TEXT NULL COMMENT '裂变矩阵节点ID列表' AFTER `is_clone`;
ALTER TABLE `ss_node` ADD COLUMN `v2_hop_ports` VARCHAR(255) NULL COMMENT '端口跳跃范围(如 20000-50000)' AFTER `v2_cdn_ip`;
ALTER TABLE `ss_node` ADD COLUMN `v2_xhttp_verify` VARCHAR(64) NULL COMMENT 'xhttp-verify模式随机校验token(UUID v4),对应Xhttp-Verify header' AFTER `v2_path`;


-- ============================================================
-- 步骤 3: 创建 dns_records 表 (最终形态)
-- ============================================================

CREATE TABLE IF NOT EXISTS `dns_records` (
  `id`            INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `node_id`       INT(11) UNSIGNED NOT NULL     COMMENT '关联节点ID',
  `root_domain`   VARCHAR(255) NOT NULL         COMMENT '根域名',
  `subdomain`     VARCHAR(255) NOT NULL         COMMENT '子域名前缀',
  `record_type`   VARCHAR(8) NOT NULL           COMMENT '记录类型: A / AAAA',
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
-- 步骤 4: config 表新增配置项
-- ============================================================


INSERT INTO `config` (`name`, `value`) VALUES ('node_domain_pool', '[]');
INSERT INTO `config` (`name`, `value`) VALUES ('node_protocol_presets', '{"threshold_mb":2048,"high":"xhttp-hy2-ws-grpc","low":"vision-hy2-ws-grpc"}');
INSERT INTO `config` (`name`, `value`) VALUES ('host_pools', '{}');
INSERT INTO `config` (`name`, `value`) VALUES ('pow_base_difficulty', '10000');

-- 解锁服务配置 (每个服务4项: address/port/password/method)
INSERT INTO `config` (`name`, `value`) VALUES
  ('unlock_netflix_address', ''),   ('unlock_netflix_port', '8388'),   ('unlock_netflix_password', ''),   ('unlock_netflix_method', 'chacha20-ietf-poly1305'),
  ('unlock_openai_address', ''),    ('unlock_openai_port', '8388'),    ('unlock_openai_password', ''),    ('unlock_openai_method', 'chacha20-ietf-poly1305'),
  ('unlock_disney_address', ''),    ('unlock_disney_port', '8388'),    ('unlock_disney_password', ''),    ('unlock_disney_method', 'chacha20-ietf-poly1305'),
  ('unlock_tiktok_address', ''),    ('unlock_tiktok_port', '8388'),    ('unlock_tiktok_password', ''),    ('unlock_tiktok_method', 'chacha20-ietf-poly1305'),
  ('unlock_bahamut_address', ''),   ('unlock_bahamut_port', '8388'),   ('unlock_bahamut_password', ''),   ('unlock_bahamut_method', 'chacha20-ietf-poly1305'),
  ('unlock_claude_address', ''),    ('unlock_claude_port', '8388'),    ('unlock_claude_password', ''),    ('unlock_claude_method', 'chacha20-ietf-poly1305'),
  ('unlock_google_scholar_address', ''), ('unlock_google_scholar_port', '8388'), ('unlock_google_scholar_password', ''), ('unlock_google_scholar_method', 'chacha20-ietf-poly1305');

-- ============================================================
-- 步骤 5: 多订阅地址配置项
-- 说明: 用户订阅页面展示多个不同网络的订阅地址，自动检测可用性
-- ============================================================

INSERT INTO `config` (`name`, `value`) VALUES ('subscribe_domains', '[]');
