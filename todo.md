

# 5. Version 5


## 5.1 Add new 

- [] 为 模块 生成 @AI/modules 说明
  where: @AI
  why: 方便AI了解 项目的架构信息
  how:
    参考 @CLAUDE.md 生成 模块的 modules readme.md 和 yaml 
  must:

- [x] 格式化生成 readme.md 
  完成的内容：

    1. 项目概述 - 清晰说明了 NPanel 是一个基于 Laravel 的 SSR/V2Ray/Trojan 代理管理面板
    2. 技术栈 - 详细列出了前后端技术和主要扩展包
    3. 项目结构 - 提供了完整的目录结构树状图，标注了各文件和目录的作用
    4. 数据结构 - 使用 YAML 格式描述了核心数据表（节点、用户、套餐、支付等）
    5. 模块划分 - 详细说明了8个主要功能模块：
      - 用户认证模块
      - 用户管理模块
      - 节点管理模块
      - 订阅服务模块
      - 商店系统模块
      - 支付系统模块
      - API 接口模块
      - 工单系统模块
    6. 安装部署 - 提供了完整的安装步骤，包括环境要求、依赖安装、数据库导入、权限设置和 Nginx 配置
    7. API 文档 - 包含了主要接口的请求和响应示例
    8. 测试说明 - 根据 CLAUDE.md 中的测试配置添加了测试流程
    9. 开发规范 - 引用了 CLAUDE.md 文件
    10. 其他信息 - 贡献指南、许可证信息和支持方式

    同时按照项目规范，在 AI/updates/ 目录下创建了更新记录文件 2024-12-18_22-06.yaml。

- [x] test脚本请直接使用 @.env 文件 , 并且在远程 ```ssh root@test.srp``` 运行脚本
  where: /tests
  why: 方便在远程真实环境中进行测试.远程服务器就是 test服务器. 
  how:
    1. 修改 tests/ 所有test的 使用的 .env 为 根目录下的 @.env 
  must:
    1. 必须在 远程 ```ssh root@test.srp``` 运行 测试脚本,本地不支持运行测试脚本! 

- [x] 给 api 添加自动测试
  where: @app/Http/Controllers/Api/PingController.php
  why: 测试 api 是否正常返回需要的值
  how:
    在 tests/目录下给 api 写一个测试脚本 
    api的地址和 token 怎么处理呢? 从 .env 读取 API_TOKEN  和 TEST_API_URL 
  must:

- [x] 添加新的api, 返回 v2_host 
  where: @app/Http/Controllers/Api/PingController.php
  why: 添加一个快速获取节点的配置信息的api,可以用于查询节点的配置信息
  how:
    1. 添加一个api, 
    2. 请求中附带 node_id 
    3. 返回json, 其中带有 v2_host 
  must:



- [x] 订阅节点,显示倍率

- [x] N panel , 流量用超以后,可以允许用户自助解封. 
  - check how to limit user
    - status : -1 
      - -1 disalbe 0 not active  1 active 
    - enable : 0 
      - 0 disable 
      - 1 enable 
  - check how to unlock limit . 
    - status : 1 
    - enable : 1 
    - [x] 这里有一个bug, 如果用户过期了,或者流量用尽了, 怎么能激活 enable 那里呢
      - 用户到期后,等级为 0 . 通过等级来控制用户 能否使用节点的. 
  - [x] srp plugin xray 那里 是如何判断的?
    - 后台  enable = 1 AND u + d < transfer_enable  
  - add the function , where to lock user 


- [] 优化每小时流量异常的代码,放到一个地方去.

- [] 防止 rebot 功能无法使用
- [] 验证码功能无法使用.


- [x] 废弃! fix一大堆 composer 的报错. 一些github库 私有 或 消失了, 在逐个替换中
    - [x] https://github.com/MIseChow/laravel-geetest项目私有化了, 改为 https://github.com/jormin/laravel-geetest
        - 所有的 MIseChow 改为了 jormin
        - [x] 测试 网站是否能正常运行

    - [x] github.com/jormin/laravel-reCAPTCHA 私有了,换成别的
        - https://github.com/biscolab/laravel-recaptcha
    - bug

```shell
PHP Fatal error:  Uncaught Error: Class 'Log' not found in /www/wwwroot/npanel-test.freesr.bid/app/Exceptions/Handler.php:45
Stack trace:
#0 /www/wwwroot/npanel-test.freesr.bid/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php(81): App\Exceptions\Handler->report(Object(Symfony\Component\Debug\Exception\FatalThrowableError))
#1 [internal function]: Illuminate\Foundation\Bootstrap\HandleExceptions->handleException(Object(Symfony\Component\Debug\Exception\FatalThrowableError))
#2 {main}
  thrown in /www/wwwroot/npanel-test.freesr.bid/app/Exceptions/Handler.php on line 45
PHP Fatal error:  Uncaught Error: Class 'Log' not found in /www/wwwroot/npanel-test.freesr.bid/app/Exceptions/Handler.php:45
Stack trace:
#0 /www/wwwroot/npanel-test.freesr.bid/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php(81): App\Exceptions\Handler->report(Object(Symfony\Component\Debug\Exception\FatalErrorException))
#1 /www/wwwroot/npanel-test.freesr.bid/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php(123): Illuminate\Foundation\Bootstrap\HandleExceptions->handleException(Object(Symfony\Component\Debug\Exception\FatalErrorException))
#2 [internal function]: Illuminate\Foundation\Bootstrap\HandleExceptions->handleShutdown()
#3 {main}
  thrown in /www/wwwroot/npanel-test.freesr.bid/app/Exceptions/Handler.php on line 45


```
- [x] In CurlDownloader.php line 371: curl error 28 while downloading https://packagist.laravel-china.org/packages.json: Connection timed out after 10004 milliseconds 
    - 解决方案:

- [x] 2025-08-20 
上面将 MIseChow 改为 jormin 是愚蠢的行为, 无法解决问题! 
恢复了 所有的 更改, 
直接 copy了 verder 文件夹, 直接上报即可.


- [] 订阅请求频率限制
  - 作用: 每个用户的订阅请求频率限制, 防止cc攻击
  - 每个用户的 限制
    - 限制 请求频率
      - 限制
        - 20次 每 15分钟
        - 30次 每小时
      - 超额限制:
        - 订阅返回空值
    - 限制请求的ip数量
      - 限制每个用户的 每小时请求的ip总数
      - 限制: 15ips 每小时限制
      - 超额返回报错ss: 'ss://YWVzLTEyOC1nY206d29yZHByZXNz@google.com:443'.'#'.urlencode('订阅请求ip数量异常,有太多ip在使用您的订阅,请联系管理员')."\n";
  - 性能优化
    - 频率限制使用 $code 
    - 所有的频率限制 在 mysql请求之前! 
      - 所有的数据库查询 都要在 频率限制之后
        - 作用: 避免 cc 攻击导致 数据库频繁查询 
    - ips 统计 使用 redis set , 代替 json
  - 优化
    - ips 统计 ,每小时重置一次. 
    - 代码中似乎是 每次请求都重置1小时统计, 这似乎是bug
    - 修改
      - ipKey 没有数据, 添加当前ip, 并设置为1小时
      - ipkey有数据,直接添加 当前ip

    
- [x] 检查 srp vps 是否安装 redis


- [] clash 和 singbox 订阅, 通过第三方url 转换后 发送给用户
  - [] 在设置中添加 sub_rss_url 地址配置
    - admin - 系统设置。- 拓展。-  订阅转换地址 sub_rss_url 
    - sub_rss_url 格式: 地址 http://139.162.118.243:25500/sub?target={target}&url={url}

  - [] clash 和 singbox 订阅 , 通过 sub_rss_url 转换后 发给用户
  - 核心文件: app/Http/Controllers/SubscribeController.php  getSubscribeByCode
  - clash & singbox
    - 当用户订阅中出现 clash=xx 或 singbox=xx ,存在且不为空值, 或值 >0  的时候, 获取 http://139.162.118.243:25500/sub.   ?target={target}&url={url} 内容,然后发给 用户
      - target: clash , singbox  , surfboard 
      - url : (self::$systemConfig['subscribe_domain'] ? self::$systemConfig['subscribe_domain'] : self::$systemConfig['website_url']) . '/s/' . Auth::user()->subscribe->code;
        - url 需要 urlencode 
    - 当 clash , singbox 参数存在时候, 优先处理, 
    - 检查, 当订阅转换超时的时候,返回空值.

- [] 新要求
1. 修正方法调用：
    - 将 getConvertedSubscribe 方法的参数从 5 个改为 3 个：target、value、subscribe
    - 更新了调用处的代码，正确传递参数
  2. 更新了方法签名：
    - $target: 目标类型（clash/singbox/surfboard）
    - $value: 订阅参数值（虽然暂时用不到，但保留以备将来扩展）
    - $subscribe: 订阅对象
  3. 优化了逻辑：
    - 在调用方法之前先确定 target 值
    - 简化了 getConvertedSubscribe 方法内部的目标验证逻辑
  4. 数据库配置：
    - 确认 sub_rss_url 配置已正确添加到数据库中

  功能使用方式

  现在用户可以通过以下方式使用转换功能：

  - Clash 订阅：/s/用户订阅码?clash=1
  - Singbox 订阅：/s/用户订阅码?singbox=1
  - Surfboard 订阅：/s/用户订阅码?surfboard=1

  管理员配置

  管理员需要在后台设置订阅转换地址，格式示例：
  http://139.162.118.243:25500/sub

  系统会自动在 URL 后面添加 ?target={target}&url={url} 参数。

  
- [] app/Http/Controllers/SubscribeController.php 修改
  - [] 订阅地址 如果检测到 app= 参数, 且为 clash singbox surfboard 中的任意一个,就进行订阅转换 target={app}
  - [] 订阅转换时候的地址,去除 app= 参数, 保留其他参数,  
  - [] singbox= , clash= , surfboard= 参数作废, 用 app= 参数代替

- [x] npanel test add sub_rss_url to mysql 
- [x] srp add sub_rss_url to mysql 

- [x] 订阅出错的时候,返回一条 信息, 告诉用户出错了. 用 rss 返回吧.


- [x] 订阅中,找出所有返回空值的地方,然后修改为返回 对应的 ss链接的信息.告诉用户信息.

请使用中文交流
- [x]  app/Http/Controllers/SubscribeController.php 文件 限制每15分钟不超过20次.
  - 用户反馈, 用户触发了 15分钟不超过20次的提示, 但是 用户等了一个小时, 还是有这个提示
    - 检查一下 这个缓存的 时间过期时间是否为 15分钟? 这里是否有BUG
  - 结论: 没问题.

请使用中文交流,有问题请问我.
- [x] 由用户反馈: “每15分钟限制20次的。我们根本没人一直订阅，过了一晚上还是不行。”
  - [x] 检查 /Volumes/co/github.com/supersongssr/NPanel/app/Http/Controllers/SubscribeController.php 这个文件中的 15分钟限制 , 是否会过了 15分钟重置,还是不重置. 
    - [x] 这里面的代码是否有bug? 
    - 给出检查结论. 
      - 没问题.

- [x] php 脚本 
  - get  /Volumes/co/github.com/supersongssr/NPanel/app/Http/Controllers/SubscribeController.php 的 redis limit 
  - 提供一个 订阅码, 返回 该订阅码在 redis 中的 limit 值.
    - 15分钟 limit
    - 1小时 limit
    - ip limit
    - 脚本放在 /tests floder  中,可以 php 单独运行. 
  - 请使用中文交流
  - 遇到问题请和我沟通方案. 


- [] 分析问题:  生成api接口说明文档
  - api file path : /Volumes/co/github.com/supersongssr/NPanel/app/Http/Controllers/Api/PingController.php
    - output 说明文档 path:  /docs
        - 请仔细核对 post 请求信息的格式


- [] 网站 firefox 报告网站诈骗问题
  - [] 猜测是 外部链接: https://edu:img@easyimage2.freessr.bid/ 导致的, 将外部链接的密钥给删除掉或者这个外部链接给去掉.再看看
    - [] 猜测是  curl https://www.baipiao.eu.org/cf.sh -o cf.sh && chmod +x cf.sh && ./cf.sh 这个教程导致的


- [] 检查所有 resources 中的 html 文件中的 外链,并列出来.
  - [] 列出来外链中 不安全的 外链.


- [v] 帮我分析一下 重置 计算 和 重置 的逻辑
  - 流量重置的代码在: app/Console/Commands/AutoResetUserTraffic.php
  - 查看一下 这些逻辑有没有问题
  

- [v] 检查每月流量重置的BUG

- [x] 新增 rsync_test.sh 脚本, 可以循环通过 fswatch 监控文件变化,然后 同步文件夹到远程. 
  - 采用了性能很高的方案, 这样可以配合 opencode 的 ai agent编程.
  - 更换到了 run on save 插件了,看一下效果.
  - 取舍 run on save 和 sync-rsync 两个插件.
    - rsync 会 在 右键加入一个程序,就是 rsync 远程到本地,以及本地到远程.
    - run on save 只能 本地到远程,不能远程到本地.哈哈
      - 这个是需要考虑的.
      - 这个同步也是厉害的.
  - [x] 这个不必了, 因为 opencode ai cli 会自己知道cp 代码到远程



- [x] panel 有 telegram 通知的设置吗? 在哪里

- [] 为 面板添加 telegram 通知功能. 
	- what i have :
		- 缺少 telegram 通知
	- what i want : 
		- 添加 telegram 通知
			- 在 .env 中支持定义 chat id 和 bot token 
		- 在用户提交提现申请的时候,发送 telegram 通知. 
			- 位置 app/Http/Controllers/UserController.php ; 如下两个 提现申请时候, 发送通知
				• ExtractAffMoney() - 邀请返利提现申请
				• ExtractRefMoney() - 消费返利提现申请
	- MUST:
		- 最小化修改
		- 考虑后续兼容性.
	- MUST NOT:
			
	- 我将如何做验证:
		- 用户提交提现申请,我会收到 telegram 通知. 
			- 这个通知提醒的 消息包含: 用户 是谁, 提现金额是多少.  
			- 我在网站的 /admin/applyList?status=0 页面可以查看到用户的提现申请.

- [] 为 telegram 通知,添加一个控制开关, 放在 admin/system 页面
  - 当前是什么状态:
    - telegram 控制开关在 .env 那里
  - 想要的状态:
    - 在 admin/system 页面控制 telegram 通知. 
      - 在 admin/system 页面配置 chat id 和 bot token 以及 是否 开启 telegram bot 的开关.
      - .env 中的 telegram 相关配置 删除


- [] 为api添加响应的值
  - 当前状态: app/Http/Controllers/Api/PingController.php ssn_sub 函数没有 json返回值, 或者返回值为空
  - 我想要的:
    - 为 ssn_sub 函数添加 json 返回值. 
    - 参考 app/Http/Controllers/Api 中的其他返回值. 
  - 审核要求:
    - 最小化修改
    - 兼容性修改

## 5.2 DEBUG


- [] /invoices页面 resources/views/user/invoices.blade.php $clonepays 的标签没有显示出来
  - 这是个BUG,请帮我检查,问题在哪,并尝试修复
  

- [] 报错: 请在远程服务器调试 
 UnexpectedValueException
The stream or file "/www/wwwroot/Npanel/storage/logs/laravel-2025-11-11.log" could not be opened: failed to open stream: Permission denied


/www
/wwwroot
/Npanel
/vendor
/monolog
/monolog
/src
/Monolog
/Handler
/StreamHandler.php

        /**
         * {@inheritdoc}
         */
        protected function write(array $record)
        {
            if (!is_resource($this->stream)) {
                if (null === $this->url || '' === $this->url) {
                    throw new \LogicException('Missing stream url, the stream can not be opened. This may be caused by a premature call to close().');
                }
                $this->createDir();
                $this->errorMessage = null;
                set_error_handler(array($this, 'customErrorHandler'));
                $this->stream = fopen($this->url, 'a');
                if ($this->filePermission !== null) {
                    @chmod($this->url, $this->filePermission);
                }
                restore_error_handler();
                if (!is_resource($this->stream)) {
                    $this->stream = null;
                    throw new \UnexpectedValueException(sprintf('The stream or file "%s" could not be opened: '.$this->errorMessage, $this->url));
                }
            }
     
            if ($this->useLocking) {
                // ignoring errors here, there's not much we can do about them
                flock($this->stream, LOCK_EX);
            }
     
            $this->streamWrite($this->stream, $record);
     
            if ($this->useLocking) {
                flock($this->stream, LOCK_UN);
            }
        }
     
        /**
         * Write to stream
         * @param resource $stream
         * @param array $record
         */

Arguments

    "The stream or file "/www/wwwroot/Npanel/storage/logs/laravel-2025-11-11.log" could not be opened: failed to open stream: Permission denied"





- [x] app/Http/Controllers/SubscribeController.php checkFrequencyLimit 需要
  - 在查询不到 key的时候, 添加key, 并设置过期时间
  - 代码中的设定过期时间不生效! 实际上未设置过期时间.请检查

- 结合代码,解释为什么 tests/get_subscribe_redis_limit.php 55-71行; fifteenMinuteKey 不存在,  但是 依然输出 状态: 活跃\n" 
  - 查看一下 当 key 不存在的时候, Redis::get 返回的值是什么? 
    - 做一个测试: 
      - 一个不存在的。key: SsXa1xx
      - 一个存在的key: SsXa1