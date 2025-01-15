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
- [] In CurlDownloader.php line 371: curl error 28 while downloading https://packagist.laravel-china.org/packages.json: Connection timed out after 10004 milliseconds 
    - 解决方案:
