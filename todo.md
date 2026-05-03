- [] 白名单问题
    - 取消 名单限制功能; 
        - 更新
        - 上报
        - 删除所有 正式站点中的 所有的屏蔽掉的黑名单. 这个可以有,允许所有人注册的方式很不错.
    - 设定为黑名单比较好 , 需要删除所有的 名单数据

- [] ss_config 表,而不是 config 表, 请注意.


- bug: 检查 singbox订阅中是否正确支持了 vless-xhttp , vmess-ws 
    - why: 避免 singbox 中出现报错信息. 
    - where: @app/Http/Controllers/SubscribeController.php
    - how: 
        - 参考 @tmp/vmess.md 和 @tmp/ws.md 中对 singbox 处理 v2ray 中对 xhttp 协议 和 vmess 协议 和 ws 协议的正确描述. 
        - 参考 @tmp/vmess.md 对 xhttp协议的正确描述
            - warn: 在 sing-box 的官方语料库中，并没有 xhttp 这个名字，它对应的是 httpupgrade。
    - must : 只修改 singbox 订阅
        - 要求订阅输出格式符合 singbox 规范.
