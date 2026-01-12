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
        // 所有格式都使用直接生成，不再使用第三方转换
        if (in_array($target, ['singbox', 'clash', 'loon', 'surfboard'])) {
            return $this->generateDirectSubscribe($target, $subscribe, $query_string);
        }

        // 其他格式仍使用第三方转换
        $sub_rss_url = self::$systemConfig['sub_rss_url'] ?? '';

        if (empty($sub_rss_url)) {
            return false;
        }

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

    /**
     * 直接生成订阅配置（sing-box 或 clash）
     *
     * @param string $format 订阅格式（singbox/clash）
     * @param object $subscribe 订阅对象
     * @param array $query_string 查询字符串参数
     * @return string|false
     */
    private function generateDirectSubscribe($format, $subscribe, $query_string = [])
    {
        // 获取用户信息
        $user = User::query()->where('status', 1)->where('enable', 1)->where('id', $subscribe->user_id)->first();
        if (!$user) {
            return false;
        }

        // 获取节点列表
        $nodeList = SsNode::query()
            ->where('status', 1)
            ->where('is_subscribe', 1)
            ->where('node_group', $user->node_group)
            ->where('level', '<=', $user->level)
            ->orderBy('level', 'desc')
            ->orderBy('traffic_left_daily', 'desc')
            ->get();

        if ($nodeList->isEmpty()) {
            return false;
        }

        // 根据格式生成配置
        if ($format === 'singbox') {
            return $this->generateSingboxConfig($nodeList, $user);
        } elseif ($format === 'clash') {
            return $this->generateClashConfig($nodeList, $user);
        } elseif ($format === 'loon') {
            return $this->generateLoonConfig($nodeList, $user);
        } elseif ($format === 'surfboard') {
            return $this->generateSurfboardConfig($nodeList, $user);
        }

        return false;
    }

    /**
     * 生成 sing-box JSON 配置
     *
     * @param \Illuminate\Support\Collection $nodeList 节点列表
     * @param User $user 用户对象
     * @return string
     */
    private function generateSingboxConfig($nodeList, $user)
    {
        $outbounds = [];
        $outbounds[] = [
            'type' => 'selector',
            'tag' => 'Proxy',
            'outbounds' => ['auto'],
            'filter' => ['type' => 'all']
        ];

        $proxyTags = [];

        foreach ($nodeList as $node) {
            $node_uuid = $node->node_uuid ?: $user->vmess_id;
            $tagName = $node->name . ($node->traffic_rate != 1 ? '_x' . $node->traffic_rate : '');
            $proxyTags[] = $tagName;

            // 解析 TLS
            $tls = '';
            if ($node->v2_tls == 1) {
                $tls = 'tls';
            } elseif ($node->v2_tls == 2) {
                $tls = 'xtls';
            }

            // 根据节点类型生成不同的 outbound 配置
            if ($node->type == 2) {
                // VMess
                $outbound = [
                    'type' => 'vmess',
                    'tag' => $tagName,
                    'server' => $node->server,
                    'server_port' => (int)$node->v2_port,
                    'uuid' => $node_uuid,
                    'security' => $node->v2_method,
                    'alter_id' => (int)$node->v2_alter_id,
                    'transport' => [
                        'type' => $node->v2_net,
                    ]
                ];

                // 添加传输层配置
                if ($node->v2_net == 'ws' || $node->v2_net == 'http') {
                    $outbound['transport']['path'] = $node->v2_path;
                    if ($node->v2_host) {
                        $outbound['transport']['headers'] = ['Host' => [$node->v2_host]];
                    }
                } elseif ($node->v2_net == 'grpc') {
                    $outbound['transport']['service_name'] = $node->v2_servicename;
                }

                // 添加 TLS 配置
                if ($tls) {
                    $outbound['tls'] = [
                        'enabled' => true,
                        'server_name' => $node->v2_sni,
                    ];
                    if ($node->v2_alpn) {
                        $alpnList = array_filter(array_map('trim', explode(',', $node->v2_alpn)));
                        if (!empty($alpnList)) {
                            $outbound['tls']['alpn'] = $alpnList;
                        }
                    }
                }

                $outbounds[] = $outbound;

            } elseif ($node->type == 3) {
                // VLESS
                $outbound = [
                    'type' => 'vless',
                    'tag' => $tagName,
                    'server' => $node->server,
                    'server_port' => (int)$node->v2_port,
                    'uuid' => $node_uuid,
                    'transport' => [
                        'type' => $node->v2_net,
                    ]
                ];

                // 添加传输层配置
                if ($node->v2_net == 'ws' || $node->v2_net == 'http') {
                    $outbound['transport']['path'] = $node->v2_path;
                    if ($node->v2_host) {
                        $outbound['transport']['headers'] = ['Host' => [$node->v2_host]];
                    }
                } elseif ($node->v2_net == 'grpc') {
                    $outbound['transport']['service_name'] = $node->v2_servicename;
                }

                // 添加 TLS 配置
                if ($tls) {
                    $outbound['tls'] = [
                        'enabled' => true,
                        'server_name' => $node->v2_sni,
                    ];
                    if ($node->v2_alpn) {
                        $alpnList = array_filter(array_map('trim', explode(',', $node->v2_alpn)));
                        if (!empty($alpnList)) {
                            $outbound['tls']['alpn'] = $alpnList;
                        }
                    }
                }

                // 添加 flow
                if ($node->v2_flow) {
                    $outbound['flow'] = $node->v2_flow;
                }

                $outbounds[] = $outbound;

            } elseif ($node->type == 4) {
                // Trojan
                $outbound = [
                    'type' => 'trojan',
                    'tag' => $tagName,
                    'server' => $node->server,
                    'server_port' => (int)$node->v2_port,
                    'password' => $node_uuid,
                    'transport' => [
                        'type' => $node->v2_net,
                    ]
                ];

                // 添加传输层配置
                if ($node->v2_net == 'ws' || $node->v2_net == 'http') {
                    $outbound['transport']['path'] = $node->v2_path;
                    if ($node->v2_host) {
                        $outbound['transport']['headers'] = ['Host' => [$node->v2_host]];
                    }
                } elseif ($node->v2_net == 'grpc') {
                    $outbound['transport']['service_name'] = $node->v2_servicename;
                }

                // 添加 TLS 配置
                if ($tls) {
                    $outbound['tls'] = [
                        'enabled' => true,
                        'server_name' => $node->v2_sni,
                    ];
                    if ($node->v2_alpn) {
                        $alpnList = array_filter(array_map('trim', explode(',', $node->v2_alpn)));
                        if (!empty($alpnList)) {
                            $outbound['tls']['alpn'] = $alpnList;
                        }
                    }
                }

                $outbounds[] = $outbound;
            }
        }

        // 添加其他必要的 outbounds
        $outbounds[] = ['type' => 'direct', 'tag' => 'direct'];
        $outbounds[] = ['type' => 'block', 'tag' => 'block'];
        $outbounds[] = ['type' => 'dns', 'tag' => 'dns-out'];

        // 更新 selector 的 outbounds
        $outbounds[0]['outbounds'] = array_merge(['auto'], $proxyTags, ['direct']);

        // 构建完整配置
        $config = [
            'log' => [
                'level' => 'info',
                'timestamp' => true
            ],
            'dns' => [
                'servers' => [
                    [
                        'tag' => 'local',
                        'address' => 'https://1.1.1.1/dns-query',
                        'detour' => 'direct'
                    ]
                ]
            ],
            'inbounds' => [
                [
                    'type' => 'tun',
                    'tag' => 'tun-in',
                    'interface_name' => 'tun0',
                    'inet4_address' => '172.19.0.1/30',
                    'auto_route' => true,
                    'strict_route' => false,
                    'sniff' => true,
                    'sniff_override_destination' => true
                ]
            ],
            'outbounds' => $outbounds,
            'route' => [
                'rules' => [
                    [
                        'protocol' => 'dns',
                        'outbound' => 'dns-out'
                    ],
                    [
                        'clash_mode' => 'Direct',
                        'outbound' => 'direct'
                    ],
                    [
                        'private' => true,
                        'outbound' => 'direct'
                    ]
                ],
                'auto_detect_interface' => true
            ],
            'experimental' => [
                'clash_api' => [
                    'external_controller' => '127.0.0.1:9090'
                ]
            ]
        ];

        return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 生成 Clash YAML 配置
     *
     * 参考 Mihomo (Clash Meta) 规范: https://wiki.metacubex.one/config/proxies/
     *
     * @param \Illuminate\Support\Collection $nodeList 节点列表
     * @param User $user 用户对象
     * @return string
     */
    private function generateClashConfig($nodeList, $user)
    {
        // 使用原生字符串拼接生成标准 YAML
        $yaml = '';

        // 全局配置
        $yaml .= "mixed-port: 7890\n";
        $yaml .= "allow-lan: true\n";
        $yaml .= "bind-address: \"*\"\n";
        $yaml .= "mode: rule\n";
        $yaml .= "log-level: info\n";
        $yaml .= "ipv6: false\n";
        $yaml .= "external-controller: 127.0.0.1:9090\n";

        // 生成节点列表
        $yaml .= "proxies:\n";

        $proxyNames = []; // 用于在 proxy-groups 中引用

        foreach ($nodeList as $node) {
            $node_uuid = $node->node_uuid ?: $user->vmess_id;

            // 节点名称：确保特殊字符转义，并添加节点 ID 避免同名冲突
            $proxyName = $node->name . ($node->traffic_rate != 1 ? '_x' . $node->traffic_rate : '') . ' | #' . $node->id;
            // 对节点名称进行引号包裹，避免 YAML 解析问题
            $quotedName = '"' . str_replace(['"', '\\'], ['\\"', '\\\\'], $proxyName) . '"';

            // TLS 状态判断
            $tlsEnabled = ($node->v2_tls == 1 || $node->v2_tls == 2);

            // 网络类型
            $network = $node->v2_net ?: 'tcp';

            // VMess 节点
            if ($node->type == 2) {
                // 只有在实际输出节点时才添加到 proxyNames
                $proxyNames[] = $quotedName;
                $yaml .= "  - name: " . $quotedName . "\n";
                $yaml .= "    type: vmess\n";
                $yaml .= "    server: " . $node->server . "\n";
                $yaml .= "    port: " . (int)$node->v2_port . "\n";
                $yaml .= "    uuid: " . $node_uuid . "\n";
                $yaml .= "    alterId: " . (int)$node->v2_alter_id . "\n";
                $yaml .= "    cipher: " . $node->v2_method . "\n";
                $yaml .= "    udp: true\n";
                $yaml .= "    network: " . $network . "\n";

                // WebSocket 传输
                if ($network == 'ws' || $network == 'http') {
                    if ($node->v2_path) {
                        $yaml .= "    ws-opts:\n";
                        $yaml .= "      path: \"" . $node->v2_path . "\"\n";
                    }
                    if ($node->v2_host) {
                        if (!$node->v2_path) {
                            $yaml .= "    ws-opts:\n";
                        }
                        $yaml .= "      headers:\n";
                        $yaml .= "        Host: \"" . $node->v2_host . "\"\n";
                    }
                }

                // gRPC 传输
                elseif ($network == 'grpc') {
                    if ($node->v2_servicename) {
                        $yaml .= "    grpc-opts:\n";
                        $yaml .= "      grpc-service-name: " . $node->v2_servicename . "\n";
                    }
                }

                // TLS 配置
                if ($tlsEnabled) {
                    $yaml .= "    tls: true\n";
                    if ($node->v2_sni) {
                        $yaml .= "    servername: " . $node->v2_sni . "\n";
                    }
                    if ($node->v2_alpn) {
                        $alpnList = array_filter(array_map('trim', explode(',', $node->v2_alpn)));
                        if (!empty($alpnList)) {
                            $yaml .= "    alpn:\n";
                            foreach ($alpnList as $alpn) {
                                $yaml .= "      - " . $alpn . "\n";
                            }
                        }
                    }
                }
            }

            // VLESS 节点 - 严格按照 Mihomo 规范
            elseif ($node->type == 3) {
                // 只有在实际输出节点时才添加到 proxyNames
                $proxyNames[] = $quotedName;
                $yaml .= "  - name: " . $quotedName . "\n";
                $yaml .= "    type: vless\n";
                $yaml .= "    server: " . $node->server . "\n";
                $yaml .= "    port: " . (int)$node->v2_port . "\n";
                $yaml .= "    uuid: " . $node_uuid . "\n";
                $yaml .= "    udp: true\n";
                $yaml .= "    skip-cert-verify: " . ($node->v2_sni ? 'false' : 'true') . "\n";

                // gRPC 传输
                if ($network == 'grpc') {
                    $yaml .= "    network: grpc\n";
                    if ($node->v2_servicename) {
                        $yaml .= "    grpc-opts:\n";
                        $yaml .= "      grpc-service-name: " . $node->v2_servicename . "\n";
                    }
                }
                // WebSocket 传输
                elseif ($network == 'ws') {
                    $yaml .= "    network: ws\n";
                    if ($node->v2_path) {
                        $yaml .= "    ws-opts:\n";
                        $yaml .= "      path: \"" . $node->v2_path . "\"\n";
                    }
                    if ($node->v2_host) {
                        $yaml .= "      headers:\n";
                        $yaml .= "        Host: \"" . $node->v2_host . "\"\n";
                    }
                }
                // TCP 传输（默认）
                else {
                    $yaml .= "    network: tcp\n";
                }

                // TLS 配置
                if ($tlsEnabled) {
                    $yaml .= "    tls: true\n";
                    if ($node->v2_sni) {
                        $yaml .= "    servername: " . $node->v2_sni . "\n";
                    }
                    // Mihomo 建议始终添加 client-fingerprint
                    $yaml .= "    client-fingerprint: chrome\n";
                    if ($node->v2_alpn) {
                        $alpnList = array_filter(array_map('trim', explode(',', $node->v2_alpn)));
                        if (!empty($alpnList)) {
                            $yaml .= "    alpn:\n";
                            foreach ($alpnList as $alpn) {
                                $yaml .= "      - " . $alpn . "\n";
                            }
                        }
                    }
                }

                // flow (XTLS 流控) - 严格检查：必须是 TCP 且有 TLS
                if ($node->v2_flow && $tlsEnabled && $network == 'tcp') {
                    $yaml .= "    flow: " . $node->v2_flow . "\n";
                }
            }

            // Trojan 节点
            elseif ($node->type == 4) {
                // 只有在实际输出节点时才添加到 proxyNames
                $proxyNames[] = $quotedName;
                $yaml .= "  - name: " . $quotedName . "\n";
                $yaml .= "    type: trojan\n";
                $yaml .= "    server: " . $node->server . "\n";
                $yaml .= "    port: " . (int)$node->v2_port . "\n";
                $yaml .= "    password: " . $node_uuid . "\n";
                $yaml .= "    udp: true\n";
                $yaml .= "    network: " . $network . "\n";

                // WebSocket 传输
                if ($network == 'ws' || $network == 'http') {
                    if ($node->v2_path) {
                        $yaml .= "    ws-opts:\n";
                        $yaml .= "      path: \"" . $node->v2_path . "\"\n";
                    }
                    if ($node->v2_host) {
                        $yaml .= "      headers:\n";
                        $yaml .= "        Host: \"" . $node->v2_host . "\"\n";
                    }
                }

                // gRPC 传输
                elseif ($network == 'grpc') {
                    if ($node->v2_servicename) {
                        $yaml .= "    grpc-opts:\n";
                        $yaml .= "      grpc-service-name: " . $node->v2_servicename . "\n";
                    }
                }

                // TLS 配置
                if ($tlsEnabled) {
                    $yaml .= "    tls: true\n";
                    if ($node->v2_sni) {
                        $yaml .= "    servername: " . $node->v2_sni . "\n";
                    }
                }
            }
        }

        // 策略组
        $yaml .= "proxy-groups:\n";
        $yaml .= "  - name: \"Proxy\"\n";
        $yaml .= "    type: select\n";
        $yaml .= "    proxies:\n";
        $yaml .= "      - \"auto\"\n";
        foreach ($proxyNames as $name) {
            $yaml .= "      - " . $name . "\n";
        }
        $yaml .= "      - \"DIRECT\"\n";

        $yaml .= "  - name: \"auto\"\n";
        $yaml .= "    type: url-test\n";
        $yaml .= "    proxies:\n";
        foreach ($proxyNames as $name) {
            $yaml .= "      - " . $name . "\n";
        }
        $yaml .= "    url: \"http://www.gstatic.com/generate_204\"\n";
        $yaml .= "    interval: 300\n";

        // 规则
        $yaml .= "rules:\n";
        $yaml .= "  - DOMAIN-SUFFIX,local,DIRECT\n";
        $yaml .= "  - IP-CIDR,127.0.0.0/8,DIRECT\n";
        $yaml .= "  - IP-CIDR,172.16.0.0/12,DIRECT\n";
        $yaml .= "  - IP-CIDR,192.168.0.0/16,DIRECT\n";
        $yaml .= "  - IP-CIDR,10.0.0.0/8,DIRECT\n";
        $yaml .= "  - GEOIP,CN,DIRECT\n";
        $yaml .= "  - MATCH,Proxy\n";

        return $yaml;
    }

    /**
     * 将数组转换为 YAML 格式
     *
     * @param array $data
     * @return string
     */
    private function toYaml($data)
    {
        $yaml = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // 检查是否是关联数组（对象）
                if ($this->isAssociativeArray($value)) {
                    $yaml .= $key . ':' . "\n";
                    $yaml .= $this->arrayToYaml($value, 1);
                } else {
                    // 索引数组
                    $yaml .= $key . ':' . "\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= str_repeat('  ', 1) . '- ' . $this->arrayToYaml($item, 2);
                        } else {
                            $yaml .= str_repeat('  ', 1) . '- ' . $this->yamlValue($item) . "\n";
                        }
                    }
                }
            } else {
                $yaml .= $key . ': ' . $this->yamlValue($value) . "\n";
            }
        }
        return $yaml;
    }

    /**
     * 将数组转换为 YAML 格式（递归）
     *
     * @param array $data
     * @param int $indent
     * @return string
     */
    private function arrayToYaml($data, $indent = 0)
    {
        $yaml = '';
        $indentStr = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isAssociativeArray($value)) {
                    // 关联数组
                    $yaml .= $indentStr . $key . ':' . "\n";
                    $yaml .= $this->arrayToYaml($value, $indent + 1);
                } else {
                    // 索引数组
                    $yaml .= $indentStr . $key . ':' . "\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            if ($this->isAssociativeArray($item)) {
                                $yaml .= $indentStr . '  ' . '- ' . $this->arrayToYaml($item, $indent + 2);
                            } else {
                                $yaml .= $indentStr . '  ' . '- ' . "\n";
                                $yaml .= $this->arrayToYaml($item, $indent + 2);
                            }
                        } else {
                            $yaml .= $indentStr . '  ' . '- ' . $this->yamlValue($item) . "\n";
                        }
                    }
                }
            } else {
                $yaml .= $indentStr . $key . ': ' . $this->yamlValue($value) . "\n";
            }
        }

        return $yaml;
    }

    /**
     * 判断是否是关联数组
     *
     * @param array $arr
     * @return bool
     */
    private function isAssociativeArray($arr)
    {
        if (!is_array($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * 将值转换为 YAML 格式
     *
     * @param mixed $value
     * @return string
     */
    private function yamlValue($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            return 'null';
        } elseif (is_numeric($value)) {
            return (string)$value;
        } else {
            // 字符串需要引号的情况
            if (strpos($value, ':') !== false || strpos($value, '#') !== false || strpos($value, '[') !== false) {
                return '"' . $value . '"';
            }
            return $value;
        }
    }

    /**
     * 生成 Loon 配置
     *
     * @param \Illuminate\Support\Collection $nodeList 节点列表
     * @param User $user 用户对象
     * @return string
     */
    private function generateLoonConfig($nodeList, $user)
    {
        $config = [];
        $proxies = [];
        $proxyNames = [];

        foreach ($nodeList as $node) {
            $node_uuid = $node->node_uuid ?: $user->vmess_id;
            $proxyName = str_replace([' ', ',', '[', ']'], '_', $node->name . ($node->traffic_rate != 1 ? '_x' . $node->traffic_rate : ''));
            $proxyNames[] = $proxyName;

            // 解析 TLS
            $tls = '';
            $sni = '';
            $alpn = '';
            if ($node->v2_tls == 1) {
                $tls = true;
                $sni = $node->v2_sni;
                $alpn = $node->v2_alpn;
            }

            // 根据节点类型生成不同的 proxy 配置
            if ($node->type == 2) {
                // VMess
                $vmess_aead = 'true';
                $ws_opts = '';
                $tls_opts = '';

                if ($node->v2_net == 'ws' || $node->v2_net == 'http') {
                    $ws_path = $node->v2_path ?: '/';
                    $ws_headers = '';
                    if ($node->v2_host) {
                        $ws_headers = ", ws-headers=Host:{$node->v2_host}";
                    }
                    $ws_opts = ", ws=true, ws-path={$ws_path}{$ws_headers}";
                }

                if ($tls) {
                    $sni_opt = $sni ? ", sni={$sni}" : '';
                    $skip_cert = ', skip-cert-verify=true';
                    $tls_opts = ", tls=true{$sni_opt}{$skip_cert}";
                }

                $proxies[] = "{$proxyName} = vmess, {$node->server}, {$node->v2_port}, username={$node_uuid}, udp-relay=false, vmess-aead={$vmess_aead}{$ws_opts}{$tls_opts}";

            } elseif ($node->type == 3) {
                // VLESS (Loon uses vmess-like format)
                $ws_opts = '';
                $tls_opts = '';
                $flow = $node->v2_flow ? ", flow={$node->v2_flow}" : '';

                if ($node->v2_net == 'ws' || $node->v2_net == 'http') {
                    $ws_path = $node->v2_path ?: '/';
                    $ws_headers = '';
                    if ($node->v2_host) {
                        $ws_headers = ", ws-headers=Host:{$node->v2_host}";
                    }
                    $ws_opts = ", ws=true, ws-path={$ws_path}{$ws_headers}";
                }

                if ($tls) {
                    $sni_opt = $sni ? ", sni={$sni}" : '';
                    $skip_cert = ', skip-cert-verify=true';
                    $tls_opts = ", tls=true{$sni_opt}{$skip_cert}";
                }

                $proxies[] = "{$proxyName} = vless, {$node->server}, {$node->v2_port}, username={$node_uuid}, udp-relay=false{$ws_opts}{$tls_opts}{$flow}";

            } elseif ($node->type == 4) {
                // Trojan
                $ws_opts = '';
                $tls_opts = '';

                if ($node->v2_net == 'ws' || $node->v2_net == 'http') {
                    $ws_path = $node->v2_path ?: '/';
                    $ws_headers = '';
                    if ($node->v2_host) {
                        $ws_headers = ", ws-headers=Host:{$node->v2_host}";
                    }
                    $ws_opts = ", ws=true, ws-path={$ws_path}{$ws_headers}";
                }

                if ($tls) {
                    $sni_opt = $sni ? ", sni={$sni}" : '';
                    $skip_cert = ', skip-cert-verify=true';
                    $tls_opts = ", tls=true{$sni_opt}{$skip_cert}";
                }

                $proxies[] = "{$proxyName} = trojan, {$node->server}, {$node->v2_port}, password={$node_uuid}, udp-relay=false{$ws_opts}{$tls_opts}";
            }
        }

        // 构建 [General] 部分
        $general_lines = [
            '[General]',
            'dns-server = system, 223.5.5.5, 119.29.29.29',
            'doh-server = https://1.1.1.1/dns-query',
            'skip-proxy = 127.0.0.1, 192.168.0.0/16, 10.0.0.0/8, 172.16.0.0/12, localhost, *.local',
            'bypass-tun = 192.168.0.0/16, 10.0.0.0/8, 172.16.0.0/12',
            'proxy-test-url = http://www.gstatic.com/generate_204',
            'test-timeout = 5',
            'internet-test-url = http://www.gstatic.cn/generate_204',
            '',
        ];

        // 构建 [Proxy] 部分
        $proxy_lines = [
            '[Proxy]',
            // 内置策略
            'DIRECT = direct',
            'REJECT = reject',
        ];
        $proxy_lines = array_merge($proxy_lines, $proxies);
        $proxy_lines[] = '';

        // 构建 [Policy] 部分
        $proxy_group_lines = [
            '[Policy]',
            'static = ' . implode(', ', array_merge($proxyNames, ['DIRECT', 'REJECT'])),
            '',
        ];

        // 构建 [Filter] 部分 (基本规则)
        $filter_lines = [
            '[Filter]',
            'DOMAIN-KEYWORD,google,DIRECT',
            'DOMAIN-KEYWORD,facebook,DIRECT',
            'DOMAIN,google.cn,DIRECT',
            'DOMAIN,baidu.com,DIRECT',
            'DOMAIN-SUFFIX,cn,DIRECT',
            'GEOIP,CN,DIRECT',
            'FINAL,static',
            '',
        ];

        return implode("\n", array_merge($general_lines, $proxy_lines, $proxy_group_lines, $filter_lines));
    }

    /**
     * 生成 Surfboard 配置
     *
     * @param \Illuminate\Support\Collection $nodeList 节点列表
     * @param User $user 用户对象
     * @return string
     */
    private function generateSurfboardConfig($nodeList, $user)
    {
        $subscribe_domain = self::$systemConfig['subscribe_domain'] ?: self::$systemConfig['website_url'];
        $subscribe_url = $subscribe_domain . '/s/' . $user->subscribe->code ?? '';

        $config = [];
        $proxies = [];
        $proxyNames = [];

        foreach ($nodeList as $node) {
            $node_uuid = $node->node_uuid ?: $user->vmess_id;
            $proxyName = str_replace([' ', ',', '[', ']'], '_', $node->name . ($node->traffic_rate != 1 ? '_x' . $node->traffic_rate : ''));
            $proxyNames[] = $proxyName;

            // 解析 TLS
            $tls = '';
            $sni = '';
            if ($node->v2_tls == 1) {
                $tls = true;
                $sni = $node->v2_sni;
            }

            // 根据节点类型生成不同的 proxy 配置
            if ($node->type == 2) {
                // VMess
                $vmess_aead = 'true';
                $ws_opts = '';
                $tls_opts = '';

                if ($node->v2_net == 'ws' || $node->v2_net == 'http') {
                    $ws_path = $node->v2_path ?: '/';
                    $ws_headers = '';
                    if ($node->v2_host) {
                        $ws_headers = ", ws-headers=X-Header-1:{$node->v2_host}";
                    }
                    $ws_opts = ", ws=true, ws-path={$ws_path}{$ws_headers}";
                }

                if ($tls) {
                    $sni_opt = $sni ? ", sni={$sni}" : '';
                    $skip_cert = ', skip-cert-verify=true';
                    $tls_opts = ", tls=true{$sni_opt}{$skip_cert}";
                }

                $proxies[] = "{$proxyName} = vmess, {$node->server}, {$node->v2_port}, username={$node_uuid}, udp-relay=false, vmess-aead={$vmess_aead}{$ws_opts}{$tls_opts}";

            } elseif ($node->type == 3) {
                // VLESS
                $ws_opts = '';
                $tls_opts = '';
                $flow = $node->v2_flow ? ", flow={$node->v2_flow}" : '';

                if ($node->v2_net == 'ws' || $node->v2_net == 'http') {
                    $ws_path = $node->v2_path ?: '/';
                    $ws_headers = '';
                    if ($node->v2_host) {
                        $ws_headers = ", ws-headers=X-Header-1:{$node->v2_host}";
                    }
                    $ws_opts = ", ws=true, ws-path={$ws_path}{$ws_headers}";
                }

                if ($tls) {
                    $sni_opt = $sni ? ", sni={$sni}" : '';
                    $skip_cert = ', skip-cert-verify=true';
                    $tls_opts = ", tls=true{$sni_opt}{$skip_cert}";
                }

                $proxies[] = "{$proxyName} = vless, {$node->server}, {$node->v2_port}, username={$node_uuid}, udp-relay=false{$ws_opts}{$tls_opts}{$flow}";

            } elseif ($node->type == 4) {
                // Trojan
                $ws_opts = '';
                $tls_opts = '';

                if ($node->v2_net == 'ws' || $node->v2_net == 'http') {
                    $ws_path = $node->v2_path ?: '/';
                    $ws_headers = '';
                    if ($node->v2_host) {
                        $ws_headers = ", ws-headers=X-Header-1:{$node->v2_host}";
                    }
                    $ws_opts = ", ws=true, ws-path={$ws_path}{$ws_headers}";
                }

                if ($tls) {
                    $sni_opt = $sni ? ", sni={$sni}" : '';
                    $skip_cert = ', skip-cert-verify=true';
                    $tls_opts = ", tls=true{$sni_opt}{$skip_cert}";
                }

                $proxies[] = "{$proxyName} = trojan, {$node->server}, {$node->v2_port}, password={$node_uuid}, udp-relay=false{$ws_opts}{$tls_opts}";
            }
        }

        // 构建配置头部
        $header_lines = [
            '#!MANAGED-CONFIG ' . $subscribe_url . ' interval=3600 strict=true',
            '[General]',
            'dns-server = system, 8.8.8.8, 8.8.4.4',
            'skip-proxy = 127.0.0.1, 192.168.0.0/16, 10.0.0.0/8, 172.16.0.0/12, localhost, *.local',
            'proxy-test-url = http://www.gstatic.com/generate_204',
            'test-timeout = 5',
            '',
        ];

        // 构建 [Proxy] 部分
        $proxy_lines = [
            '[Proxy]',
            // 内置策略
            'On = direct',
            'Off = reject',
        ];
        $proxy_lines = array_merge($proxy_lines, $proxies);
        $proxy_lines[] = '';

        // 构建 [Proxy Group] 部分
        $proxy_group_lines = [
            '[Proxy Group]',
            'Proxy = select, ' . implode(', ', array_merge(['On'], $proxyNames, ['Off'])),
            'AutoTest = url-test, ' . implode(', ', $proxyNames) . ', url=http://www.gstatic.com/generate_204, interval=600, timeout=5',
            '',
        ];

        // 构建 [Rule] 部分
        $rule_lines = [
            '[Rule]',
            'DOMAIN-SUFFIX,local,On',
            'IP-CIDR,192.168.0.0/16,On',
            'IP-CIDR,10.0.0.0/8,On',
            'IP-CIDR,172.16.0.0/12,On',
            'GEOIP,CN,On',
            'FINAL,Proxy',
            '',
        ];

        return implode("\n", array_merge($header_lines, $proxy_lines, $proxy_group_lines, $rule_lines));
    }
}
