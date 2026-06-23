when: 2026-06-22T22:30:00
where:
  - app/Http/Controllers/Api/NodeApiController.php
  - app/Http/Controllers/SubscribeController.php
  - app/Http/Models/SsNode.php
  - database/migrations/2026_06_22_000000_add_v2_reality_to_ss_node_table.php
  - resources/templates/xray/vision-reality.json
  - resources/templates/nginx/vision-reality.conf
  - tests/test_vision_reality.php
  - tests/test_vision_reality_register.php
why:
  - 新增 vision-reality 协议支持 (VLESS + XTLS-Vision + REALITY, 偷自己), 用于测试新协议
  - xray 监听 443, realitySettings.dest=127.0.0.1:8443 偷本机 nginx 8443 泛域名证书完成 TLS 握手
  - 非法探测/普通浏览器透明回落到 nginx 8443 的 AriaNg 伪装站
  - fingerprint 强制 firefox (uTLS 指纹, reality 抗 GFW 主动探测的核心伪装层)
how:
  - 存储: ss_node 新增 3 列 v2_reality_pbk(公钥)/v2_reality_private(私钥)/v2_reality_sid(8hex)
    私钥仅 xray config 读取, 公钥仅订阅层读取, shortId 两端共用. 主节点与所有 clone
    共用同一组 (同一物理节点, register 时 libsodium X25519 一次性生成).
  - register(): v2_name=vision-reality 触发生成集群共用 reality 三件套, 写入每个节点.
  - applyV2Preset(): modeName=vision-reality 时覆写 v2_fp=firefox/v2_flow=xtls-rprx-vision/
    v2_tls=1 + 写 reality 三列 (reality 复用 vision 协议槽, inbound tag=proxy-vision).
  - config(): 注入 __realityPrivateKey__/__realityShortId__/__realityServerName__ 占位符,
    serverName=节点 host (偷自己), debug 日志对 privateKey 打码.
  - nginxConfig(): 由 v2_name 自动选 vision-reality.conf 模板 (80 重定向 + 8443 ssl 回落).
  - 订阅: vless URI 输出 security=reality+pbk+sid; clash 输出 reality-opts+firefox 指纹;
    sing-box 输出 tls.reality 子块+firefox 指纹. SNI 跳过用户前缀 (必须=serverNames).
  - 参考 SPanel V2Presets/NodeConfigService + 三份订阅格式参考文档对齐.
must:
  - REALITY privateKey 永不进入订阅, pbk 永不进入 xray config (独立 DB 列隔离)
  - SNI 必须与 realitySettings.serverNames 完全一致 (偷自己证书校验), 不可加用户前缀
  - reality 节点 client-fingerprint 必须为 firefox (抗识别核心伪装层), 服务端无法强制故面板兜底
  - 主节点与所有 clone 共用同一组 reality 密钥 (host/sni 相同 = 同一物理节点)
  - PHP 7.4 兼容 (无 8+ 语法), migration 幂等 (hasColumn 检查)
  - 新建/修改文件需 chown www-data + composer dump-autoload (跨环境权限)
test:
  - tests/test_vision_reality.php (44 项: 密钥生成/配套性/applyV2Preset/config/nginx/3种订阅)
  - tests/test_vision_reality_register.php (register 端到端: 主+clone 共享密钥, 含回滚)
status: done
