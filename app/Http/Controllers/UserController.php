<?php

namespace App\Http\Controllers;

use App\Components\Helpers;
use App\Components\ServerChan;
use App\Http\Models\Article;
use App\Http\Models\Coupon;
use App\Http\Models\Goods;
use App\Http\Models\GoodsLabel;
use App\Http\Models\Invite;
use App\Http\Models\Order;
use App\Http\Models\ReferralApply;
use App\Http\Models\ReferralLog;
use App\Http\Models\SsGroup;
use App\Http\Models\SsNodeInfo;
use App\Http\Models\SsNode;   //显示流量监控 song
use App\Http\Models\SsNodeTrafficDaily; // 节点流量日志 song
use App\Http\Models\SsNodeTrafficHourly;    //song
use App\Http\Models\SsNodeLabel;
use App\Http\Models\Ticket;
use App\Http\Models\TicketReply;
use App\Http\Models\User;

// Cncdn cdn自选入口功能
use App\Http\Models\Cncdn;
//
use App\Http\Models\UserLabel;
use App\Http\Models\UserSubscribe;
use App\Http\Models\UserTrafficDaily;
use App\Http\Models\UserTrafficHourly;
use App\Mail\newTicket;
use App\Mail\replyTicket;
use Cache;
use Illuminate\Http\Request;
use Redirect;
use Response;
use Session;
use Mail;
use Log;
use DB;
use Auth;
use Hash;
use Validator;

/**
 * 用户控制器
 *
 * Class UserController
 *
 * @package App\Http\Controllers
 */
class UserController extends Controller
{
    protected static $systemConfig;

    function __construct()
    {
        self::$systemConfig = Helpers::systemConfig();
    }

    public function index(Request $request)
    {

        //Song公告列表获取
        //$view['noticeList'] = Article::query()->where('type', 2)->where('is_del', 0)->orderBy('sort', 'desc')->orderBy('id', 'desc')->limit(10)->get();
        //
        $dailyData = [];
        $hourlyData = [];
        // 用户一个月内的流量
        $userTrafficDaily = UserTrafficDaily::query()->where('user_id', Auth::user()->id)->where('node_id', 0)->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))->orderBy('created_at', 'asc')->pluck('total')->toArray();

        $dailyTotal = 30;  // date('d', time()) - 1; // 今天不算，减一
        $dailyCount = count($userTrafficDaily);
        for ($x = 0; $x < ($dailyTotal - $dailyCount); $x++) {
            $dailyData[$x] = 0;
        }
        for ($x = ($dailyTotal - $dailyCount); $x < $dailyTotal; $x++) {
            $dailyData[$x] = round($userTrafficDaily[$x - ($dailyTotal - $dailyCount)] / (1024 * 1024 * 1024), 3);
        }

        // 用户一天内的流量
        $userTrafficHourly = UserTrafficHourly::query()->where('user_id', Auth::user()->id)->where('node_id', 0)->where('created_at', '>=', date('Y-m-d', time()))->orderBy('created_at', 'asc')->pluck('total')->toArray();
        $hourlyTotal = date('H', time());
        $hourlyCount = count($userTrafficHourly);
        for ($x = 0; $x < ($hourlyTotal - $hourlyCount); $x++) {
            $hourlyData[$x] = 0;
        }
        for ($x = ($hourlyTotal - $hourlyCount); $x < $hourlyTotal; $x++) {
            $hourlyData[$x] = round($userTrafficHourly[$x - ($hourlyTotal - $hourlyCount)] / (1024 * 1024 * 1024), 3);
        }

        /**  // 本月天数数据
        $monthDays = [];
        $monthHasDays = date("t");
        for ($i = 1; $i <= $monthHasDays; $i++) {
            $monthDays[] = $i;
        } **/

        $view['trafficDaily'] = "'" . implode("','", $dailyData) . "'";
        $view['trafficHourly'] = "'" . implode("','", $hourlyData) . "'";
        //$view['monthDays'] = "'" . implode("','", $monthDays) . "'";
        $view['notice'] = Article::type(2)->orderBy('id', 'desc')->first(); // 公告

        return Response::view('user.index', $view);
    }

    // 签到
    public function checkIn(Request $request)
    {
        // 系统开启登录加积分功能才可以签到
        if (!self::$systemConfig['is_checkin']) {
            return Response::json(['status' => 'fail', 'message' => '系统未开启签到功能']);
        }

        // 已签到过，验证是否有效
        if (Cache::has('userCheckIn_' . Auth::user()->id)) {
            return Response::json(['status' => 'fail', 'message' => '已经签到过了，明天再来吧']);
        }

        $traffic = mt_rand(self::$systemConfig['min_rand_traffic'], self::$systemConfig['max_rand_traffic']);
        $ret = User::uid()->increment('transfer_enable', $traffic * 1048576);
        if (!$ret) {
            return Response::json(['status' => 'fail', 'message' => '签到失败，系统异常']);
        }

        // 写入用户流量变动记录
        Helpers::addUserTrafficModifyLog(Auth::user()->id, 0, Auth::user()->transfer_enable, Auth::user()->transfer_enable + $traffic * 1048576, '[签到]');

        // 多久后可以再签到
        $ttl = self::$systemConfig['traffic_limit_time'] ? self::$systemConfig['traffic_limit_time'] : 1440;
        Cache::put('userCheckIn_' . Auth::user()->id, '1', $ttl);

        return Response::json(['status' => 'success', 'message' => '签到成功，系统送您 ' . $traffic . 'M 流量']);
    }

    //订阅教程
    // 节点列表
    public function subscribe(Request $request)
    {
        // 在线安装APP
        //$view['ipa_list'] = 'itms-services://?action=download-manifest&url=' . self::$systemConfig['website_url'] . '/clients/ipa.plist';

        // 订阅连接
        $view['link'] = (self::$systemConfig['subscribe_domain'] ? self::$systemConfig['subscribe_domain'] : self::$systemConfig['website_url']) . '/s/' . Auth::user()->subscribe->code;

        if (Auth::user()->status < 1 ) {
            $view['link'] = '您的账号处理保护状态，请退出后验证账号安全    ';
        }

        // 订阅连接二维码
        //$view['link_qrcode'] = 'sub://' . base64url_encode($view['link']) . '#' . base64url_encode(self::$systemConfig['website_name']);

        return Response::view('user.subscribe', $view);
    }

    // 节点列表
    public function nodeList(Request $request)
    {
        // 在线安装APP
        $view['ipa_list'] = 'itms-services://?action=download-manifest&url=' . self::$systemConfig['website_url'] . '/clients/ipa.plist';

        // 订阅连接
        $view['link'] = (self::$systemConfig['subscribe_domain'] ? self::$systemConfig['subscribe_domain'] : self::$systemConfig['website_url']) . '/s/' . Auth::user()->subscribe->code;

        // 订阅连接二维码
        $view['link_qrcode'] = 'sub://' . base64url_encode($view['link']) . '#' . base64url_encode(self::$systemConfig['website_name']);

        // 节点列表
        $userLabelIds = UserLabel::uid()->pluck('label_id');
        if (empty($userLabelIds)) {
            $view['nodeList'] = [];
            $view['allNodes'] = '';

            return Response::view('user.nodeList', $view);
        }

        // 获取当前用户可用节点
        $nodeList = DB::table('ss_node')
            ->selectRaw('ss_node.*')
            //->leftJoin('ss_node_label', 'ss_node.id', '=', 'ss_node_label.node_id')
            //->whereIn('ss_node_label.label_id', $userLabelIds)
            ->where('ss_node.status', 1)
            ->where('ss_node.node_group',Auth::user()->node_group)
            ->where('ss_node.level','<=',Auth::user()->level)
            //->groupBy('ss_node.id')
            //->orderBy('ss_node.sort', 'desc')
            ->orderBy('ss_node.node_onload', 'asc')
            //->orderBy('ss_node.id', 'asc')
            //->limit(21) //Song
            ->get();

        //$allNodes = ''; // 全部节点SSR链接，用于一键复制所有节点
        foreach ($nodeList as &$node) {
            //Song
            // 节点标签
            $node->labels = SsNodeLabel::query()->with('labelInfo')->where('node_id', $node->id)->get();
        }

        $view['allNodes'] = '';
        $view['nodeList'] = $nodeList;

        /*// 使用教程
        $view['tutorial1'] = Article::type(4)->where('sort', 1)->orderBy('id', 'desc')->first();
        $view['tutorial2'] = Article::type(4)->where('sort', 2)->orderBy('id', 'desc')->first();
        $view['tutorial3'] = Article::type(4)->where('sort', 3)->orderBy('id', 'desc')->first();
        $view['tutorial4'] = Article::type(4)->where('sort', 4)->orderBy('id', 'desc')->first();
        $view['tutorial5'] = Article::type(4)->where('sort', 5)->orderBy('id', 'desc')->first();
        $view['tutorial6'] = Article::type(4)->where('sort', 6)->orderBy('id', 'desc')->first();*/

        return Response::view('user.nodeList', $view);
    }

    // 公告详情
    public function article(Request $request)
    {
        $view['info'] = Article::query()->findOrFail($request->id);

        return Response::view('user.article', $view);
    }

    // 修改个人资料
    public function profile(Request $request)
    {
        if ($request->isMethod('POST')) {
            $old_password = trim($request->get('old_password'));
            $new_password = trim($request->get('new_password'));
            $wechat = $request->get('wechat');
            $alipay = $request->get('alipay');
            $qq = $request->get('qq');
            $usdt = $request->get('usdt');
            $passwd = trim($request->get('passwd'));
            $vmess_id = trim($request->get('vmess_id'));
            $cncdn = trim($request->get('cncdn'));
            $cfcdn = trim($request->get('cfcdn'));
            //$cn_update = trim($request->get('cn_update'));

            // 修改密码
            if ($old_password && $new_password) {
                if (!Hash::check($old_password, Auth::user()->password)) {
                    return Redirect::to('profile#tab_1')->withErrors('旧密码错误，请重新输入');
                } elseif (Hash::check($new_password, Auth::user()->password)) {
                    return Redirect::to('profile#tab_1')->withErrors('新密码不可与旧密码一样，请重新输入');
                }

                // 演示环境禁止改管理员密码
                if (env('APP_DEMO') && Auth::user()->id == 1) {
                    return Redirect::to('profile#tab_1')->withErrors('演示环境禁止修改管理员密码');
                }

                $ret = User::uid()->update(['password' => Hash::make($new_password)]);
                if (!$ret) {
                    return Redirect::to('profile#tab_1')->withErrors('修改失败');
                } else {
                    return Redirect::to('profile#tab_1')->with('successMsg', '修改成功');
                }
            }

            // 修改联系方式
            if ($wechat || $qq || $alipay || $usdt) {
                // if (empty(clean($wechat)) && empty(clean($qq))) {
                //     return Redirect::to('profile#tab_2')->withErrors('修改失败');
                // }

                $ret = User::uid()->update(['wechat' => $wechat,'alipay' => $alipay , 'qq' => $qq , 'usdt' => $usdt]);
                if (!$ret) {
                    return Redirect::to('profile#tab_2')->withErrors('修改失败');
                } else {
                    return Redirect::to('profile#tab_2')->with('successMsg', '修改成功');
                }
            }

            // 修改代理密码
            if ($passwd || $vmess_id) {
                $ret = User::uid()->update(['passwd' => $passwd,'vmess_id' => $vmess_id]);
                if (!$ret) {
                    return Redirect::to('profile#tab_3')->withErrors('修改失败');
                } else {
                    return Redirect::to('profile#tab_3')->with('successMsg', '修改成功');
                }
            }

            //修改 cncdn
            if ($cncdn) {
                $cncdn == 666 && $cncdn = '';
                $ret = User::uid()->update(['cncdn' => $cncdn]);
                if (!$ret) {
                    return Redirect::to('profile#tab_4')->withErrors('修改失败');
                } else {
                    return Redirect::to('profile#tab_4')->with('successMsg', '修改成功,请客户端更新节点');
                }
            }

            //修改 cfcdn
            if ( isset($cfcdn) ) {
                if (!filter_var($cfcdn, FILTER_VALIDATE_IP)) {
                    $cfcdn='';
                }
                $ret = User::uid()->update(['cfcdn' => $cfcdn]);
                if (!$ret) {
                    return Redirect::to('profile#tab_6')->withErrors('修改失败');
                } else {
                    return Redirect::to('profile#tab_6')->with('successMsg', '修改成功,请客户端更新节点');
                }
            }

            return Redirect::to('profile')->withErrors('参数错误');
        } else {
            $view['cncdns'] = Cncdn::where('status',1)->where('show',1)->get();
            return Response::view('user.profile',$view);
        }
    }

    // 商品列表
    public function services(Request $request)
    {
        // 余额充值商品，只取10个
        $view['chargeGoodsList'] = Goods::type(3)->orderBy('price', 'asc')->limit(10)->get();

        // 套餐列表
        $view['packageList'] = Goods::type(2)->limit(12)->get();

        // 流量包列表
        $view['trafficList'] = Goods::type(1)->limit(12)->get();

        // 购买说明
        $view['direction'] = Article::type(3)->orderBy('id', 'desc')->first();

        return Response::view('user.services', $view);
    }

    // 工单
    public function ticketList(Request $request)
    {
        $view['ticketList'] = Ticket::uid()->orderBy('id', 'desc')->paginate(16)->appends($request->except('page'));

        if ( $request->get('search')  ) {
            $keyword = trim($request->get('search'));
            $view['openTicket'] = Ticket::where('open',1)->where('content','like','%'.$keyword.'%')->orderBy('updated_at', 'desc')->paginate(32)->appends($request->except('page'));
        }else{
            $view['openTicket'] = Ticket::where('open',1)->orderBy('updated_at', 'desc')->paginate(32)->appends($request->except('page'));
        }
        

        return Response::view('user.ticketList', $view);
    }

    // 订单
    public function invoices(Request $request)
    {
        $view['fakapay'] = self::$systemConfig['fakapay'];
        $view['fakapay_10url'] = self::$systemConfig['fakapay_10url'];
        $view['fakapay_100url'] = self::$systemConfig['fakapay_100url'];

        $view['clonepay'] = self::$systemConfig['clonepay'];
        $sign = Auth::user()->username . '&' . date('Ymd') . '&'.self::$systemConfig['clonepay_token'];
        $key = md5($sign);
        $view['clonepay_url'] = self::$systemConfig['clonepay_homeurl'] .'&regname=user'.Auth::user()->id .'&regemail='.Auth::user()->username.'&regkey='.$key;


        $view['orderList'] = Order::uid()->with(['user', 'goods', 'coupon', 'payment'])->orderBy('oid', 'desc')->paginate(10)->appends($request->except('page'));
        $view['couponList'] = Coupon::where('user_id',Auth::user()->id)->orderBy('updated_at', 'desc')->paginate(10)->appends($request->except('page'));

        return Response::view('user.invoices', $view);
    }

    // 订单明细
    public function invoiceDetail(Request $request, $sn)
    {
        $view['order'] = Order::uid()->with(['goods', 'coupon', 'payment'])->where('order_sn', $sn)->firstOrFail();

        return Response::view('user.invoiceDetail', $view);
    }

    // 添加工单
    public function addTicket(Request $request)
    {
        $title = $request->get('title');
        $content = clean($request->get('content'));
        $content = str_replace("eval", "", str_replace("atob", "", $content));

        if (Auth::user()->level < 1) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '请先购买商品升级您的等级']);
        }

        if (empty($title) || empty($content)) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '请输入标题和内容']);
        }

        $obj = new Ticket();
        $obj->user_id = Auth::user()->id;
        $obj->sort += Auth::user()->level +100;
        $obj->title = $title;
        $obj->content = $content;
        $obj->status = 0;
        $obj->open = 0;
        $obj->save();

        //每个工单扣除 0.33元
        User::query()->where('id', Auth::user()->id)->decrement('balance', 33);

        if ($obj->id) {
            $emailTitle = "新工单提醒";
            $content = "标题：【" . $title . "】<br>内容：" . $content;

/** Song
            // 发邮件通知管理员
            if (self::$systemConfig['crash_warning_email']) {
                $logId = Helpers::addEmailLog(self::$systemConfig['crash_warning_email'], $emailTitle, $content);
                Mail::to(self::$systemConfig['crash_warning_email'])->send(new newTicket($logId, $emailTitle, $content));
            }

            // 通过ServerChan发微信消息提醒管理员
            if (self::$systemConfig['is_server_chan'] && self::$systemConfig['server_chan_key']) {
                ServerChan::send($emailTitle, $content);
            }
**/
            return Response::json(['status' => 'success', 'data' => '', 'message' => '提交成功']);
        } else {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '提交失败']);
        }
    }

    // 回复工单
    public function replyTicket(Request $request)
    {
        $id = intval($request->get('id'));

        $ticket = Ticket::uid()->with('user')->where('id', $id)->firstOrFail();

        if ($request->isMethod('POST')) {
            $content = clean($request->get('content'));
            $content = str_replace("eval", "", str_replace("atob", "", $content));
            $content = substr($content, 0, 300);

            if (empty($content)) {
                return Response::json(['status' => 'fail', 'data' => '', 'message' => '回复内容不能为空']);
            }

            $obj = new TicketReply();
            $obj->ticket_id = $id;
            $obj->user_id = Auth::user()->id;
            $obj->content = $content;
            $obj->save();

            // 每个工单扣除 0.33元
            User::query()->where('id', Auth::user()->id)->decrement('balance', 33);

            if ($obj->id) {
                // 重新打开工单
                $ticket->status = 0;
                // 工单设置为 不展示
                $ticket->open = 0;
                $ticket->sort += Auth::user()->level + 100;
                //$ticket->created_at = time();
                $ticket->updated_at = time();
                $ticket->save();

                //song
                $title = $id . "--回复";
                //
                $content = "标题：【" . $ticket->title . "】<br> https://web.ssvss.xyz/ticket/replyTicket?id=" .$id. "<br>用户回复：" . $content;
/**
                // 发邮件通知管理员
                if (self::$systemConfig['crash_warning_email']) {
                    $logId = Helpers::addEmailLog(self::$systemConfig['crash_warning_email'], $title, $content);
                    Mail::to(self::$systemConfig['crash_warning_email'])->send(new replyTicket($logId, $title, $content));
                }

                ServerChan::send($title, $content);
**/
                return Response::json(['status' => 'success', 'data' => '', 'message' => '回复成功']);
            } else {
                return Response::json(['status' => 'fail', 'data' => '', 'message' => '回复失败']);
            }
        } else {
            $view['ticket'] = $ticket;
            $view['replyList'] = TicketReply::query()->where('ticket_id', $id)->with('user')->orderBy('id', 'asc')->get();

            return Response::view('user.replyTicket', $view);
        }
    }

    // 查看工单
    public function viewTicket(Request $request)
    {
        $id = intval($request->get('id'));

        $view['ticket'] = Ticket::where('id', $id)->where('open',1)->first();
        $view['replyList'] = TicketReply::query()->where('ticket_id', $id)->orderBy('id', 'asc')->get();
        return Response::view('user.viewTicket', $view);
    }

    // 关闭工单
    public function closeTicket(Request $request)
    {
        $id = $request->get('id');

        $ret = Ticket::uid()->where('id', $id)->update(['status' => 2,'sort' => 0]);
        if ($ret) {
            ServerChan::send('工单关闭提醒', '工单：ID' . $id . '用户已手动关闭');

            return Response::json(['status' => 'success', 'data' => '', 'message' => '关闭成功']);
        } else {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '关闭失败']);
        }
    }

    // 邀请码
    public function invite(Request $request)
    {
        // 已生成的邀请码数量
        $num = Invite::uid()->count();

        $view['num'] = self::$systemConfig['invite_num'] - $num <= 0 ? 0 : self::$systemConfig['invite_num'] - $num; // 还可以生成的邀请码数量
        $view['inviteList'] = Invite::uid()->with(['generator', 'user'])->paginate(10); // 邀请码列表
        $view['referral_traffic'] = flowAutoShow(self::$systemConfig['referral_traffic'] * 1048576);
        $view['referral_percent'] = self::$systemConfig['referral_percent'];

        return Response::view('user.invite', $view);
    }

    // 生成邀请码
    public function makeInvite(Request $request)
    {
        // 已生成的邀请码数量
        $num = Invite::uid()->count();
        if ($num >= self::$systemConfig['invite_num']) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '生成失败：最多只能生成' . self::$systemConfig['invite_num'] . '个邀请码']);
        }

        $obj = new Invite();
        $obj->uid = Auth::user()->id;
        $obj->fuid = 0;
        $obj->code = strtoupper(mb_substr(md5(microtime() . makeRandStr()), 8, 12));
        $obj->status = 0;
        $obj->dateline = date('Y-m-d H:i:s', strtotime("+" . self::$systemConfig['user_invite_days'] . " days"));
        $obj->save();

        return Response::json(['status' => 'success', 'data' => '', 'message' => '生成成功']);
    }

    // 使用优惠券
    public function redeemCoupon(Request $request)
    {
        $coupon_sn = $request->get('coupon_sn');

        if (empty($coupon_sn)) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '优惠券不能为空']);
        }

        $coupon = Coupon::query()->where('sn', $coupon_sn)->whereIn('type', [1, 2])->orderBy('id','desc')->first();
        if (!$coupon) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '该优惠券不存在']);
        } elseif ($coupon->status == 1) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '该优惠券已使用，请换一个试试']);
        } elseif ($coupon->status == 2) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '该优惠券已失效，请换一个试试']);
        } elseif ($coupon->available_end < time()) {
            $coupon->status = 2;
            $coupon->save();
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '该优惠券已失效，请换一个试试']);
        } elseif ($coupon->available_start > time()) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '该优惠券尚不可用，请换一个试试']);
        }

        if (strstr($coupon_sn, 'edu.cn') == 'edu.cn') {  // coupon 以 edu.cn结尾的话，
            if (strstr(Auth::user()->username, $coupon_sn) != $coupon_sn) {  // 但是用户不是 edu用户的话，不能用这个 优惠券
                return Response::json(['status' => 'fail', 'data' => '', 'message' => '此优惠券为 '.$coupon->name.' 专享优惠券']);
            }
        }

        $data = [
            'type'     => $coupon->type,
            'amount'   => $coupon->amount,
            'discount' => $coupon->discount
        ];

        return Response::json(['status' => 'success', 'data' => $data, 'message' => '该优惠券有效']);
    }

    // 购买服务
    public function buy(Request $request, $id)
    {
        $goods_id = intval($id);
        $coupon_sn = $request->get('coupon_sn');

        if ($request->isMethod('POST')) {
            $goods = Goods::query()->with(['label'])->where('status', 1)->where('id', $goods_id)->first();
            if (!$goods) {
                return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：商品或服务已下架']);
            }

/*
            // 如果商品等级 不允许购买比自己等级低的商品
            if ($goods->sort < Auth::user()->level) {
                # code...
                return Response::json(['status' => 'fail', 'data' => '', 'message' => '购买失败，商品等级小于用户等级']);
            }
*/

            // 限购控制：all-所有商品限购, free-价格为0的商品限购, none-不限购（默认）
            $strategy = self::$systemConfig['goods_purchase_limit_strategy'];
            if ($strategy == 'all' || ($strategy == 'package' && $goods->type == 2) || ($strategy == 'free' && $goods->price == 0) || ($strategy == 'package&free' && ($goods->type == 2 || $goods->price == 0))) {
                $noneExpireGoodExist = Order::uid()->where('status', '>=', 0)->where('is_expire', 0)->where('goods_id', $goods_id)->exists();
                if ($noneExpireGoodExist) {
                    return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：商品不可重复购买']);
                }
            }

            // 单个商品限购
            if ($goods->is_limit == 1) {
                $noneExpireOrderExist = Order::uid()->where('status', '>=', 0)->where('goods_id', $goods_id)->exists();
                if ($noneExpireOrderExist) {
                    return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：此商品每人限购1次']);
                }
            }

            // 使用优惠券
            if (!empty($coupon_sn)) {
                $coupon = Coupon::query()->where('status', 0)->whereIn('type', [1, 2])->where('sn', $coupon_sn)->orderBy('id','desc')->first();
                if (empty($coupon)) {
                    return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：优惠券不存在']);
                }

                // EDU 专用优惠券
                if (strstr($coupon_sn, 'edu.cn') == 'edu.cn') {  // coupon 以 edu.cn结尾的话，
                    if (strstr(Auth::user()->username, $coupon_sn) != $coupon_sn) {  // 但是用户不是 edu用户的话，不能用这个 优惠券
                        return Response::json(['status' => 'fail', 'data' => '', 'message' => '此优惠券为 '.$coupon->name.' 专享优惠券']);
                    }
                }

                // 计算实际应支付总价
                $amount = $coupon->type == 2 ? $goods->price * $coupon->discount / 10 : $goods->price - $coupon->amount;
                $amount = $amount > 0 ? $amount : 0;
            } else {
                $amount = $goods->price;
            }

            // 价格异常判断
            if ($amount < 0) {
                return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：订单总价异常']);
            }
            // 验证账号余额是否充足
            $user = User::uid()->first();
/*
            // song 统计所有用户 充值金额
            $user_coupons = Coupon::type(3)->where('user_id', $user->id)->where('status', 1)->sum('amount');

            //检测 商品金额不能低于用户充值总金额的 10%;
            if ($amount > $user_coupons / 10 ) {
                # code...
                return Response::json(['status' => 'fail', 'data' => '', 'message' => '累计充值满'.$amount /10 .'就能购买本套餐:)']);

            }
*/

            // 余额 + 信用额度  > 支付的商品价格才允许购买
            if ($user->balance + $user->credit < $amount) {
                return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：您的额度不足，充值或邀请好友试试？']);
            }


/*
            // 验证账号是否存在有效期更长的套餐
            if ($goods->type == 2) {
                $existOrderList = Order::uid()
                    ->with(['goods'])
                    ->whereHas('goods', function ($q) {
                        $q->where('type', 2);
                    })
                    ->where('is_expire', 0)
                    ->where('status', 2)
                    ->get();

                foreach ($existOrderList as $vo) {
                    if ($vo->goods->days > $goods->days) {
                        return Response::json(['status' => 'fail', 'data' => '', 'message' => '已拒绝：您已存在有效期更长的帝王套餐，不能忤逆自己呦']);
                    }
                }
            }
            */

            DB::beginTransaction();
            try {
                // 生成订单
                $order = new Order();
                $order->order_sn = date('ymdHis') . mt_rand(100000, 999999);
                $order->user_id = $user->id;
                $order->goods_id = $goods_id;
                $order->coupon_id = !empty($coupon) ? $coupon->id : 0;
                $order->origin_amount = $goods->price;
                $order->amount = $amount;
                $order->expire_at = date("Y-m-d H:i:s", strtotime("+" . $goods->days . " days"));
                $order->is_expire = 0;
                $order->pay_way = 1;
                $order->status = 2;
                $order->save();

                // 扣余额
                User::query()->where('id', $user->id)->decrement('balance', $amount);

                // 记录余额操作日志
                $this->addUserBalanceLog($user->id, $order->oid, $user->balance, $user->balance - $amount, -1 * $amount, '购买服务：' . $goods->name);

                // 优惠券置为已使用
                if (!empty($coupon)) {
                    if ($coupon->usage == 1) {
                        $coupon->status = 1;
                        // song 这里记录一下优惠券使用的是谁
                        $coupon->user_id = $user->id;
                        $coupon->save();
                    }

                    // 写入日志
                    Helpers::addCouponLog($coupon->id, $goods_id, $order->oid, $user->id,'余额支付订单使用');
                }

/*
                // 如果买的是套餐，则先将之前购买的所有套餐置都无效，并扣掉之前所有套餐的流量，重置用户已用流量为0
                if ($goods->type == 2) {
                    $existOrderList = Order::query()
                        ->with(['goods'])
                        ->whereHas('goods', function ($q) {
                            $q->where('type', 2);
                        })
                        ->where('user_id', $order->user_id)
                        ->where('oid', '<>', $order->oid)
                        ->where('is_expire', 0)
                        ->where('status', 2)
                        ->get();

                    foreach ($existOrderList as $vo) {
                        Order::query()->where('oid', $vo->oid)->update(['is_expire' => 1]);

                        // 先判断，防止手动扣减过流量的用户流量被扣成负数
                        if ($order->user->transfer_enable - $vo->goods->traffic * 1048576 <= 0) {
                            // 写入用户流量变动记录
                            Helpers::addUserTrafficModifyLog($user->id, $order->oid, 0, 0, '[余额支付]用户购买套餐，先扣减之前套餐的流量(扣完)');

                            User::query()->where('id', $order->user_id)->update(['u' => 0, 'd' => 0, 'transfer_enable' => 0]);
                        } else {
                            // 写入用户流量变动记录
                            $user = User::query()->where('id', $user->id)->first(); // 重新取出user信息
                            Helpers::addUserTrafficModifyLog($user->id, $order->oid, $user->transfer_enable, ($user->transfer_enable - $vo->goods->traffic * 1048576), '[余额支付]用户购买套餐，先扣减之前套餐的流量(未扣完)');

                            User::query()->where('id', $order->user_id)->update(['u' => 0, 'd' => 0]);
                            User::query()->where('id', $order->user_id)->decrement('transfer_enable', $vo->goods->traffic * 1048576);
                        }
                    }
                }
*/

                $user = User::query()->where('id', $user->id)->first(); // 重新取出user信息
                // 写入用户流量变动记录
                Helpers::addUserTrafficModifyLog($user->id, $order->oid, $user->transfer_enable, ($user->transfer_enable + $goods->traffic * 1048576), '[余额支付]用户购买商品，加上流量');
                // 把商品的流量加到账号上
                User::query()->where('id', $user->id)->increment('transfer_enable', $goods->traffic * 1048576);

                // 计算账号过期时间
                if ($user->expire_time < date('Y-m-d', strtotime("+" . $goods->days . " days"))) {
                    $expireTime = date('Y-m-d', strtotime("+" . $goods->days . " days"));
                } else {
                    $expireTime = $user->expire_time;
                }

                //这个是不管怎样都把账号的过期时间加到账号上。我觉的也无可厚非
                //$expireTime  = date('Y-m-d', strtotime($user->expire_time ."+" . $goods->days . " days" ));

                // 套餐的话，就要改流量重置日，同时把流量写入到transfer_montly
                if ($goods->type == 2) {
                    if (date('m') == 2 && date('d') == 29) {
                        $traffic_reset_day = 28;
                    } else {
                        // 更改套餐重置日
                        $traffic_reset_day = date('d') == 31 ? 30 : abs(date('d'));
                    }
                    User::query()->where('id', $order->user_id)->update(['traffic_reset_day' => $traffic_reset_day, 'expire_time' => $expireTime, 'enable' => 1]);
                    //是按时间买套餐 的话， 流量写入到 transfer_monthly 表中
                    User::query()->where('id', $user->id)->increment('transfer_monthly', $goods->traffic * 1048576);
                } else {
                    User::query()->where('id', $order->user_id)->update(['expire_time' => $expireTime, 'enable' => 1]);
                }

                /* 这里不再需要标签功能
                // 写入用户标签
                if ($goods->label) {
                    // 用户默认标签
                    $defaultLabels = [];
                    if (self::$systemConfig['initial_labels_for_user']) {
                        $defaultLabels = explode(',', self::$systemConfig['initial_labels_for_user']);
                    }

                    // 取出现有的标签
                    $userLabels = UserLabel::query()->where('user_id', $user->id)->pluck('label_id')->toArray();
                    $goodsLabels = GoodsLabel::query()->where('goods_id', $goods_id)->pluck('label_id')->toArray();

                    // 标签去重
                    $newUserLabels = array_values(array_unique(array_merge($userLabels, $goodsLabels, $defaultLabels)));

                    // 删除用户所有标签
                    UserLabel::query()->where('user_id', $user->id)->delete();

                    // 生成标签
                    foreach ($newUserLabels as $vo) {
                        $obj = new UserLabel();
                        $obj->user_id = $user->id;
                        $obj->label_id = $vo;
                        $obj->save();
                    }
                }
                */

                // 更新用户等级  商品等级 > 用户等级，则更新用户等级
                if ($goods->level > $user->level) {
                    # code...
                    User::query()->where('id', $order->user_id)->update(['level' => $goods->level]);
                }

                // 写入邀请返利， 用户购买商品后 balance >= 0 才给返利  同时 只有 124805这个编号以上的用户才给返利 也就是新用户才返利
                if ( $user->id > 124804 && $user->referral_uid && $user->balance >= 0) {
                    $this->addReferralLog($user->id, $user->referral_uid, $order->oid, $amount, $amount * self::$systemConfig['referral_percent']);
                }

                // 取消重复返利  Song 允许重复返利
                //User::query()->where('id', $order->user_id)->update(['referral_uid' => 0]);

                DB::commit();
                return Response::json(['status' => 'success', 'data' => '', 'message' => '支付成功']);
            } catch (\Exception $e) {
                DB::rollBack();

                Log::error('支付订单失败：' . $e->getMessage());

                return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：' . $e->getMessage()]);
            }
        } else {
            $goods = Goods::query()->where('id', $goods_id)->where('status', 1)->first();
            if (empty($goods)) {
                return Redirect::to('services');
            }

            // 余额充值商品，只取10个
            $view['chargeGoodsList'] = Goods::type(3)->orderBy('price', 'asc')->limit(10)->get();

            $view['goods'] = $goods;

            return Response::view('user.buy', $view);
        }
    }

    // 推广返利
    public function referral(Request $request)
    {
        $view['referral_traffic'] = flowAutoShow(self::$systemConfig['referral_traffic'] * 1048576);
        $view['referral_percent'] = self::$systemConfig['referral_percent'];
        $view['referral_money'] = self::$systemConfig['referral_money'];
        $view['totalAmount'] = ReferralLog::uid()->sum('ref_amount') / 100;
        $view['canAmount'] = ReferralLog::uid()->where('status', 0)->sum('ref_amount') / 100;
        $view['link'] = self::$systemConfig['website_url'] . '/register?aff=' . Auth::user()->id;
        $view['referralLogList'] = ReferralLog::uid()->with('user')->orderBy('id', 'desc')->paginate(10);
        $view['referralApplyList'] = ReferralApply::uid()->with('user')->orderBy('id', 'desc')->paginate(10);
        $view['referralUserList'] = User::query()->select(['username', 'created_at'])->where('referral_uid', Auth::user()->id)->orderBy('id', 'desc')->paginate(10);
        $view['couponList'] = Coupon::query()->where('name',Auth::user()->id)->where('creat_user',Auth::user()->id)->orderBy('id','desc')->paginate(10);
        //sdo2022-04-28 邀请返利统计
        $view['my_affmoney'] = ReferralLog::uid()->where('status', 0)->where('order_id',0)->where('amount',0)->sum('ref_amount');  //邀请返利
        $view['my_refmoney'] = ReferralLog::uid()->where('status', 0)->where('order_id','>',0)->where('amount','!=',0)->sum('ref_amount'); //消费返利

        return Response::view('user.referral', $view);
    }

    // 申请提现
    // 已经在路由那里禁用了
    public function extractMoney(Request $request)
    {
        // 判断账户是否过期
        if (Auth::user()->expire_time < date('Y-m-d')) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：账号已过期，请先购买服务吧']);
        }

/*
        // 判断是否已存在申请
        $referralApply = ReferralApply::uid()->whereIn('status', [0, 1])->first();
        if ($referralApply) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：已存在申请，请等待之前的申请处理完']);
        }
*/
        // 校验可以提现金额是否超过系统设置的阀值
// 之前这里有过bug，导致 用户在提现的时候，金额计算的是，用户邀请返利的所有金额。但是，却只把消费返利的 给 设置为1 了。
        $ref_amount = ReferralLog::uid()->where('status', 0)->where('order_id','>',0)->sum('ref_amount');
        $ref_amount = $ref_amount / 100;
        if ($ref_amount < self::$systemConfig['referral_money']) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：满' . self::$systemConfig['referral_money'] . '元才可以银行卡，继续努力吧']);
        }

/*
        //加一个功能 song 如果消费返利 < 注册返利，那么就无法申请提现 判定订单中 订单为0 的 比例
        $reg_money = ReferralLog::uid()->where('status', 0)->where('order_id',0)->sum('ref_amount');
        // 这里取 邀请注册返利占比不能大于 1/2
        if ($reg_money > 0) {  //*50 = *100 /2
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：包含注册返利！']);
        }
*/
        // 取出本次申请关联返利日志ID
        $link_logs = '';
        $referralLog = ReferralLog::uid()->where('status', 0)->where('order_id','>',0)->get();
        foreach ($referralLog as $log) {
            $link_logs .= $log->id . ',';
            #这里自动将 返利的那个 已返利变为1 就是已申请
            ReferralLog::query()->where('id', $log->id)->update(['status' => 1]);
            #song 这里直接将提现记录变成1 就是已申请
        }
        $link_logs = rtrim($link_logs, ',');

        $obj = new ReferralApply();
        $obj->user_id = Auth::user()->id;
        $obj->before = $ref_amount;
        $obj->after = 0;
        $obj->amount = $ref_amount;
        $obj->link_logs = $link_logs;
        $obj->status = 0;
        $obj->save();

        return Response::json(['status' => 'success', 'data' => '', 'message' => '申请成功，记得在个人设置中添加收款信息呦']);
    }

    // 申请提现并自动审核打款进余额！ 这个不用了
    // 已经在路由那里禁用了
    public function autoExtractMoney(Request $request)
    {
        // 判断账户是否过期
        if (Auth::user()->expire_time < date('Y-m-d')) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：账号已过期，请先购买服务吧']);
        }

/*
        // 判断是否已存在申请
        $referralApply = ReferralApply::uid()->whereIn('status', [0, 1])->first();
        if ($referralApply) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：已存在申请，请等待之前的申请处理完']);
        }
*/
        // 校验可以提现金额是否超过系统设置的阀值
        $ref_amount = ReferralLog::uid()->where('status', 0)->sum('ref_amount');
        $ref_amount = $ref_amount / 100;
        if ($ref_amount < self::$systemConfig['referral_money']) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：满' . self::$systemConfig['referral_money'] . '元才可以提现，继续努力吧']);
        }

        // 取出本次申请关联返利日志ID
        $link_logs = '';
        $referralLog = ReferralLog::uid()->where('status', 0)->get();
        foreach ($referralLog as $log) {
            $link_logs .= $log->id . ',';
            #这里自动将 返利的那个 已返利变为2 就是已返利
            //referral_log  0未提现 1 审核中 2 已提现 3 代金券 4 微信 5 支付宝 6 usdt
            ReferralLog::query()->where('id', $log->id)->update(['status' => 2]);
            #song 这里直接将所有的返利记录变为2
        }
        $link_logs = rtrim($link_logs, ',');

        //写入返利申请
        $obj = new ReferralApply();
        $obj->user_id = Auth::user()->id;
        $obj->before = $ref_amount;
        $obj->after = 0;
        $obj->amount = $ref_amount;
        $obj->link_logs = $link_logs;
        #song 这里直接将提现记录变成 已提现
        //referral_apply -2 驳回请更换支付方式 -1 驳回 0 待审核 1 审核通过待打款 2 已打款 3 代金券 4 微信 5 支付宝 6 usdt
        $obj->status = 2;
        $obj->save();

        // 自动将金额打入用户账户
        DB::beginTransaction();
        try {
            $user = User::query()->where('id', Auth::user()->id)->first();
            // 写入余额变动日志
            $this->addUserBalanceLog($user->id, 0, $user->balance, $user->balance + $ref_amount, $ref_amount, '用户返利自动提款');
            //增加余额
            $user->increment('balance', $ref_amount * 100);
            DB::commit();
            return Response::json(['status' => 'success', 'data' => '', 'message' => '操作成功，已打款到余额']);
        } catch (\Exception $e) {
            DB::rollBack();
        }
    }

    // 注册返利申请提现
    public function ExtractAffMoney(Request $request)
    {
        // 判断账户是否过期
        if (Auth::user()->expire_time < date('Y-m-d')) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：账号已过期，请先购买服务吧']);
        }

        // 校验可以提现金额是否超过系统设置的阀值
        $aff_amount = ReferralLog::uid()->where('status', 0)->where('order_id',0)->where('amount',0)->sum('ref_amount');
        if ($aff_amount < self::$systemConfig['referral_money'] * 100) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：不满' . self::$systemConfig['referral_money'] . '元，继续努力吧']);
        }

        // 检验注册返利， 消费返利那里要高于 要提取的注册返利的2倍才行
        $ref_amount = ReferralLog::uid()->where('status', 0)->where('order_id','>',0)->where('amount','>',0)->sum('amount'); // 计算总消费金额
        if (empty($ref_amount) || ($ref_amount < ($aff_amount * 2) ) ) {   // 注册返利金额 不能低于 消费总额的一半！ 很重要
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：您申请提现 '.($aff_amount/100).'￥，需要被邀请用户消费满' . ($aff_amount * 2 / 100) . '元，继续努力吧']);
        }

        // 取出所有的返利日志，这样可以有
        $link_logs = '';
        $referralLog = ReferralLog::uid()->where('status', 0)->get();
        foreach ($referralLog as $log) {
            $link_logs .= $log->id . ',';
            #这里自动将 返利的那个 已返利变为1 就是已申请
            //referral_log  0未提现 1 审核中 2 已提现 3 代金券 4 微信 5 支付宝 6 usdt
            ReferralLog::query()->where('id', $log->id)->update(['status' => 1]);
            #song 这里直接将提现记录变成1 就是已申请
        }
        $link_logs = rtrim($link_logs, ',');

//referral_apply -2 驳回请更换支付方式 -1 驳回 0 待审核 1 审核通过待打款 2 已打款 3 代金券 4 微信 5 支付宝 6 usdt
        $obj = new ReferralApply();
        $obj->user_id = Auth::user()->id;
        $obj->before = $aff_amount;
        $obj->after = 0;
        $obj->amount = $aff_amount;
        $obj->link_logs = $link_logs;
        $obj->status = 0;
        $obj->save();

        return Response::json(['status' => 'success', 'data' => '', 'message' => '申请成功，记得在个人设置中添加收款信息呦']);
    }

    // 注册返利生成 代金券
    public function autoExtractAffMoney(Request $request)
    {
        // 判断账户是否过期
        if (Auth::user()->expire_time < date('Y-m-d')) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：账号已过期，请先购买服务吧']);
        }

        // // 校验可以提现金额是否超过系统设置的阀值
        $aff_amount = ReferralLog::uid()->where('status', 0)->where('order_id',0)->where('amount',0)->sum('ref_amount');
        // if ($aff_amount < self::$systemConfig['referral_money'] * 100) {
        //     return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：不满' . self::$systemConfig['referral_money'] . '元，继续努力吧']);
        // }
        //sdo2022-04-28
        if ($aff_amount < 5) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：不满5元，继续努力吧']);
        }

        // 检验注册返利， 消费返利那里要高于 要提取的注册返利的2倍才行
        $ref_amount = ReferralLog::uid()->where('status', 0)->where('order_id','>',0)->where('amount','>',0)->sum('amount'); // 计算总消费金额
        if (empty($ref_amount) || ($ref_amount < ($aff_amount * 2) ) ) {   // 注册返利金额 不能低于 消费总额的一半！ 很重要
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：不满' . ($aff_amount * 2 / 100) . '元，继续努力吧']);
        }

        // 取出所有的返利日志，这样可以有
        $link_logs = '';
        $referralLog = ReferralLog::uid()->where('status', 0)->get();
        foreach ($referralLog as $log) {
            $link_logs .= $log->id . ',';
            #这里自动将 返利的那个 已返利变为1 就是已申请
            //referral_log  0未提现 1 审核中 2 已提现 3 代金券 4 微信 5 支付宝 6 usdt
            ReferralLog::query()->where('id', $log->id)->update(['status' => 3]);
            #song 这里直接将提现记录变成1 就是已申请
        }
        $link_logs = rtrim($link_logs, ',');

//referral_apply -2 驳回请更换支付方式 -1 驳回 0 待审核 1 审核通过待打款 2 已打款 3 代金券 4 微信 5 支付宝 6 usdt
        $obj = new ReferralApply();
        $obj->user_id = Auth::user()->id;
        $obj->before = $aff_amount;
        $obj->after = 0;
        $obj->amount = $aff_amount;
        $obj->link_logs = $link_logs;
        $obj->status = 3;
        $obj->save();

        //生成代金券
        $coupon = new Coupon();
        $coupon->name = Auth::user()->id;
        $coupon->sn = Auth::user()->username.time();
        $coupon->logo = '';
        $coupon->type = 1; // 1是抵扣券
        $coupon->usage = 1; //1次性使用
        $coupon->amount = $aff_amount;
        $coupon->discount = 0;
        $coupon->available_start = time();
        $coupon->available_end = time() + 31536000;  // 一年有效期
        $coupon->status = 0;
        $coupon->creat_user = Auth::user()->id;
        $coupon->save();

        return Response::json(['status' => 'success', 'data' => '', 'message' => '申请成功，在邀请返利页面查看生成的代金券']);
    }

    // 消费返利申请提现
    public function ExtractRefMoney(Request $request)
    {
        // 判断账户是否过期
        if (Auth::user()->expire_time < date('Y-m-d')) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：账号已过期，请先购买服务吧']);
        }

        // 校验可以提现金额是否超过系统设置的阀值
        $ref_amount = ReferralLog::uid()->where('status', 0)->where('order_id','>',0)->where('amount','!=',0)->sum('ref_amount');
        if ($ref_amount < self::$systemConfig['referral_money'] * 100) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：邀请用户累计消费返利不满' . self::$systemConfig['referral_money'] . '元，继续努力吧']);
        }

        // 取出所有的返利日志，这样可以有
        $link_logs = '';
        $referralLog = ReferralLog::uid()->where('status', 0)->get();
        foreach ($referralLog as $log) {
            $link_logs .= $log->id . ',';
            #这里自动将 返利的那个 已返利变为1 就是已申请
            //referral_log  0未提现 1 审核中 2 已提现 3 代金券 4 微信 5 支付宝 6 usdt
            ReferralLog::query()->where('id', $log->id)->update(['status' => 1]);
            #song 这里直接将提现记录变成1 就是已申请审核中
        }
        $link_logs = rtrim($link_logs, ',');

//referral_apply -2 驳回请更换支付方式 -1 驳回 0 待审核 1 审核通过待打款 2 已打款 3 代金券 4 微信 5 支付宝 6 usdt
        $obj = new ReferralApply();
        $obj->user_id = Auth::user()->id;
        $obj->before = $ref_amount;
        $obj->after = 0;
        $obj->amount = $ref_amount;
        $obj->link_logs = $link_logs;
        $obj->status = 0;
        $obj->save();

        return Response::json(['status' => 'success', 'data' => '', 'message' => '申请成功，记得在个人设置中添加收款信息呦']);
    }

    // 消费返利申请提现
    public function autoExtractRefMoney(Request $request)
    {
        // 判断账户是否过期
        if (Auth::user()->expire_time < date('Y-m-d')) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：账号已过期，请先购买服务吧']);
        }

        // // 校验可以提现金额是否超过系统设置的阀值
        $ref_amount = ReferralLog::uid()->where('status', 0)->where('order_id','>',0)->where('amount','!=',0)->sum('ref_amount');
        // if ($ref_amount < self::$systemConfig['referral_money'] * 100) {
        //     return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：不满' . self::$systemConfig['referral_money'] . '元，继续努力吧']);
        // }
        //sdo2022-04-28
        if ($ref_amount < 5) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '申请失败：不满5元，继续努力吧']);
        }

        // 取出所有的返利日志，这样可以有
        $link_logs = '';
        $referralLog = ReferralLog::uid()->where('status', 0)->get();
        foreach ($referralLog as $log) {
            $link_logs .= $log->id . ',';
            #这里自动将 返利的那个 已返利变为1 就是已申请
            //referral_log  0未提现 1 审核中 2 已提现 3 代金券 4 微信 5 支付宝 6 usdt
            ReferralLog::query()->where('id', $log->id)->update(['status' => 3]);
            #song 这里直接将提现记录变成1 就是已申请
        }
        $link_logs = rtrim($link_logs, ',');

//referral_apply -2 驳回请更换支付方式 -1 驳回 0 待审核 1 审核通过待打款 2 已打款 3 代金券 4 微信 5 支付宝 6 usdt
        $obj = new ReferralApply();
        $obj->user_id = Auth::user()->id;
        $obj->before = $ref_amount;
        $obj->after = 0;
        $obj->amount = $ref_amount;
        $obj->link_logs = $link_logs;
        $obj->status = 3;
        $obj->save();

        $coupon = new Coupon();
        $coupon->name = Auth::user()->id;
        $coupon->sn = Auth::user()->username.time();
        $coupon->logo = '';
        $coupon->type = 1; // 1是抵扣券
        $coupon->usage = 1; //1次性使用
        $coupon->amount = $ref_amount;
        $coupon->discount = 0;
        $coupon->available_start = time();
        $coupon->available_end = time()+31536000;  // 一年有效期
        $coupon->status = 0;   // 这里保障 是 没有被使用的
        $coupon->creat_user = Auth::user()->id;
        $coupon->save();

        return Response::json(['status' => 'success', 'data' => '', 'message' => '申请成功，记得在邀请返利页面查看生成的代金券呦']);
    }


    // 帮助中心
    public function help(Request $request)
    {
        $view['articleList'] = Article::type(1)->orderBy('sort', 'desc')->orderBy('id', 'desc')->limit(10)->paginate(5);

        return Response::view('user.help', $view);
    }

    // 更换订阅地址
    public function exchangeSubscribe(Request $request)
    {
        DB::beginTransaction();
        try {
            // 更换订阅码
            UserSubscribe::uid()->update(['code' => Helpers::makeSubscribeCode()]);

            // 更换sr 连接密码
            User::uid()->update(['passwd' => makeRandStr()]);

            // 更换 uuid
            $uuid = createGuid();
            User::uid()->update(['vmess_id' => $uuid]);

            DB::commit();

            return Response::json(['status' => 'success', 'data' => '', 'message' => '更换成功']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::info("更换订阅地址异常：" . $e->getMessage());

            return Response::json(['status' => 'fail', 'data' => '', 'message' => '更换失败' . $e->getMessage()]);
        }
    }

    // 转换成管理员的身份
    public function switchToAdmin(Request $request)
    {
        if (!Session::has('admin')) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '非法请求']);
        }

        // 管理员信息重新写入user
        Auth::loginUsingId(Session::get('admin'));
        Session::forget('admin');

        return Response::json(['status' => 'success', 'data' => '', 'message' => "身份切换成功"]);
    }

    // 卡券余额充值
    public function charge(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coupon_sn' => 'required'
        ], [
            'coupon_sn.required' => '券码不能为空'
        ]);

        if ($validator->fails()) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => $validator->getMessageBag()->first()]);
        }

        $coupon = Coupon::type(3)->where('sn', $request->coupon_sn)->where('status', 0)->first();
        if (!$coupon) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '该券已被使用，请勿重复使用']);
        }

        DB::beginTransaction();
        try {
            // 写入日志
            $this->addUserBalanceLog(Auth::user()->id, 0, Auth::user()->balance, Auth::user()->balance + $coupon->amount, $coupon->amount, $coupon->id, '用户手动充值 - [充值券：' . $request->coupon_sn . ']');

            // 更改卡券状态
            $coupon->status = 1;
            $coupon->user_id = Auth::user()->id;
            $coupon->save();

            // 写入卡券日志
            Helpers::addCouponLog($coupon->id, 0, 0, Auth::user()->id, '账户余额充值使用');

            // 余额充值
            User::uid()->increment('balance', $coupon->amount);

            DB::commit();

            return Response::json(['status' => 'success', 'data' => '', 'message' => '充值成功']);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();

            return Response::json(['status' => 'fail', 'data' => '', 'message' => '充值失败']);
        }
    }

    // sdo2022-04-13 同步clonepay记录
    public function clonepay_sync(Request $request)
    {
        //sdo2022-04-13 
        //检测 是否开启这个功能
        if (self::$systemConfig['clonepay'] != 'on') {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '本功能尚未开启']);
        }
        // 开始同步信息
        if (self::$systemConfig['clonepay_syncurl']) {   //是否设置了 同步 url地址
            $sync_url = self::$systemConfig['clonepay_syncurl'] .'&email=' . Auth::user()->username ;
            // 开始 curl get 
            // 初始化
            $curl = curl_init();
            // 设置url路径
            curl_setopt($curl, CURLOPT_URL, $sync_url);
            // 将 curl_exec()获取的信息以文件流的形式返回，而不是直接输出。
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true) ;
            // 在启用 CURLOPT_RETURNTRANSFER 时候将获取数据返回
            // 添加头信息
            // CURLINFO_HEADER_OUT选项可以拿到请求头信息
            curl_setopt($curl, CURLINFO_HEADER_OUT, true);
            // 不验证SSL
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
            // 执行
            $syncpays = curl_exec($curl);
            // 关闭连接
            curl_close($curl);
            // 返回数据
        }
        //
        if ($syncpays) {
            parse_str($syncpays, $msg);
        }else{
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '网络超时，没有获取到数据']);
        }
        // 判断 是否存在 error 
        if (!empty($msg['error'])) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => $msg['error']]);
        }elseif(!empty($msg['success'])){
            $msginfo = '已同步<code>'.Auth::user()->username.'</code>用户在'.$msg['days'] . '天内的 <code>'. $msg['total'].'</code> 个订单。请刷新页面';
            return Response::json(['status' => 'success', 'data' => '', 'message' => $msginfo]);
        }else{
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '返回数据无法识别，请联系管理员']);
        }
    }

    public function nodeMonitor(Request $request)
    {
        $node_id = $request->get('id');

        $node = SsNode::query()->where('id', $node_id)->orderBy('level', 'desc')->first();
        if (!$node) {
            Session::flash('errorMsg', '节点不存在，请重试');

            return Redirect::back();
        }

        // 查看流量
        $dailyData = [];
        $hourlyData = [];

        // 节点一个月内的流量
        $nodeTrafficDaily = SsNodeTrafficDaily::query()->with(['info'])->where('node_id', $node->id)->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))->orderBy('created_at', 'asc')->pluck('total')->toArray();
        $dailyTotal = 30; //  date('d', time()) - 1;//今天不算，减一
        $dailyCount = count($nodeTrafficDaily);
        for ($x = 0; $x < ($dailyTotal - $dailyCount); $x++) {
            $dailyData[$x] = 0;
        }
        for ($x = ($dailyTotal - $dailyCount); $x < $dailyTotal; $x++) {
            $dailyData[$x] = round($nodeTrafficDaily[$x - ($dailyTotal - $dailyCount)] / (1024 * 1024 * 1024), 3);
        }

        // 节点一天内的流量
        $nodeTrafficHourly = SsNodeTrafficHourly::query()->with(['info'])->where('node_id', $node->id)->where('created_at', '>=', date('Y-m-d', time()))->orderBy('created_at', 'asc')->pluck('total')->toArray();
        $hourlyTotal = date('H', time());
        $hourlyCount = count($nodeTrafficHourly);
        for ($x = 0; $x < ($hourlyTotal - $hourlyCount); $x++) {
            $hourlyData[$x] = 0;
        }
        for ($x = ($hourlyTotal - $hourlyCount); $x < $hourlyTotal; $x++) {
            $hourlyData[$x] = round($nodeTrafficHourly[$x - ($hourlyTotal - $hourlyCount)] / (1024 * 1024 * 1024), 3);
        }

        $view['trafficDaily'] = [
            'nodeName'  => $node->name . '#' . $node->id,
            'dailyData' => "'" . implode("','", $dailyData) . "'"
        ];

        $view['trafficHourly'] = [
            'nodeName'   => $node->name . '#' . $node->id,
            'hourlyData' => "'" . implode("','", $hourlyData) . "'"
        ];

        /*   // 本月天数数据
        $monthDays = [];
        $monthHasDays = date("t");
        for ($i = 1; $i <= $monthHasDays; $i++) {
            $monthDays[] = $i;
        } */

        $view['nodeName'] = $node->name . ' #' . $node->id;
        $view['nodeDesc'] = $node->desc;
        //$view['monthDays'] = "'" . implode("','", $monthDays) . "'";

        //展示节点信息
        // 订阅连接
        $view['link'] = (self::$systemConfig['subscribe_domain'] ? self::$systemConfig['subscribe_domain'] : self::$systemConfig['website_url']) . '/s/' . Auth::user()->subscribe->code;
        // 订阅连接二维码
        $view['link_qrcode'] = 'sub://' . base64url_encode($view['link']) . '#' . base64url_encode(self::$systemConfig['website_name']);
        if ($node->type == 1) {
            // 生成ssr scheme
            $obfs_param = Auth::user()->obfs_param ? Auth::user()->obfs_param : $node->obfs_param;
            $protocol_param = $node->single ? Auth::user()->port . ':' . Auth::user()->passwd : Auth::user()->protocol_param;

            $ssr_str = ($node->server ? $node->server : $node->ip) . ':' . ($node->single ? $node->single_port : Auth::user()->port);
            $ssr_str .= ':' . ($node->single ? $node->single_protocol : Auth::user()->protocol) . ':' . ($node->single ? $node->single_method : Auth::user()->method);
            $ssr_str .= ':' . ($node->single ? $node->single_obfs : Auth::user()->obfs) . ':' . ($node->single ? base64url_encode($node->single_passwd) : base64url_encode(Auth::user()->passwd));
            $ssr_str .= '/?obfsparam=' . base64url_encode($obfs_param);
            $ssr_str .= '&protoparam=' . ($node->single ? base64url_encode(Auth::user()->port . ':' . Auth::user()->passwd) : base64url_encode($protocol_param));
            $ssr_str .= '&remarks=' . base64url_encode($node->name);
            $ssr_str .= '&group=' . base64url_encode(empty($group) ? '' : $group->name);
            $ssr_str .= '&udpport=0';
            $ssr_str .= '&uot=0';
            $ssr_str = base64url_encode($ssr_str);
            $ssr_scheme = 'ssr://' . $ssr_str;

            // 生成ss scheme
            $ss_str = Auth::user()->method . ':' . Auth::user()->passwd . '@';
            $ss_str .= ($node->server ? $node->server : $node->ip) . ':' . Auth::user()->port;
            $ss_str = base64url_encode($ss_str) . '#' . 'VPN';
            $ss_scheme = 'ss://' . $ss_str;

            // 生成文本配置信息
            $txt = "节点技术: ss SS" . PHP_EOL;
            $txt .= "服务器：" . ($node->server ? $node->server : $node->ip) . PHP_EOL;
            $txt .= "远程端口：" . ($node->single ? $node->single_port : Auth::user()->port) . PHP_EOL;
            $txt .= "密码：" . ($node->single ? $node->single_passwd : Auth::user()->passwd) . PHP_EOL;
            $txt .= "加密方法：" . ($node->single ? $node->single_method : Auth::user()->method) . PHP_EOL;
            $txt .= "路由：绕过局域网及中国大陆地址" . PHP_EOL . PHP_EOL;
            $txt .= "协议：" . ($node->single ? $node->single_protocol : Auth::user()->protocol) . PHP_EOL;
            $txt .= "协议参数：" . ($node->single ? Auth::user()->port . ':' . Auth::user()->passwd : Auth::user()->protocol_param) . PHP_EOL;
            $txt .= "混淆方式：" . ($node->single ? $node->single_obfs : Auth::user()->obfs) . PHP_EOL;
            $txt .= "混淆参数：" . (Auth::user()->obfs_param ? Auth::user()->obfs_param : $node->obfs_param) . PHP_EOL;
            $txt .= "本地端口：1080" . PHP_EOL;

            $node->txt = $txt;
            $node->ssr_scheme = $ssr_scheme;
            $node->ss_scheme = $node->compatible ? $ss_scheme : ''; // 节点兼容原版才显示

            $allNodes .= $ssr_scheme . '|';
        } elseif ($node->type == 2) {
            // 生成v2ray scheme
            $v2_json = [
                "v"    => "2",
                "ps"   => $node->name,
                "add"  => $node->server ? $node->server : $node->ip,
                "port" => $node->v2_port,
                "id"   => Auth::user()->vmess_id,
                "aid"  => $node->v2_alter_id,
                "net"  => $node->v2_net,
                "type" => $node->v2_type,
                "host" => $node->v2_host,
                "path" => $node->v2_path,
                "tls"  => $node->v2_tls == 1 ? "tls" : ""
            ];
            $v2_scheme = 'vmess://' . base64url_encode(json_encode($v2_json, JSON_PRETTY_PRINT));

            // 生成文本配置信息
            $txt = "节点技术: Vmess "  . PHP_EOL;
            $txt .= "服务器：" . ($node->server ? $node->server : $node->ip) . PHP_EOL;
            $txt .= "端口：" . $node->v2_port . PHP_EOL;
            $txt .= "加密方式：" . $node->v2_method . PHP_EOL;
            $txt .= "用户ID：" . Auth::user()->vmess_id . PHP_EOL;
            $txt .= "额外ID：" . $node->v2_alter_id . PHP_EOL;
            $txt .= "传输协议：" . $node->v2_net . PHP_EOL;
            $txt .= "伪装类型：" . $node->v2_type . PHP_EOL;
            $txt .= $node->v2_host ? "伪装域名：" . $node->v2_host . PHP_EOL : "";
            $txt .= $node->v2_path ? "路径：" . $node->v2_path . PHP_EOL : "";
            $txt .= $node->v2_servicename ? "gRPC serviceName：" . $node->v2_servicename . PHP_EOL : "";
            $txt .= $node->v2_mode ? "gRPC mode：" . $node->v2_mode . PHP_EOL : "";
            $txt .= $node->v2_tls ? "TLS：tls" . PHP_EOL : "";
            $txt .= "allowInsecure：true" . PHP_EOL;
            $txt .= $node->v2_host ? "MAC,tls servername：" . $node->v2_host . PHP_EOL : "";
            $txt .= $node->v2_host ? "IOS,Peer：" . $node->v2_host . PHP_EOL : "";

            $node->txt = $txt;
            $node->v2_scheme = $v2_scheme;
        } elseif ($node->type == 3) {//vless 
            // 生成文本配置信息
            $txt = "节点技术: Vless (请注意区分Vmess Vless)"  . PHP_EOL;
            $txt .= "服务器：" . ($node->server ? $node->server : $node->ip) . PHP_EOL;
            $txt .= "端口：" . $node->v2_port . PHP_EOL;
            $txt .= "用户ID：" . Auth::user()->vmess_id . PHP_EOL;
            $txt .= "传输协议：" . $node->v2_net . PHP_EOL;
            $txt .= "伪装类型：" . $node->v2_type . PHP_EOL;
            $txt .= $node->v2_host ? "伪装域名：" . $node->v2_host . PHP_EOL : "";
            $txt .= $node->v2_path ? "路径：" . $node->v2_path . PHP_EOL : "";
            $txt .= "gRPC serviceName：" . $node->v2_servicename . PHP_EOL;
            $txt .= "gRPC mode：" . $node->v2_mode . PHP_EOL;
            $txt .= "TLS：tls" . PHP_EOL;
            $txt .= "allowInsecure：true" . PHP_EOL;
            $txt .= $node->v2_host ? "MAC,tls servername：" . $node->v2_host . PHP_EOL : "";
            $txt .= $node->v2_host ? "IOS,Peer：" . $node->v2_host . PHP_EOL : "";
            //
            $node->txt = $txt ;
            $scheme = 'vless://'.Auth::user()->vmess_id.'@'.$node->server.':'.$node->v2_port;
            $scheme .= '?encryption='.$node->v2_encryption.'&type='.$node->v2_net.'&headerType='.$node->v2_type.'&host='.urlencode($node->v2_host).'&path='.urlencode($node->v2_path).'&flow='.$node->v2_flow.'&security='.$node->v2_tls.'&sni='.$node->v2_sni.'&serviceName='.$node->v2_servicename. '&mode='.$node->v2_mode.'&alpn='.urlencode($node->v2_alpn);
            $scheme .= '#'.urlencode($node->name.'_'.$node->traffic_rate.'_'.$node->bandwidth.'M') . "\n";
            $node->v2_scheme = $scheme;
        }   elseif ( $node->type == 4) { // trojan
            // 生成文本配置信息
            $txt = "节点技术: Trojan "  . PHP_EOL;
            $txt .= "服务器：" . ($node->server ? $node->server : $node->ip) . PHP_EOL;
            $txt .= "端口：" . $node->v2_port . PHP_EOL;
            $txt .= "用户ID：" . Auth::user()->vmess_id . PHP_EOL;
            $txt .= "传输协议：" . $node->v2_net . PHP_EOL;
            $txt .= "伪装类型：" . $node->v2_type . PHP_EOL;
            $txt .= $node->v2_host ? "伪装域名：" . $node->v2_host . PHP_EOL : "";
            $txt .= $node->v2_path ? "路径：" . $node->v2_path . PHP_EOL : "";
            $txt .= $node->v2_servicename ? "gRPC serviceName：" . $node->v2_servicename . PHP_EOL : "";
            $txt .= $node->v2_mode ? "gRPC mode：" . $node->v2_mode . PHP_EOL : "";
            $txt .= "TLS：tls" . PHP_EOL;
            $txt .= "allowInsecure：true" . PHP_EOL;
            $txt .= $node->v2_host ? "MAC,tls servername：" . $node->v2_host . PHP_EOL : "";
            $txt .= $node->v2_host ? "IOS,Peer：" . $node->v2_host . PHP_EOL : "";
            $node->txt = $txt;
            $scheme = 'trojan://'.Auth::user()->vmess_id.'@'.$node->server.':'.$node->v2_port;
            $scheme .= '?type='.$node->v2_net.'&headerType='.$node->v2_type.'&host='.urlencode($node->v2_host).'&path='.urlencode($node->v2_path).'&flow='.$node->v2_flow.'&security='.$node->v2_tls.'&sni='.$node->v2_sni.'&serviceName='.$node->v2_servicename. '&mode='.$node->v2_mode.'&alpn='.urlencode($node->v2_alpn);
            $scheme .= '#'.urlencode($node->name.'_'.$node->traffic_rate.'_'.$node->bandwidth.'M') . "\n";
            $node->v2_scheme = $scheme;
        }

        // 节点在线状态
        $nodeInfo = SsNodeInfo::query()->where('node_id', $node->id)->where('log_time', '>=', strtotime("-10 minutes"))->orderBy('id', 'desc')->first();
        $node->online_status = empty($nodeInfo) || empty($nodeInfo->load) ? 0 : 1;

        // 节点标签
        $node->labels = SsNodeLabel::query()->with('labelInfo')->where('node_id', $node->id)->get();

        $view['node'] = $node;

        return Response::view('user.nodeMonitor', $view);
    }

    // 设置用户的订阅的状态
    public function reActiveSubscribe(Request $request)
    {

        UserSubscribe::uid()->update(['status' => 1, 'ban_time' => 0, 'ban_desc' => '']);
        // 重置用户的SS链接密码 UUID
        User::uid()->update(['passwd' => makeRandStr()]);
        User::uid()->update(['vmess_id' => createGuid()]);

        return Response::json(['status' => 'success', 'data' => '', 'message' => '解封成功,请使用最新订阅地址']);
    }

    // 矫正用户等级
    public function reLevel(Request $request)
    {

        $goodsIds = Order::query()->where('user_id', Auth::user()->id)->where('status', 2)->where('is_expire', 0)->where('expire_at', '>', date('Y-m-d H:i:s'))->groupBy('goods_id')->pluck('goods_id')->toArray();
        // song 获取 用户商品最大 等级
        $maxLevel = Goods::query()->whereIn('id', $goodsIds)->orderBy('level','desc')->pluck('level')->first();
        empty($maxLevel) && $maxLevel = 0;  // 如果为空 就算 0
        // 将最新的等级写入到用户 中
        User::uid()->update(['level' => $maxLevel]);
        return Response::json(['status' => 'success', 'data' => '', 'message' => '等级校正成功']);
    }

    // 矫正用户等级
    public function reUUID(Request $request)
    {

        $user = User::uid()->first();
        # 5级以上用户才开启
        if (empty($user->vmess_id)) {
            $uuid = createGuid();
            User::uid()->update(['vmess_id' => $uuid]);
        }
        return Response::json(['status' => 'success', 'data' => '', 'message' => '申请成功']);
    }

    // CN + 节点申请
    public function cnUpdate(Request $request)
    {
        $cn_update = trim($request->get('cn_update'));
        $user = User::uid()->first();
        if ($cn_update != $user->username) {
            return Redirect::to('profile#tab_5')->withErrors('邮箱输入错误');
        }elseif ($user->node_group > 1) {
            return Redirect::to('profile#tab_5')->withErrors('您已申请通过');
        }elseif ($user->balance < 0 ) {
            return Redirect::to('profile#tab_5')->withErrors('您的余额 < 0');
        }elseif ($cn_update == $user->username) {

            $orders = Order::where('user_id','=',$user->id)->where('is_expire','=','0')->where('status',2)->where('expire_at','>',date('Y-m-d H:i:s'))->get();
            $level = 0;
            $transfer_enable = 0;
            $transfer_monthly = 0;
            foreach ($orders as $order) {
                // 选取流量
                $order->goods->level > $level && $level = $order->goods->level;
                $transfer_enable += $order->goods->traffic * 1048576;
                if ($order->goods->type == 2) {
                    $transfer_monthly += $order->goods->traffic * 1048576;
                }
            }
            //
            $ret = User::uid()->update(['level' => $level, 'transfer_enable' => $transfer_enable, 'transfer_monthly' => $transfer_monthly, 'node_group' => '2']);
            if (!$ret) {
                return Redirect::to('profile#tab_5')->withErrors('升级失败，请联系管理员');
            } else {
                return Redirect::to('profile#tab_5')->with('successMsg', '升级成功，请在客户端更新节点');
                }
        }else{
            return Redirect::to('profile#tab_5')->withErrors('未知错误，请联系管理员');
        }
    }
}
