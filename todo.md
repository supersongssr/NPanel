
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

    
- [] 检查 srp vps 是否安装 redis
