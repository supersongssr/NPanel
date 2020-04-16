<?php

namespace App\Console\Commands;

use App\Components\Helpers;
use Illuminate\Console\Command;
use App\Http\Models\Order;
use App\Http\Models\User;
use App\Http\Models\Goods;  // 引入这个Goods song
use App\Http\Models\UserLabel;
use App\Http\Models\GoodsLabel;
use Log;
use DB;

class AutoDecGoodsTraffic extends Command
{
    protected $signature = 'autoDecGoodsTraffic';
    protected $description = '自动扣减用户到期商品的流量 更新等级 标签';
    protected static $systemConfig;

    public function __construct()
    {
        parent::__construct();
        self::$systemConfig = Helpers::systemConfig();
    }

    public function handle()
    {
        $jobStartTime = microtime(true);

        // 扣减用户到期商品的流量
        $this->decGoodsTraffic();

        $jobEndTime = microtime(true);
        $jobUsedTime = round(($jobEndTime - $jobStartTime), 4);

        Log::info('执行定时任务【' . $this->description . '】，耗时' . $jobUsedTime . '秒');
    }

    // 扣减用户到期商品的流量
    private function decGoodsTraffic()
    {
        $orderList = Order::query()->where('status', 2)->where('is_expire', 0)->where('expire_at', '<', date('Y-m-d H:i:s'))->get();
        if (!$orderList->isEmpty()) {
            /**
            // 用户默认标签
            $defaultLabels = [];
            if (self::$systemConfig['initial_labels_for_user']) {
                $defaultLabels = explode(',', self::$systemConfig['initial_labels_for_user']);
            }
            **/

            DB::beginTransaction();
            try {
                foreach ($orderList as $order) {

                    // 先过期本订单
                    Order::query()->where('oid', $order->oid)->update(['is_expire' => 1]);
                    
                    if (empty($order->user_id) || empty($order->goods_id)) {
                        continue;
                    }

                    $user = User::query()->where('id',$order->user_id)->first();
                    $goods = Goods::query()->where('id',$order->goods_id)->first();

                    if (empty($user)) {
                        continue;
                    }

                    // -- 处理用户 等级 - 和 流量重置日 - 
                    // 检查该订单对应用户是否还有套餐（非流量包）存在
                    $haveOrders = Order::query()
                        ->where('is_expire', 0)
                        ->where('user_id', $order->user_id)
                        ->orderBy('oid', 'desc')
                        ->get();
                    $user_level = 0;
                    $user_reset_day = 0;
                    if (!$haveOrders->isEmpty()) {
                        foreach ($haveOrders as $haveOrder) {
                            // 找到商品
                            $haveGoods = Goods::query()->where('id',$haveOrder->goods_id)->first();
                            // 如果商品 type = 2 说明时重置周期的那种 
                            if ($haveGoods->type == 2) {
                                $user_reset_day += 1;
                            }
                            // 如果商品等级 大于当前 就重置一下等级
                            if ($haveGoods->level >  $user_level) {
                                $user_level = $haveGoods->level;
                            }
                        }
                    }
                    // 如果存在有效的套餐，就不重置流量重置日 
                    if ($user_reset_day > 0 ) {
                        User::query()->where('id', $order->user_id)->update(['level' => $user_level]);
                    }else{
                        User::query()->where('id', $order->user_id)->update(['traffic_reset_day' => 0, 'level' => $user_level]);
                    }

                    // ----------- 扣除 用户总流量 
                    $user->transfer_enable -= $goods->traffic * 1048576;
                    // 扣除之后 < 0 的话，就 = 0 
                    $user->transfer_enable < 0 && $user->transfer_enable = 0;
                    User::query()->where('id', $order->user_id)->update(['transfer_enable' => $user->transfer_enable]);
                    // --------处理用户使用流量 
                    if ($goods->type == 2) {  // 每月流量套餐 
                        // ----- 处理 monthly 每月重置的流量
                        $user->transfer_monthly -= $goods->traffic * 1048576;
                        $user->transfer_monthly < 0 && $user->transfer_monthly = 0 ;
                        // ----- 处理已用流量
                        $user->d -= $goods->traffic * 1048576;
                        $user->d < 0 && $user->d = 0;
                        User::query()->where('id', $order->user_id)->update(['d' => $user->d, 'transfer_monthly' => $user->transfer_monthly]);
                    }elseif ($goods->type == 1) {  // 流量包
                        //只处理已用流量
                        $user->u -= $goods->traffic * 1048576;
                        if ( $user->u < 0 ) {
                            $user->d += $user->u;
                            $user->u = 0;
                            $user->d < 0 && $user->d = 0;
                        }
                        User::query()->where('id', $order->user_id)->update(['u' => $user->u,'d' => $user->d]);
                    }
                    Helpers::addUserTrafficModifyLog($order->user_id, $order->oid, $user->transfer_enable, 0, '[定时任务]用户所购商品到期，扣减商品对应的流量');
                    Helpers::addUserTrafficModifyLog($order->user_id, $order->oid, $user->transfer_enable, ($user->transfer_enable - $goods->traffic * 1048576), '[定时任务]用户所购商品到期，扣减商品对应的流量');

/**
                    // 删除该商品对应用户的所有标签
                    UserLabel::query()->where('user_id', $order->user->id)->delete();

                    // 取出用户的其他商品带有的标签
                    $goodsIds = Order::query()->where('user_id', $order->user->id)->where('oid', '<>', $order->oid)->where('status', 2)->where('is_expire', 0)->groupBy('goods_id')->pluck('goods_id')->toArray();
                    $goodsLabels = GoodsLabel::query()->whereIn('goods_id', $goodsIds)->groupBy('label_id')->pluck('label_id')->toArray();


                    // 生成标签 写入用户最新标签
                    $labels = array_values(array_unique(array_merge($goodsLabels, $defaultLabels))); // 标签去重
                    foreach ($labels as $vo) {
                        $userLabel = new UserLabel();
                        $userLabel->user_id = $order->user->id;
                        $userLabel->label_id = $vo;
                        $userLabel->save();
                    }
**/
                }

                DB::commit();
            } catch (\Exception $e) {
                \Log::error($this->description . '：' . $e);

                DB::rollBack();
            }
        }
    }
}
