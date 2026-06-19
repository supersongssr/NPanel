<?php

namespace App\Components;

use App\Http\Models\Config;
use App\Http\Models\CouponLog;
use App\Http\Models\EmailLog;
use App\Http\Models\Level;
use App\Http\Models\SsConfig;
use App\Http\Models\User;
use App\Http\Models\UserSubscribe;
use App\Http\Models\UserTrafficModifyLog;

class Helpers
{
    // 不生成的端口
    private static $denyPorts = [
        1068, 1109, 1434, 3127, 3128,
        3129, 3130, 3332, 4444, 5554,
        6669, 8080, 8081, 8082, 8181,
        8282, 9996, 17185, 24554, 35601,
        60177, 60179
    ];

    /**
     * Parse node_domain_pool config into a normalized map.
     *
     * @param array $sysConf  Result of self::systemConfig()
     * @return array ['domainPool' => [...], 'primaryDomain' => string]
     */
    public static function parseDomainPool(array $sysConf)
    {
        $domainPool = array();
        $raw = isset($sysConf['node_domain_pool']) ? $sysConf['node_domain_pool'] : '';

        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $firstKey = null;
                foreach ($decoded as $k => $v) {
                    $firstKey = $k;
                    break;
                }
                if ($firstKey !== null && is_string($firstKey) && is_array($decoded[$firstKey])) {
                    $domainPool = $decoded;
                } else {
                    foreach (array_values(array_unique(array_filter($decoded))) as $domain) {
                        if (is_string($domain) && $domain !== '') {
                            $domainPool[$domain] = array();
                        }
                    }
                }
            }
        }

        $primaryDomain = '';
        foreach ($domainPool as $dk => $dv) {
            $primaryDomain = $dk;
            break;
        }
        if (empty($primaryDomain) && !empty($sysConf['node_root_domain'])) {
            $primaryDomain = $sysConf['node_root_domain'];
        }

        return array('domainPool' => $domainPool, 'primaryDomain' => $primaryDomain);
    }

    // 获取系统配置
    public static function systemConfig()
    {
        $config = Config::query()->get();
        $data = [];
        foreach ($config as $vo) {
            $data[$vo->name] = $vo->value;
        }

        if (!isset($data['host_pools'])) {
            $data['host_pools'] = '{}';
        }

        if (!empty($data['host_pools'])) {
            $hostPools = json_decode($data['host_pools'], true);
            if (is_array($hostPools)) {
                $request = request();
                if ($request) {
                    $host = $request->header('X-Forwarded-Host') ?: $request->header('Host');
                    if ($host) {
                        $host = trim(explode(',', $host)[0]); // 取第一个
                        $host = explode(':', $host)[0]; // 忽略端口号

                        if (isset($hostPools[$host])) {
                            $pool = $hostPools[$host];
                            if (!empty($pool['website_name'])) {
                                $data['website_name'] = $pool['website_name'];
                            }
                            if (!empty($pool['website_url'])) {
                                $data['website_url'] = $pool['website_url'];
                            }
                            if (!empty($pool['subscribe_domain'])) {
                                $data['subscribe_domain'] = $pool['subscribe_domain'];
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }

    // 获取默认加密方式
    public static function getDefaultMethod()
    {
        $config = SsConfig::default()->type(1)->first();

        return $config ? $config->name : 'aes-256-cfb';
    }

    // 获取默认协议
    public static function getDefaultProtocol()
    {
        $config = SsConfig::default()->type(2)->first();

        return $config ? $config->name : 'origin';
    }

    // 获取默认混淆
    public static function getDefaultObfs()
    {
        $config = SsConfig::default()->type(3)->first();

        return $config ? $config->name : 'plain';
    }

    // 获取一个随机端口
    public static function getRandPort()
    {
        $config = self::systemConfig();
        $port = mt_rand($config['min_port'], $config['max_port']);

        $exists_port = User::query()->pluck('port')->toArray();
        if (in_array($port, $exists_port) || in_array($port, self::$denyPorts)) {
            $port = self::getRandPort();
        }

        return $port;
    }

    // 获取一个端口
    public static function getOnlyPort()
    {
        $config = self::systemConfig();
        $port = $config['min_port'];

        $exists_port = User::query()->where('port', '>=', $config['min_port'])->pluck('port')->toArray();
        while (in_array($port, $exists_port) || in_array($port, self::$denyPorts)) {
            $port = $port + 1;
        }

        return $port;
    }

    /**
     * 生成随机 path (32 位十六进制, 等同 UUID 的熵).
     * 用于 xhttp / ws / grpc 随机化分流路径, 避免固定 path 被封锁.
     * 仅含 [0-9a-f], URL / nginx location 均安全.
     */
    public static function genRandomPath()
    {
        return bin2hex(random_bytes(16));
    }

    // 加密方式
    public static function methodList()
    {
        return SsConfig::type(1)->get();
    }

    // 协议
    public static function protocolList()
    {
        return SsConfig::type(2)->get();
    }

    // 混淆
    public static function obfsList()
    {
        return SsConfig::type(3)->get();
    }

    // 等级
    public static function levelList()
    {
        return Level::query()->get()->sortBy('level');
    }

    // 生成用户的订阅码
    public static function makeSubscribeCode()
    {
        $code = makeRandStr(5);
        if (UserSubscribe::query()->where('code', $code)->exists()) {
            $code = self::makeSubscribeCode();
        }

        return $code;
    }

    /**
     * 添加邮件投递日志
     *
     * @param string $address 收信地址
     * @param string $title   标题
     * @param string $content 内容
     * @param int    $status  投递状态
     * @param string $error   投递失败时记录的异常信息
     *
     * @return int
     */
    public static function addEmailLog($address, $title, $content, $status = 1, $error = '')
    {
        $log = new EmailLog();
        $log->type = 1;
        $log->address = $address;
        $log->title = $title;
        $log->content = $content;
        $log->status = $status;
        $log->error = $error;
        $log->save();

        return $log->id;
    }

    /**
     * 添加优惠券操作日志
     *
     * @param int    $couponId 优惠券ID
     * @param int    $goodsId  商品ID
     * @param int    $orderId  订单ID
     * @param string $desc     备注
     *
     * @return int
     */
    public static function addCouponLog($couponId, $goodsId, $orderId, $user_id,$desc = '')
    {
        $log = new CouponLog();
        $log->coupon_id = $couponId;
        $log->goods_id = $goodsId;
        $log->order_id = $orderId;
        $log->user_id = $user_id;
        $log->desc = $desc; 

        return $log->save();
    }

    /**
     * 记录流量变动日志
     *
     * @param int    $userId 用户ID
     * @param string $oid    订单ID
     * @param int    $before 记录前的值
     * @param int    $after  记录后的值
     * @param string $desc   描述
     *
     * @return int
     */
    public static function addUserTrafficModifyLog($userId, $oid, $before, $after, $desc = '')
    {
        $log = new UserTrafficModifyLog();
        $log->user_id = $userId;
        $log->order_id = $oid;
        $log->before = $before;
        $log->after = $after;
        $log->desc = $desc;

        return $log->save();
    }
}