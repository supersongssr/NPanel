
# 2025-01-16

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

# 2024-12-31  这个废弃!

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