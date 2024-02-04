<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Log;
//song
use App\Http\Models\SsNodeTrafficHourly;
use App\Http\Models\UserTrafficLog;
use App\Http\Models\SsNode;
use Illuminate\Console\Command;
use App\Http\Models\SsNodeOnlineLog;
use App\Components\Helpers;
//sdo2022-04-13
use App\Http\Models\User;
use App\Http\Models\Coupon;
// song2023-12-21 use for label ! cool way 
use App\Http\Models\SsNodeLabel;
use App\Http\Models\Label;


/**
 * PING检测工具
 *
 * Class PingController
 *
 * @package App\Http\Controllers\Api
 */
class PingController extends Controller
{
    public function ping(Request $request)
    {
        $token = $request->input('token');
        $host = $request->input('host');
        $port = $request->input('port', 22);
        $transport = $request->input('transport', 'tcp');
        $timeout = $request->input('timeout', 0.5);

        if (empty($host)) {
            echo "<pre>";
            echo "使用方法：";
            echo "<br>";
            echo "GET /api/ping?token=toke_value&host=www.baidu.com&port=80&transport=tcp&timeout=0.5";
            echo "<br>";
            echo "token：.env下加入API_TOKEN，其值就是token的值";
            echo "<br>";
            echo "host：检测地址，必传，可以是域名、IPv4、IPv6";
            echo "<br>";
            echo "port：检测端口，可不传，默认22";
            echo "<br>";
            echo "transport：检测协议，可不传，默认tcp，可以是tcp、udp";
            echo "<br>";
            echo "timeout：检测超时，单位秒，可不传，默认0.5秒，建议不超过3秒";
            echo "<br>";
            echo "成功返回：1，失败返回：0";
            echo "</pre>";
            exit();
        }

        // 验证TOKEN，防止滥用
        if (env('API_TOKEN') != $token) {
            return response()->json(['status' => 0, 'message' => 'token invalid']);
        }

        // 如果不是IPv4
        if (false === filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // 如果是IPv6
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $host = '[' . $host . ']';
            }
        }

        try {
            $host = gethostbyname($host); // 这里如果挂了，说明服务器的DNS解析不给力，必须换
            $fp = stream_socket_client($transport . '://' . $host . ':' . $port, $errno, $errstr, $timeout);
            if (!$fp) {
                Log::info("$errstr ($errno)");
                $ret = 0;
                $message = 'port close';
            } else {
                $ret = 1;
                $message = 'port open';
            }

            fclose($fp);

            return response()->json(['status' => $ret, 'message' => $message]);
        } catch (\Exception $e) {
            Log::info($e);

            return response()->json(['status' => 0, 'message' => 'port close']);
        }
    }

    public function ssn_sub(Request $request, $id)
    {
        $request->get('token') != env('API_TOKEN') && exit; // 验证 token 防止滥用

        $ip = getClientIp();
        //
        //获取NODE数据
        $node = SsNode::query()->where('id', $id)->first();
        $node->heartbeat_at = date('Y-m-d H:i:s');      //节点心跳
        // 审核上报的IP， 是否和记录的一致 如果记录Ip不匹配， 就不更改，外加报错。
        if ( $node->ip != $ip && $node->ipv6 != $ip ) {
            $node->desc .= '_'.$ip;
            $node->sort -= 100;
            // $node->save();
            // exit;
        }
        $request->get('status') == 0 && $node->status = 0;
        $request->get('status') == 1 && $node->status = 1;
        $request->get('health') == 0 && $node->is_subscribe = 0;
        $request->get('health') == 1 && $node->is_subscribe = 1;
        $node->node_online = $request->get('online');
        $node->traffic = $request->get('traffic');
        $node->traffic_used = $request->get('traffic_used');
        $node->traffic_used_daily = $request->get('traffic_used_daily');
        $node->traffic_left = $request->get('traffic_left');
        $node->traffic_left_daily = $request->get('traffic_left_daily');
        $node->node_onload = $request->get('daily');
        $node->save();
        //写入节点在线人数
        $online_log = new SsNodeOnlineLog();
        $online_log->node_id = $id;
        $online_log->online_user = $request->get('online');
        $online_log->log_time = time();   
        $online_log->save();
    }

    public function ssn_v2(Request $request, $id)
    {

        $request->get('token') != env('API_TOKEN') && exit; // 验证 token 防止滥用
        $node = SsNode::query()->where('id', $id)->first();
        if (! $node ){
            $node = new SsNode();
            $node->id = $id;
        }

        $request->get('node_name') && $node->name = $request->get('node_name');
        $request->get('node_country_code') && $node->country_code = $request->get('node_country_code');
        $request->get('node_country_code') && $node->name = $node->isotoemoji($request->get('node_country_code')).$node->name;
        $request->get('node_info') && $node->info = $request->get('node_info');
        // $request->get('node_unlock_info') && $node->info .= $request->get('node_unlock_info');
        $request->get('node_from') && $node->desc = ',from:' . $request->get('node_from');
        $request->get('node_expire') && $node->desc .= ',expire:' . $request->get('node_expire');
        $request->get('node_cost') != '' && $node->node_cost = $request->get('node_cost');
        $request->get('node_level') && $node->level = $request->get('node_level');
        $request->get('node_group') && $node->node_group = $request->get('node_group');
        $request->get('node_traffic_limit') && $node->traffic_limit = $request->get('node_traffic_limit')*1024*1024*1024;
        $request->get('node_sort') != '' && $node->sort = $request->get('node_sort');  //排序 这里之所以用 node_sort 是因为 sort 参数在 Sspuim里面 是节点类型的意思。避免混淆
        $request->get('node_traffic_rate') && $node->traffic_rate = $request->get('node_traffic_rate');
        $request->get('node_bandwidth') && $node->bandwidth = $request->get('node_bandwidth');  //带宽
        if ($request->get('node_ip') || $request->get('node_ipv6')) {   //IP IPV6要同时记录。嘎嘎
            $node->ip = $request->get('node_ip');  //排序
            $node->ipv6 = $request->get('node_ipv6');  //排序
        }

        if ($request->get('node_unlock')){
            $_a = $_a = str_replace(',','&',$request->get('node_unlock'));
            $_a = str_replace(':','=',$_a);
            $node->node_unlock = $_a;
            
            if ($request->get('node_info')){  //生成 user Lables
                SsNodeLabel::query()->where('node_id',$node->id)->delete(); //先删除之前的 label
                $labels = Label::query()->orderBy('sort', 'desc')->orderBy('id', 'asc')->get();
                foreach ($labels as $label) {
                    if (strstr($node->info , $label->name)){
                        $ssNodeLabel = new SsNodeLabel();
                        $ssNodeLabel->node_id = $node->id;
                        $ssNodeLabel->label_id = $label->id;
                        $ssNodeLabel->save();
                    }
                }
            }
        }

        
        
        //node protocol_conf  先判断是否有 vmess vless trojan ss 等标志前缀
        if ( $request->get('v2') ) {
            $request->get('v2') == 'ss' && $node->type = 1;
            $request->get('v2') == 'vmess' && $node->type = 2;
            $request->get('v2') == 'vless' && $node->type = 3;
            $request->get('v2') == 'trojan' && $node->type = 4;
            $node->server = $request->get('v2_add');
            $node->v2_port = $request->get('v2_port');
            $node->v2_alter_id = $request->get('v2_aid');
            $node->v2_method = $request->get('v2_scy');
            $node->v2_net = $request->get('v2_net');
            $node->v2_type = $request->get('v2_type');
            $node->v2_host = $request->get('v2_host');
            $node->v2_path = $request->get('v2_path');
            // tls : 0 tls xtls 
            empty($request->get('v2_tls')) && $node->v2_tls = 0;
            $request->get('v2_tls') == 'tls' && $node->v2_tls = 1;
            $request->get('v2_tls') == 'xtls' && $node->v2_tls = 2;
            $node->v2_sni = $request->get('v2_sni');
            $node->v2_alpn = $request->get('v2_alpn');
            // vless 特有
            $node->v2_encryption = $request->get('v2_ecpt');
            $node->v2_encryption || $node->v2_encryption = 'none';
            // xtls特有 
            $node->v2_flow = $request->get('v2_flow');
            // 个性化
            $node->node_uuid = $request->get('v2_uuid') ;  // node_uuid 独立节点的密码
            $request->get('v2_cdn') != '' ? $node->is_transit = 1 : $node->is_transit = 0;  // 支持 CDN 
            //
            $node->v2_mode = $request->get('v2_mode'); // 2023-02-16 新增 grpc mode
            $node->v2_servicename = $request->get('v2_servicename'); // 2023-02-20 新增 grpc serviceName
            if ($request->get('v2_id')){
                if ($request->get('v2_id') != $id){
                    $node->is_clone = $request->get('v2_id');
                }else{
                    $node->is_clone = 0;
                }
            }
        }

        $node->save();
    }

    public function clonepay(Request $request){
        $sysConf = Helpers::systemConfig();  //获取系统设置
        // 验证是否开启 clonepay
        if ($sysConf['clonepay'] != 'on') {
            exit;
        }
        // 验证 安全ip  
        $ip = $_SERVER["REMOTE_ADDR"]; // 获取请求ip
        if ($ip != $sysConf['clonepay_safeip'] && $ip != $sysConf['clonepay_safeipv6']) {
            exit;
        }
        // 验证签名
        $signStr = $request->get('order') .'&'. $request->get('money') .'&'. $sysConf['clonepay_token']; // 订单号 金额 token 生成签名
        if(md5($signStr) != $request->get('sign')){         //验证签名是否一致
            exit;
        }
        //获取 email，验证用户
        $email = $request->get('email');
        if (!$email) {
            exit;
        }
        $user = User::query()->where('username',$email)->first();
        // 验证用户
        if (!$user->id) {
            exit;
        }
        // 验证订单号是否已存在
        $exsitcoupon = Coupon::query()->where('sn',$request->get('order'))->first();
        if (!empty($exsitcoupon->id)) {
            echo '&error=订单已被记录';
            exit;
        }
        // 计算 money ,这里是按照 分计算的 所以 * 100
        $money = $request->money * 100;
        // 开始写入 充值记录 和返利
        $obj = new Coupon();
        $obj->name = 'CP代付';
        $obj->sn =  $request->order;  //订单
        $obj->logo = '';
        $obj->type = 3;  // 3=充值券
        $obj->usage = 1;
        $obj->amount = $money;  //金额
        $obj->discount = 0;
        $obj->available_start = time();
        $obj->available_end = time();
        $obj->status = 1;   //状态  1=已使用
        $obj->user_id = $user->id; //使用者
        $obj->created_at = date("Y-m-d H:i:s", $request->time);
        $obj->updated_at = date("Y-m-d H:i:s", $request->time);
        $obj->save();
        // 开始写入用户充值
        $user->balance += $money ;
        $user->save();
        //写入用户充值记录
        // 写入卡券日志
        Helpers::addCouponLog($obj->id, 0, 0, $user->id, 'CP代付充值使用');
        
    }
}
