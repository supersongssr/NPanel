极验验证码 v3.0 Laravel

> 采用了Germeyd的package，在此基础上添加后台获取Geetest ID 和 KEY  方法，可不从env获取。

## 安装

 1. 安装包文件

	``` bash
	$ composer require misechow/laravel-geetest
	```

## 配置

1. config/app.php 注册 ServiceProvider:
	
	```php
	Misechow\Geetest\GeetestServiceProvider::class,
	```

2. config/app.php 添加 Alias

     ```php
     'Geetest' => Misechow\Geetest\Facades\Geetest::class,
     ```

3. 创建配置文件、视图级资源文件：

	```shell
	php artisan vendor:publish --provider='Misechow\Geetest\GeetestServiceProvider'
	```
	
4. `.env` 文件增加配置项 `GEETEST_ID` 和 `GEETEST_KEY` 或 通过添加 Component CaptchaVerify 来获取 ID 和 KEY

## 配置项

| 配置项  | 说明  | 选项  | 默认值  |
| ------------ | ------------ | ------------ | ------------ |
| width | 按钮宽度  | 单位可以是 px, %, em, rem, pt  | 300px|
| lang | 语言，极验验证码免费版不支持多国语言  | zh-cn, en, zh-tw, ja, ko, th  | zh-cn  |
| server-get-config | 从服务器获取GeetestKEY | True | False          |
| product  | 验证码展示方式  | popup, float  | popup  |
| geetest_id  | 极验验证码ID  |   |   |
| geetest_key  | 极验验证码KEY  |   |   |
| client_fail_alert  | 客户端失败提示语  |   | 请完成验证码  |
| server_fail_alert  | 服务端失败提示语  |   | 验证码校验失败  |

## 使用

1. 前端使用

安装扩展后，在页面需要使用极验验证码的地方增加如下代码

```php
{!! Geetest::render() !!}
```

2. 服务端校验

在服务端使用 `geetest` 验证规则进行二次验证，示例代码：

```php
$this->validate($request, [
    'geetest_challenge' => 'required|geetest'
], [
    'geetest' => config('geetest.server_fail_alert')
]);
```

3. 配置项：server-get-config  -  服务器获取GeetestKey

通过调用 Components CaptchaVerify 的 geetestCaptchaGetConfig 方法来获取 Geetest 配置，方便实现由后台配置的KEY 和 ID

```php
<?php

namespace App\Components;

/**
 * Class CaptchaVerify
 *
 * @package App\Components
 */

Class CaptchaVerify 
{
    public static function geetestCaptchaGetConfig() 
    {
        return [
            "geetest_id" => Helpers::systemConfig()["geetest_id"], // 后台获取 id
            "geetest_key" => Helpers::systemConfig()["geetest_key"] // 后台获取 key
        ];
    }
}

?>
```

## 参考项目

1. [Germey/LaravelGeetest](https://github.com/Germey/LaravelGeetest)

2. [GeeTeam/gt3-php-sdk](https://github.com/GeeTeam/gt3-php-sdk)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
