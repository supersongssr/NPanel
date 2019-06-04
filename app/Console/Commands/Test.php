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
        // 取出用户的其他商品带有的标签
        $goodsIds = Order::query()->where('user_id', '21260')->where('status', 2)->groupBy('goods_id')->pluck('goods_id')->toArray();
        $goodsLevel = Goods::query()->whereIn('id', $goodsIds)->orderBy('sort','desc')->pluck('sort')->first();  //获取 sort排序，提取sort 获取最大的第一个
        empty($goodsLevel) && $goodsLevel = 0;
        $goodsLabels = GoodsLabel::query()->whereIn('goods_id', $goodsIds)->groupBy('label_id')->pluck('label_id')->toArray();
        var_dump($goodsIds);
        echo '------------';
        var_dump($goodsLabels);
        echo '------------';
        var_dump($goodsLevel);
        echo '------------';
        echo $goodsLevel;
    }
}
