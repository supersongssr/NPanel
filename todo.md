
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

# 2024-12-31 

- [] fix一大堆 composer 的报错. 一些github库 私有 或 消失了, 在逐个替换中
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

