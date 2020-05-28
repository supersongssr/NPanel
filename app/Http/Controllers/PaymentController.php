<?php

namespace App\Http\Controllers;

use App\Components\Helpers;
use App\Components\Yzy;
use App\Components\Trimepay;
use App\Components\AlipaySubmit;
use App\Http\Models\Coupon;
use App\Http\Models\Goods;
use App\Http\Models\Order;
use App\Http\Models\Payment;
use App\Http\Models\PaymentCallback;
use Illuminate\Http\Request;
use Payment\Client\Charge;
use Response;
use Log;
use DB;
use Auth;
use Validator;

/**
 * 支付控制器
 *
 * Class PaymentController
 *
 * @package App\Http\Controllers
 */
class PaymentController extends Controller
{
    protected static $systemConfig;

    function __construct()
    {
        self::$systemConfig = Helpers::systemConfig();
    }

    // 创建支付单
    public function create(Request $request)
    {
        $goods_id = intval($request->get('goods_id'));
        $coupon_sn = $request->get('coupon_sn');
        $pay_type = $request->get('pay_type');

        $goods = Goods::query()->where('status', 1)->where('id', $goods_id)->first();
        if (!$goods) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：商品或服务已下架']);
        }

        // 判断是否开在线支付
        if ( !self::$systemConfig['is_trimepay'] && !self::$systemConfig['is_youzan'] && !self::$systemConfig['is_alipay'] && !self::$systemConfig['is_f2fpay']) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：系统并未开启在线支付功能']);
        }

        // 判断是否存在同个商品的未支付订单
        $existsOrder = Order::uid()->where('status', 0)->where('user_id', Auth::user()->id)->where('goods_id', $goods_id)->exists();
        if ($existsOrder) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：尚有未支付的订单，请先去支付']);
        }

        // 限购控制
        $strategy = self::$systemConfig['goods_purchase_limit_strategy'];
        if ($strategy == 'all' || ($strategy == 'package' && $goods->type == 2) || ($strategy == 'free' && $goods->price == 0) || ($strategy == 'package&free' && ($goods->type == 2 || $goods->price == 0))) {
            $noneExpireOrderExist = Order::uid()->where('status', '>=', 0)->where('is_expire', 0)->where('goods_id', $goods_id)->exists();
            if ($noneExpireOrderExist) {
                return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：商品不可重复购买']);
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
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：订单总价异常']);
        } elseif ($amount == 0) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：订单总价为0，无需使用在线支付']);
        }

        /*// 验证账号是否存在有效期更长的套餐
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
                    return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：您已存在有效期更长的套餐，只能购买流量包']);
                }
            }
        }*/

        DB::beginTransaction();
        try {

            $sn = makeRandStr(12);
            //$orderSn = date('ymdHis') . mt_rand(100000, 999999);
            $orderSn = 'n' .time(). $sn;  // 支付方式+时间号的方式来计算？ 如果同一时间怎么办？

            // 支付方式
            if ($pay_type == 'trimepay' self::$systemConfig['is_trimepay']) {
                $pay_way = 3;
            } elseif (self::$systemConfig['is_youzan']) {
                $pay_way = 2;
            } elseif (self::$systemConfig['is_alipay']) {
                $pay_way = 4;
            } elseif (self::$systemConfig['is_f2fpay']) {
                $pay_way = 5;
            }

            // 生成订单
            $order = new Order();
            $order->order_sn = $orderSn;
            $order->user_id = Auth::user()->id;
            $order->goods_id = $goods_id;
            $order->coupon_id = !empty($coupon) ? $coupon->id : 0;
            $order->origin_amount = $goods->price;
            $order->amount = $amount;
            $order->expire_at = date("Y-m-d H:i:s", strtotime("+" . $goods->days . " days"));
            $order->is_expire = 0;
            $order->pay_way = $pay_way;
            $order->status = 0;
            $order->save();

            // 生成支付单
            if ($pay_type == 'trimepay' && self::$systemConfig['is_trimepay']) {
                $trimepay = new Trimepay(self::$systemConfig['trimepay_appid'], self::$systemConfig['trimepay_appsecret']);
                $notifyUrl = 'https://srp-ssn.freessr.bid' . '/api/trimepay';
                $returnUrl = 'https://srp-ssn.freessr.bid' ;
                $result = $trimepay->pay($orderSn, $amount, $notifyUrl, $returnUrl);
                if ($result['code'] !== 0) {
                    Log::error('【Trimepay】创建二维码失败：' . $result['msg']);
                    throw new \Exception($result['msg']);
                }
            } elseif ($pay_type == 'youzan' && self::$systemConfig['is_youzan']) {
                $yzy = new Yzy();
                $result = $yzy->createQrCode($goods->name, $amount * 100, $orderSn);
                if (isset($result['error_response'])) {
                    Log::error('【有赞云】创建二维码失败：' . $result['error_response']['msg']);

                    throw new \Exception($result['error_response']['msg']);
                }
            } elseif ($pay_type == 'alipay' && self::$systemConfig['is_alipay']) {
                $parameter = [
                    "service"        => "create_forex_trade", // WAP:create_forex_trade_wap ,即时到帐:create_forex_trade
                    "partner"        => self::$systemConfig['alipay_partner'],
                    "notify_url"     => self::$systemConfig['website_url'] . "/api/alipay", // 异步回调接口
                    "return_url"     => self::$systemConfig['website_url'],
                    "out_trade_no"   => $orderSn,  // 订单号
                    "subject"        => "Package", // 订单名称
                    //"total_fee"      => $amount, // 金额
                    "rmb_fee"        => $amount,   // 使用RMB标价，不再使用总金额
                    "body"           => "",        // 商品描述，可为空
                    "currency"       => self::$systemConfig['alipay_currency'], // 结算币种
                    "product_code"   => "NEW_OVERSEAS_SELLER",
                    "_input_charset" => "utf-8"
                ];

                // 建立请求
                $alipaySubmit = new AlipaySubmit(self::$systemConfig['alipay_sign_type'], self::$systemConfig['alipay_partner'], self::$systemConfig['alipay_key'], self::$systemConfig['alipay_private_key']);
                $result = $alipaySubmit->buildRequestForm($parameter, "post", "确认");
            } elseif ($pay_type == 'f2fpay' && self::$systemConfig['is_f2fpay']) {
                // TODO：goods表里增加一个字段用于自定义商品付款时展示的商品名称，
                // TODO：这里增加一个随机商品列表，根据goods的价格随机取值
                $result = Charge::run("ali_qr", [
                    'use_sandbox'     => false,
                    "partner"         => self::$systemConfig['f2fpay_app_id'],
                    'app_id'          => self::$systemConfig['f2fpay_app_id'],
                    'sign_type'       => 'RSA2',
                    'ali_public_key'  => self::$systemConfig['f2fpay_public_key'],
                    'rsa_private_key' => self::$systemConfig['f2fpay_private_key'],
                    'notify_url'      => self::$systemConfig['website_url'] . "/api/f2fpay", // 异步回调接口
                    'return_url'      => self::$systemConfig['website_url'],
                    'return_raw'      => false
                ], [
                    'body'     => '',
                    'subject'  => self::$systemConfig['f2fpay_subject_name'],
                    'order_no' => $orderSn,
                    'amount'   => $amount,
                ]);
            }

            $payment = new Payment();
            $payment->sn = $sn;
            $payment->user_id = Auth::user()->id;
            $payment->oid = $order->oid;
            $payment->order_sn = $orderSn;
            $payment->pay_way = 1;
            $payment->amount = $amount;
            if (self::$systemConfig['is_trimepay']) {
                $payment->qr_url = $result['data'];
                $payment->qr_code = 'https://www.zhihu.com/qrcode?url=' . $result['data'];
                $payment->qr_local_url = 'https://www.zhihu.com/qrcode?url=' . $result['data'];
            } elseif (self::$systemConfig['is_youzan']) {
                $payment->qr_id = $result['response']['qr_id'];
                $payment->qr_url = $result['response']['qr_url'];
                $payment->qr_code = $result['response']['qr_code'];
                $payment->qr_local_url = $this->base64ImageSaver($result['response']['qr_code']);
            } elseif (self::$systemConfig['is_alipay']) {
                $payment->qr_code = $result;
            } elseif (self::$systemConfig['is_f2fpay']) {
                $payment->qr_code = $result;
                $payment->qr_url = 'http://qr.topscan.com/api.php?text=' . $result . '&bg=ffffff&fg=000000&pt=1c73bd&m=10&w=400&el=1&inpt=1eabfc&logo=https://t.alipayobjects.com/tfscom/T1Z5XfXdxmXXXXXXXX.png';
                $payment->qr_local_url = $payment->qr_url;
            }
            $payment->status = 0;
            $payment->save();

            // 优惠券置为已使用
            if (!empty($coupon)) {
                if ($coupon->usage == 1) {
                    $coupon->status = 1;
                    $coupon->save();
                }

                Helpers::addCouponLog($coupon->id, $goods_id, $order->oid, '在线支付使用');
            }

            DB::commit();

            if (self::$systemConfig['is_alipay']) { // Alipay返回支付信息
                return Response::json(['status' => 'success', 'data' => $result, 'message' => '创建订单成功，正在转到付款页面，请稍后']);
            } else {
                return Response::json(['status' => 'success', 'data' => $sn, 'message' => '创建订单成功，正在转到付款页面，请稍后']);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('创建支付订单失败：' . $e->getMessage());

            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建订单失败：' . $e->getMessage()]);
        }
    }

    // 支付单详情
    public function detail(Request $request, $sn)
    {
        $view['payment'] = Payment::uid()->with(['order', 'order.goods'])->where('sn', $sn)->firstOrFail();

        return Response::view('payment.detail', $view);
    }

    // 获取订单支付状态
    public function getStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sn' => 'required|exists:payment,sn'
        ], [
            'sn.required' => '请求失败：缺少sn',
            'sn.exists'   => '支付失败：支付单不存在'
        ]);

        if ($validator->fails()) {
            return Response::json(['status' => 'error', 'data' => '', 'message' => $validator->getMessageBag()->first()]);
        }

        $payment = Payment::uid()->where('sn', $request->sn)->first();
        if ($payment->status > 0) {
            return Response::json(['status' => 'success', 'data' => '', 'message' => '支付成功']);
        } elseif ($payment->status < 0) {
            return Response::json(['status' => 'error', 'data' => '', 'message' => '订单超时未支付，已自动关闭']);
        } else {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '等待支付']);
        }
    }

    // 回调日志
    public function callbackList(Request $request)
    {
        $status = $request->get('status', 0);

        $query = PaymentCallback::query();

        if ($status) {
            $query->where('status', $status);
        }

        $view['list'] = $query->orderBy('id', 'desc')->paginate(10);

        return Response::view('payment.callbackList', $view);
    }
}
