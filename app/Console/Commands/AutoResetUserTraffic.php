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
        $userList = User::query()->where('status', '>=', 0)->where('expire_time', '>=', date('Y-m-d'))->get();
        if (!$userList->isEmpty()) {
            foreach ($userList as $user) {
                if (!$user->traffic_reset_day) {
                    continue;
                }

                // 取出用户最后购买的有效套餐
                $order = Order::query()
                    ->with(['user', 'goods'])
                    ->whereHas('goods', function ($q) {
                        $q->where('type', 2);
                    })
                    ->where('user_id', $user->id)
                    ->where('is_expire', 0)
                    ->orderBy('oid', 'desc')
                    ->first();

                if (!$order) {
                    continue;
                }

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
                    $traffic = $user->u + $user->d - $traffic * 1024 * 1024;
                    // 如果扣除后发现流量小于0 那么设定为 0 
                    $traffic < 0 && $traffic = 0;
                    User::query()->where('id', $user->id)->update(['u' => 0, 'd' => $traffic]);
                }
            }
        }
    }
}
