<?php

namespace App\Console\Commands;

use App\Components\Helpers;
use Illuminate\Console\Command;
use App\Http\Models\Order;
use App\Http\Models\User;
use App\Http\Models\Goods;  // song 商品找到
use Log;

class AutoResetUserTraffic extends Command
{
    protected $signature = 'autoResetUserTraffic';
    protected $description = '自动重置用户可用流量';
    protected static $systemConfig;

    public function __construct()
    {
        parent::__construct();
        self::$systemConfig = Helpers::systemConfig();
    }

    public function handle()
    {
        $jobStartTime = microtime(true);

        // 重置用户流量
        if (self::$systemConfig['reset_traffic']) {
            $this->resetUserTraffic();
        }

        $jobEndTime = microtime(true);
        $jobUsedTime = round(($jobEndTime - $jobStartTime), 4);

        Log::info('执行定时任务【' . $this->description . '】，耗时' . $jobUsedTime . '秒');
    }

    // 重置用户流量
    private function resetUserTraffic()
    {
        $today = date('d');
        $userList = User::query()->where('status', '>=', 0)->where('traffic_reset_day','=',$today)->where('expire_time', '>=', date('Y-m-d'))->get();
        if (date('m') == 2 && date('d') == 28) {   // 2月 28号就重置，然后 30号 和 31号的用户无法重置 
            $userList = User::query()->where('status', '>=', 0)->where('traffic_reset_day','>=',$today)->where('expire_time', '>=', date('Y-m-d'))->get();
        }
        if (!$userList->isEmpty()) {
            foreach ($userList as $user) {
/**
                // 取出用户最后购买的有效套餐
                $orders = Order::query()
                    ->with(['user', 'goods'])
                    ->whereHas('goods', function ($q) {
                        $q->where('type', 2);
                    })
                    ->where('user_id', $user->id)
                    ->where('is_expire', 0)
                    ->orderBy('oid', 'desc')
                    ->get();

                if (!$orders) {
                    continue;
                }

                foreach ($orders as $order) {
                    # code...
                    $month = abs(date('m'));
                    $today = abs(date('d'));
                    if ($order->user->traffic_reset_day == $today) {
                        // 跳过本月，防止异常重置
                        if ($month == date('m', strtotime($order->expire_at))) {
                            continue;
                        } elseif ($month == date('m', strtotime($order->created_at))) {
                            continue;
                        }

                        // 这里从用户已用流量中扣除相应的流量。
                        $goods = Goods::query()->where('id',$order->goods_id)->first();
                        // 这里从已使用流量中 扣除掉 套餐赠送的流量
                        $traffic = $user->u + $user->d - $goods->traffic * 1024 * 1024;
                        // 如果扣除后发现流量小于0 那么设定为 0 
                        $traffic < 0 && $traffic = 0;
                        User::query()->where('id', $user->id)->update(['u' => 0, 'd' => $traffic]);
                    }
                }
**/
/**
                // 获取该用户的所有 存在的套餐订单
                $orders = Order::query()->where('user_id', $user->id)->where('is_expire', 0)->get();
                if (!$orders) {
                    continue;
                }
                // 每个套餐都按照这个流量重置日计算么？非也，按照购买时间，来计算，每30天重置一次流量。可以有。这个可以有。
                foreach ($orders as $order) {
                    # code...
                }
    // song 备注 还是按照用户的 购买时间来写比较好，然后，每30天重置一次套餐流量。这个可以有。
**/
                // 套餐重置日，会把套餐流量重置一下 ，可以有
                if ($user->d > $user->transfer_monthly ) {
                    $user->u += $user->d - $user->transfer_monthly;
                }
                $user->d = 0;
                $user->save();
            }
        }
    }
}
