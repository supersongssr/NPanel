api 开发计划
====

在 app/Http/Controllers/Api 开发 api v2 ; 用于接收 node 上报的配置信息, 和 node上报的 心跳包 和 日常数据 以及获取 可用的节点id
node上报的配置信息参考 @app/Http/Controllers/Api/PingController.php: ssn_v2 ; node上报的每小时信息参考 @app/Http/Controllers/Api/PingController.php ssn_sub




## 技术栈

@.links/nodehub:v2 branch 脚本中心
@.links/nodehub-api 是 nodehubapi中心
## 模块
api-v2:
    获取可用的节点id:
        返回一个 超过30天没有心跳或数据上报或活动的节点的id
    节点的配置信息:
        更新节点的配置信息
    节点的日常信息:
        接收节点的流量变动信息
        status 变动
        订阅变动 等

## 工作流


## 