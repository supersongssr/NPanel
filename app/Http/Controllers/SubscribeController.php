<?php

namespace App\Http\Controllers;

use App\Components\Helpers;
use App\Http\Models\Device;
use App\Http\Models\SsGroup;
use App\Http\Models\SsNode;
use App\Http\Models\User;
use App\Http\Models\UserLabel;
use App\Http\Models\UserSubscribe;
use App\Http\Models\UserSubscribeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

// cncdn
use App\Http\Models\Cncdn;

use Redirect;
use Response;

/**
 * 订阅控制器
 *
 * Class SubscribeController
 *
 * @package App\Http\Controllers
 */
class SubscribeController extends Controller
{
    protected static $systemConfig;

    function __construct()
    {
        self::$systemConfig = Helpers::systemConfig();
    }

    // 订阅码列表
    public function subscribeList(Request $request)
    {
        $user_id = $request->get('user_id');
        $username = $request->get('username');
        $status = $request->get('status');

        $query = UserSubscribe::with(['User']);

        if (!empty($user_id)) {
            $query->where('user_id', $user_id);
        }

        if (!empty($username)) {
            $query->whereHas('user', function ($q) use ($username) {
                $q->where('username', 'like', '%' . $username . '%');
            });
        }

        if ($status != '') {
            $query->where('status', intval($status));
        }

        $view['subscribeList'] = $query->orderBy('id', 'desc')->paginate(20)->appends($request->except('page'));

        return Response::view('subscribe.subscribeList', $view);
    }

    // 订阅设备列表
    public function deviceList(Request $request)
    {
        $type = intval($request->get('type'));
        $platform = intval($request->get('platform'));
        $name = trim($request->get('name'));
        $status = intval($request->get('status'));

        $query = Device::query();

        if (!empty($type)) {
            $query->where('type', $type);
        }

        if ($platform != '') {
            $query->where('platform', $platform);
        }

        if (!empty($name)) {
            $query->where('name', 'like', '%' . $name . '%');
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        $view['deviceList'] = $query->paginate(20)->appends($request->except('page'));

        return Response::view('subscribe.deviceList', $view);
    }

    // 设置用户的订阅的状态
    public function setSubscribeStatus(Request $request)
    {
        $id = $request->get('id');
        $status = $request->get('status', 0);

        if (empty($id)) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '操作异常']);
        }

        if ($status) {
            UserSubscribe::query()->where('id', $id)->update(['status' => 1, 'ban_time' => 0, 'ban_desc' => '']);
        } else {
            UserSubscribe::query()->where('id', $id)->update(['status' => 0, 'ban_time' => time(), 'ban_desc' => '后台手动封禁']);
        }

        return Response::json(['status' => 'success', 'data' => '', 'message' => '操作成功']);
    }

    // 设置设备是否允许订阅的状态
    public function setDeviceStatus(Request $request)
    {
        $id = intval($request->get('id'));
        $status = intval($request->get('status', 0));

        if (empty($id)) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '操作异常']);
        }

        Device::query()->where('id', $id)->update(['status' => $status]);

        return Response::json(['status' => 'success', 'data' => '', 'message' => '操作成功']);
    }

    // 通过订阅码获取订阅信息
    public function getSubscribeByCode(Request $request, $code)
    {
        if (empty($code)) {
            return Redirect::to('login');
        }

        // 获取请求的域名（不包含协议）
        $requestDomain = $request->getHttpHost();

        // 获取客户端IP
        $clientIp = getClientIp();
        
        // 频率限制检查 - 在任何数据库查询之前执行
        $limitResult = $this->checkFrequencyLimit($code, $clientIp);
        
        // 如果15分钟频率限制超额，返回特定错误信息
        if ($limitResult === 'fifteen_min_exceeded') {
            $errorResponse = 'ss://YWVzLTEyOC1nY206d29yZHByZXNz@'.$requestDomain.':443'.'#'.urlencode('订阅请求频繁15分钟后再试')."\n";
            exit(base64_encode($errorResponse));
        }

        // 如果1小时频率限制超额，返回特定错误信息
        if ($limitResult === 'one_hour_exceeded') {
            $errorResponse = 'ss://YWVzLTEyOC1nY206d29yZHByZXNz@'.$requestDomain.':443'.'#'.urlencode('订阅请求频繁1小时后再试')."\n";
            exit(base64_encode($errorResponse));
        }

        // 如果IP数量超额，返回错误信息
        if ($limitResult === 'ip_exceeded') {
            $errorResponse = 'ss://YWVzLTEyOC1nY206d29yZHByZXNz@'.$requestDomain.':443'.'#'.urlencode('订阅ip数量异常请休息一下')."\n";
            exit(base64_encode($errorResponse));
        }
        
        // 校验合法性
        $subscribe = UserSubscribe::query()->with('user')->where('status', 1)->where('code', $code)->first();
        if (!$subscribe) {
            $errorResponse = 'ss://YWVzLTEyOC1nY206d29yZHByZXNz@'.$requestDomain.':443'.'#'.urlencode('error167')."\n";
            exit(base64_encode($errorResponse));
        }
        $user = User::query()->where('status', 1)->where('enable', 1)->where('id', $subscribe->user_id)->first();
        if (!$user) {
            $errorResponse = 'ss://YWVzLTEyOC1nY206d29yZHByZXNz@'.$requestDomain.':443'.'#'.urlencode('error172')."\n";
            exit(base64_encode($errorResponse));
        }

        

        // $subscribe->increment('times', 1);  // 更新访问次数
        // $subscribe->increment('times_today', 1);   //今日访问也+1
        // $user->rss_ip = $clientIp;
        // $user->save();
        $this->log($subscribe->id, $clientIp, $request->headers);   // 记录每次请求

        // 获取查询字符串参数
        $app = $request->get('app') ?? "";     // app 参数用于订阅转换

        $ver = $request->get('ver') ?? 2;  // 1 = sr 2 = v2ray 这个废弃了 旧版本的
        $ssr_sub = $request->get('ssr') ?? 128; //ssr现在已经废弃了
        $v2ray_sub = $request->get('v2ray') ?? 128;  // v2ray 包含 ss vmess vless trojan 三个订阅格式
        $ss_sub = $request->get('ss') ?? 128;
        $vmess_sub = $request->get('vmess') ?? 128;
        $vless_sub = $request->get('vless') ?? 128;
        $trojan_sub = $request->get('trojan') ?? 128;
        $rocket_sub = $request->get('rocket') ?? 128;  // 效果等同 v2ray_sub

        // Clash 和 Singbox 订阅转换处理（使用 app 参数）
        if ($app && in_array($app, ['clash', 'singbox', 'surfboard', 'loon'])) {
            // 移除 app 参数，保留其他参数构建原始订阅URL
            $query_string = $request->query();
            unset($query_string['app']);

            $converted_subscribe = $this->getConvertedSubscribe($app, $subscribe, $query_string);
            if ($converted_subscribe) {
                // 根据不同的订阅类型设置相应的 Content-Type
                $contentType = 'text/plain; charset=utf-8';
                if (in_array($app, ['clash', 'singbox'])) {
                    $contentType = 'text/yaml; charset=utf-8';
                }

                return Response::make($converted_subscribe)
                    ->header('Content-Type', $contentType)
                    ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
            }
        }

        //订阅数量统计
        $v2ray_count = 0;
        $ss_count = 0;
        $vmess_count = 0;
        $vless_count = 0;
        $trojan_count = 0;
        $rocket_count = 0;
        // 开始获取节点 ：
        $scheme = '';
        $scheme .= 'ss://YWVzLTEyOC1nY206d29yZHByZXNz@'.$requestDomain.':443'.'#'.urlencode('有效期：'.$user->expire_time)."\n";
        $newsList = SsNode::query()->where('status',1)->where('node_group',0)->orderBy('level', 'desc')->get();     //获取等级为0的news节点，新闻通知节点。
        foreach ($newsList as $key => $node) {
            if ( $node->type == 1 && ($ss_sub || $ver == "2" || $v2ray_sub || $rocket_sub) ) {
                $scheme .= 'ss://YWVzLTEyOC1nY206d29yZHByZXNz@'.$requestDomain.':443';
                $scheme .= '#'.urlencode($node->name) ."\n";
            } elseif ( $node->type == 2 && ($vmess_sub || $ver == "2" || $v2ray_sub || $rocket_sub) ) {       // 获取 vmess节点
                $v2_json = [
                    "v"    => "2",
                    "ps"   => $node->name ,
                    "add"  => $requestDomain ,
                    "port" => 443 ,
                    "id"   => '11886d96-252e-4166-9535-ec72467ad095' ,
                    "aid"  => 0 ,
                    "scy"  => 'none' ,
                    "net"  => 'tcp' ,
                    "type" => '' ,
                    "host" => '' ,
                    "path" => '' ,
                    "tls"  => '' ,
                    "sni"  => '' ,
                    "alpn" => ''
                ];
                $scheme .= 'vmess://' . base64_encode(json_encode($v2_json)) . "\n";
            } elseif ( $node->type == 3 && ($vless_sub || $ver == "2" || $v2ray_sub || $rocket_sub) ) {   // vless节点获取
                $scheme .= 'vless://11886d96-252e-4166-9535-ec72467ad095@'.$requestDomain.':443?encryption=none';
                $scheme .= '#'.urlencode($node->name) . "\n";
            } elseif ( $node->type == 4 && ($trojan_sub || $ver == "2" || $v2ray_sub || $rocket_sub) ) {  // trojan节点获取
                $scheme .= 'trojan://33216f76-f96d-417d-855a-7bd40bb3b884@'.$requestDomain.':443';
                $scheme .= '#'.urlencode($node->name) . "\n";
            }
        }
        // 获取正式节点。
        $nodeList = SsNode::query()->where('status',1)->where('is_subscribe',1)->where('node_group',$user->node_group)->where('level', '<=' ,$user->level)->orderBy('level', 'desc')->orderBy('traffic_left_daily', 'desc')->get();
        if (empty($nodeList)) {
            exit(base64_encode($scheme));
        }
        foreach ($nodeList as $key => $node) {
            // if ($node->v2_cdn){
            //     if ($node->v2_cdn_ip){
            //         $node->server = $node->v2_cdn_ip; // cdn ip
            //     }
            //     if ( $node->v2_cdn == 'cf' && $user->cfcdn ) {   // 设置用户CDN
            //         $node->server = $user->cfcdn;
            //     }
            // }
            $node->v2_tls == 0 && $node->v2_tls = '';   // 解析 tls xtls 
            $node->v2_tls == 1 && $node->v2_tls = 'tls';
            $node->v2_tls == 2 && $node->v2_tls = 'xtls';
            $node->node_uuid ? $node_uuid = $node->node_uuid : $node_uuid = $user->vmess_id;    // 独立节点的密码判断

            if ( $node->type == 2 && ($vmess_sub || $ver == "2" || $v2ray_sub || $rocket_sub) ) {       // 获取 vmess节点   
                if (max($vmess_count,$v2ray_count,$rocket_count) >= max($vmess_sub, $v2ray_sub, $rocket_sub)) {  //空值节点数量
                    continue;
                }
                $v2_json = [
                    "v"    => "2",
                    "ps"   => $node->name.'-'.$node->id  ,
                    "add"  => $node->server ,
                    "port" => $node->v2_port ,
                    "id"   => $node_uuid ,
                    "aid"  => $node->v2_alter_id ,
                    "scy"  => $node->v2_method ,
                    "net"  => $node->v2_net ,
                    "type" => $node->v2_type ,
                    "host" => $node->v2_host ,
                    "path" => $node->v2_path ,
                    "tls"  => $node->v2_tls ,
                    "sni"  => $node->v2_sni ,
                    "serviceName" => $node->v2_servicename,
                    "mode"  => $node->v2_mode ,
                    "alpn" => $node->v2_alpn  
                ];
                $scheme .= 'vmess://' . base64_encode(json_encode($v2_json)) . "\n";
                $vmess_count += 1;
                $v2ray_count += 1;
                $rocket_count += 1;
            } elseif ( $node->type == 3 && ($vless_sub || $ver == "2" || $v2ray_sub || $rocket_sub) ) {   // vless节点获取
                if (max($vless_count,$v2ray_count,$rocket_count) >= max($vless_sub, $v2ray_sub, $rocket_sub)) {  //空值节点数量
                    continue;
                }
                $scheme .= 'vless://'.$node_uuid.'@'.$node->server.':'.$node->v2_port;
                $scheme .= '?encryption='.$node->v2_encryption.'&type='.$node->v2_net.'&headerType='.$node->v2_type.'&host='.urlencode($node->v2_host).'&path='.urlencode($node->v2_path).'&flow='.$node->v2_flow.'&security='.$node->v2_tls.'&sni='.$node->v2_sni .'&fp='.$node->v2_fp.'&serviceName='.$node->v2_servicename. '&mode='.$node->v2_mode.'&alpn='.urlencode($node->v2_alpn);
                $scheme .= '#'.urlencode($node->name.($node->traffic_rate != 1 ? '_x'.$node->traffic_rate : '')) . "\n";
                $vless_count += 1;
                $v2ray_count += 1;
                $rocket_count += 1;
            } elseif ( $node->type == 4 && ($trojan_sub || $ver == "2" || $v2ray_sub || $rocket_sub) ) {  // trojan节点获取
                if (max($trojan_count,$v2ray_count,$rocket_count) >= max($trojan_sub, $v2ray_sub, $rocket_sub)) {  //空值节点数量
                    continue;
                }
                $scheme .= 'trojan://'.$node_uuid.'@'.$node->server.':'.$node->v2_port;
                $scheme .= '?type='.$node->v2_net.'&headerType='.$node->v2_type.'&host='.urlencode($node->v2_host).'&path='.urlencode($node->v2_path).'&flow='.$node->v2_flow.'&security='.$node->v2_tls.'&sni='.$node->v2_sni.'&serviceName='.$node->v2_servicename.'&mode='.$node->v2_mode.'&alpn='.urlencode($node->v2_alpn);
                $scheme .= '#'.urlencode($node->name.($node->traffic_rate != 1 ? '_x'.$node->traffic_rate : '')) . "\n";
                $trojan_count += 1;
                $v2ray_count += 1;
                $rocket_count += 1;
            }            
        }

        // 2023-12-21 获取 free proxy nodes share link 
        $getFreeNodes = false;
        if ($getFreeNodes){
            if ($ss_sub || $ver == "2" || $v2ray_sub || $rocket_sub){
                $scheme .= "";
            }
            if ($vmess_sub || $ver == "2" || $v2ray_sub || $rocket_sub){
                $scheme .= "";
            }
            if ($vless_sub || $ver == "2" || $v2ray_sub || $rocket_sub){
                $scheme .= "";
            }
            if ($trojan_sub || $ver == "2" || $v2ray_sub || $rocket_sub){
                $scheme .= "";
            }
        }

        exit(base64_encode($scheme));
    }

    // 写入订阅访问日志
    private function log($subscribeId, $ip, $headers)
    {
        $log = new UserSubscribeLog();
        $log->sid = $subscribeId;
        $log->request_ip = $ip;
        $log->request_time = date('Y-m-d H:i:s');
        $log->request_header = $headers;
        $log->save();
    }
    
    /**
     * 检查订阅请求频率限制
     *
     * @param string $code 订阅码
     * @param string $ip 客户端IP
     * @return string|bool 'fifteen_min_exceeded' 表示15分钟限制超额, 'one_hour_exceeded' 表示1小时限制超额, 'ip_exceeded' 表示IP数量超额, true 表示通过
     */
    private function checkFrequencyLimit($code, $ip)
    {
        $currentTime = time();
        $subscribeKey = 'subscribe_limit:' . $code;
        $ipKey = 'subscribe_ip:' . $code;
        
        // 检查15分钟频率限制 (20次/15分钟)
        $fifteenMinuteKey = $subscribeKey . ':15m';
        $fifteenMinuteCount = Redis::get($fifteenMinuteKey);
        
        if ($fifteenMinuteCount == null || $fifteenMinuteCount == false ) {
            // 第一次请求，设置初始值和过期时间
            Redis::setex($fifteenMinuteKey, 900, 1); // 15分钟 = 900秒
        } else {
            $fifteenMinuteCount = intval($fifteenMinuteCount);
            if ($fifteenMinuteCount >= 20) {
                return 'fifteen_min_exceeded'; // 超过15分钟限制，返回特定标识
            }
            Redis::incr($fifteenMinuteKey);
        }
        
        // 检查1小时频率限制 (30次/小时)
        $oneHourKey = $subscribeKey . ':1h';
        $oneHourCount = Redis::get($oneHourKey);
        
        if ($oneHourCount === null  || $fifteenMinuteCount == false ) {
            // 第一次请求，设置初始值和过期时间
            Redis::setex($oneHourKey, 3600, 1); // 1小时 = 3600秒
        } else {
            $oneHourCount = intval($oneHourCount);
            if ($oneHourCount >= 30) {
                return 'one_hour_exceeded'; // 超过1小时限制，返回特定标识
            }
            Redis::incr($oneHourKey);
        }
        
        // 检查IP数量限制 (15个IP/小时)
        $ipCount = Redis::sCard($ipKey);
        if ($ipCount >= 15) {
            return 'ip_exceeded'; // 超过IP数量限制
        }
        
        // 添加当前IP到集合，设置1小时过期
        // 如果ipKey没有数据，添加当前ip并设置1小时过期
        // 如果ipKey有数据，直接添加当前ip
        if (!Redis::exists($ipKey)) {
            Redis::sAdd($ipKey, $ip);
            Redis::expire($ipKey, 3600);
        } else {
            Redis::sAdd($ipKey, $ip);
        }
        
        return true; // 所有检查通过
    }

    // 抛出无可用的节点信息，用于兼容防止客户端订阅失败
    private function noneNode()
    {
        return base64url_encode('ss://' . base64url_encode('0.0.0.0:1:origin:none:plain:' . base64url_encode('0000') . '/?obfsparam=&protoparam=&remarks=' . base64url_encode('检查账号！网站' . Helpers::systemConfig()['website_name'] .'欢迎您') . '&group=' . base64url_encode('错误') . '&udpport=0&uot=0') . "\n");
    }

    /**
     * 过期时间
     *
     * @param object $user
     *
     * @return string
     */
    private function expireDate($user)
    {
        $text = '到期时间：' . $user->expire_time;

        return 'ssr://' . base64url_encode('0.0.0.1:1:origin:none:plain:' . base64url_encode('0000') . '/?obfsparam=&protoparam=&remarks=' . base64url_encode($text) . '&group=' . base64url_encode(Helpers::systemConfig()['website_name']) . '&udpport=0&uot=0') . "\n";
    }

    /**
     * 剩余流量
     *
     * @param object $user
     *
     * @return string
     */
    private function lastTraffic($user)
    {
        $text = '剩余流量：' . flowAutoShow($user->transfer_enable - $user->u - $user->d);

        return 'ssr://' . base64url_encode('0.0.0.2:1:origin:none:plain:' . base64url_encode('0000') . '/?obfsparam=&protoparam=&remarks=' . base64url_encode($text) . '&group=' . base64url_encode(Helpers::systemConfig()['website_name']) . '&udpport=0&uot=0') . "\n";
    }

    /**
     * 用户信息 v2ray
     *
     * @param object $user
     *
     * @return string
     */
    private function userInfoV2ray($user)
    {
        $text = '到期时间：' . $user->expire_time;

        return 'ssr://' . base64url_encode('0.0.0.1:1:origin:none:plain:' . base64url_encode('0000') . '/?obfsparam=&protoparam=&remarks=' . base64url_encode($text) . '&group=' . base64url_encode(Helpers::systemConfig()['website_name']) . '&udpport=0&uot=0') . "\n";
    }

    /**
     * 获取转换后的订阅（Clash/Singbox/Surfboard）
     *
     * @param string $target 目标类型（clash/singbox/surfboard）
     * @param object $subscribe 订阅对象
     * @param array $query_string 查询字符串参数（已移除app参数）
     *
     * @return string|false
     */
    private function getConvertedSubscribe($target, $subscribe, $query_string = [])
    {
        // 获取系统配置中的订阅转换地址
        $sub_rss_url = self::$systemConfig['sub_rss_url'] ?? '';

        if (empty($sub_rss_url)) {
            return false;
        }

        // // 验证目标类型
        // if (!in_array($target, ['clash', 'singbox', 'surfboard'])) {
        //     return false;
        // }

        // 构建订阅URL（保留其他参数）
        $subscribe_domain = self::$systemConfig['subscribe_domain'] ?: self::$systemConfig['website_url'];
        $original_url = $subscribe_domain . '/s/' . $subscribe->code;

        // 如果有其他查询参数，添加到URL中
        if (!empty($query_string)) {
            $original_url .= '?' . http_build_query($query_string);
        }

        // 构建转换URL
        $convert_url = $sub_rss_url. '?target='. $target . '&url=' . urlencode($original_url);

        try {
            // 设置超时时间（5秒）
            $timeout = 5;

            // 使用 cURL 获取转换后的订阅内容
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $convert_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; NPanel Subscribe Converter)');

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // 检查是否成功
            if ($http_code === 200 && $response !== false && !empty($response)) {
                return $response;
            }

            // 超时或错误处理
            if (strpos($error, 'timeout') !== false || strpos($error, 'Operation timed out') !== false) {
                // 记录超时日志（可选）
                Log::error('订阅转换超时: ' . $convert_url);
                return false;
            }

            // 其他错误处理
            Log::error('订阅转换失败: ' . $convert_url . ' - ' . $error);
            return false;

        } catch (\Exception $e) {
            Log::error('订阅转换异常: ' . $convert_url . ' - ' . $e->getMessage());
            return false;
        }
    }
}
