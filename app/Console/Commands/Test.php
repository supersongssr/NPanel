<?php

namespace App\Console\Commands;

use App\Components\Helpers;
use Illuminate\Console\Command;
use App\Http\Models\Order;
use App\Http\Models\User;
use App\Http\Models\Goods;  //Song 
use App\Http\Models\UserLabel;
use App\Http\Models\GoodsLabel;
use Log;
use DB;

class Test extends Command
{
    protected $signature = 'Test';
    protected $description = 'Test测试';
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
        $userDelList = User::query()->where('id', '>', 1)->where('enable', 0)->where('expire_time', '<', date('Y-m-d',strtotime("-7 day")))->get();
        if (!$userDelList->isEmpty()) {
            # code...
            foreach ($userDelList as $user) {
                #song 这里进行一次判断，判断过期时间和余额之间的关系
                $expire_time = time() - strtotime($user->expire_time);
                ## 这里注意一下 balance 是分，就是1￥ = 100分
                #按照100分 = 1个月的关系，如果过期月份 小于 余额 就跳过。就是1分钱对应 0.3天的意思  1小时3600s， 相当于 每分钱 25920s 就是7.2小时。 
                echo floor($expire_time / 25920);
                echo '#';
                echo $user->id;
                echo '#';
                echo $user->balance;
                echo '----------';
                
            }
        }
    }
}