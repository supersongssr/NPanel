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
        // 校验合法性
        $subscribe = UserSubscribe::query()->with('user')->where('status', 1)->where('code', $code)->first();
        if (!$subscribe) {
            exit(0);
        }
        $user = User::query()->where('status', 1)->where('enable', 1)->where('id', $subscribe->user_id)->first();
        if (!$user) {
            exit(0);
        }
        $subscribe->increment('times', 1);  // 更新访问次数
        $subscribe->increment('times_today', 1);   //今日访问也+1
        $user->rss_ip = getClientIp();
        $user->save();
        $this->log($subscribe->id, getClientIp(), $request->headers);   // 记录每次请求
        //Song 获取查询字符串
        $ver = $request->get('ver');  // 1 = sr 2 = v2ray 这个废弃了 旧版本的
        $ssr_sub = $request->get('ssr'); //ssr现在已经废弃了
        $v2ray_sub = $request->get('v2ray');  // v2ray 包含 ss vmess vless trojan 三个订阅格式
        $ss_sub = $request->get('ss');
        $vmess_sub = $request->get('vmess');
        $vless_sub = $request->get('vless');
        $trojan_sub = $request->get('trojan');
        $rocket_sub = $request->get('rocket');  // 效果等同 v2ray_sub
        //订阅数量统计
        $v2ray_count = 0;
        $ss_count = 0;
        $vmess_count = 0;
        $vless_count = 0;
        $trojan_count = 0;
        $rocket_count = 0;
        // 开始获取节点 ：
        $scheme = '';  
        $scheme .= 'ss://YWVzLTEyOC1nY206d29yZHByZXNz@google.com:443'.'#'.urlencode('有效期：'.$user->expire_time)."\n";
        $newsList = SsNode::query()->where('status',1)->where('node_group',0)->orderBy('level', 'desc')->get();     //获取等级为0的news节点，新闻通知节点。
        foreach ($newsList as $key => $node) {
            if ( $node->type == 1 && ($ss_sub || $ver == "2" || $v2ray_sub || $rocket_sub) ) {
                $scheme .= 'ss://YWVzLTEyOC1nY206d29yZHByZXNz@google.com:443';
                $scheme .= '#'.urlencode($node->name) ."\n";
            } elseif ( $node->type == 2 && ($vmess_sub || $ver == "2" || $v2ray_sub || $rocket_sub) ) {       // 获取 vmess节点   
                $v2_json = [
                    "v"    => "2",
                    "ps"   => $node->name ,
                    "add"  => 'google.com' ,
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
                $scheme .= 'vless://11886d96-252e-4166-9535-ec72467ad095@google.com:443?encryption=none';
                $scheme .= '#'.urlencode($node->name) . "\n";
            } elseif ( $node->type == 4 && ($trojan_sub || $ver == "2" || $v2ray_sub || $rocket_sub) ) {  // trojan节点获取
                $scheme .= 'trojan://33216f76-f96d-417d-855a-7bd40bb3b884@google.com:443';
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
                $scheme .= '#'.urlencode($node->name.'-'.$node->id) . "\n";
                $vless_count += 1;
                $v2ray_count += 1;
                $rocket_count += 1;
            } elseif ( $node->type == 4 && ($trojan_sub || $ver == "2" || $v2ray_sub || $rocket_sub) ) {  // trojan节点获取
                if (max($trojan_count,$v2ray_count,$rocket_count) >= max($trojan_sub, $v2ray_sub, $rocket_sub)) {  //空值节点数量
                    continue;
                }
                $scheme .= 'trojan://'.$node_uuid.'@'.$node->server.':'.$node->v2_port;
                $scheme .= '?type='.$node->v2_net.'&headerType='.$node->v2_type.'&host='.urlencode($node->v2_host).'&path='.urlencode($node->v2_path).'&flow='.$node->v2_flow.'&security='.$node->v2_tls.'&sni='.$node->v2_sni.'&serviceName='.$node->v2_servicename.'&mode='.$node->v2_mode.'&alpn='.urlencode($node->v2_alpn);
                $scheme .= '#'.urlencode($node->name.($node->traffic_rate != 1 ? '_$'.$node->traffic_rate : '')) . "\n";
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
}
