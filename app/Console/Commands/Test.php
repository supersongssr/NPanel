<?php

namespace App\Console\Commands;

use App\Components\Helpers;
use Illuminate\Console\Command;
use App\Http\Models\Order;
use App\Http\Models\User;
use App\Http\Models\Goods;  //Song
use App\Http\Models\UserLabel;
use App\Http\Models\GoodsLabel;
use App\Http\Models\ReferralLog;
use App\Http\Models\UserBalanceLog;
use App\Http\Models\Coupon;
use App\Http\Models\SsNode;
use App\Http\Models\Cncdn;
use App\Http\Models\UserSubscribe;

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

        $this->aTest();

        $jobEndTime = microtime(true);
        $jobUsedTime = round(($jobEndTime - $jobStartTime), 4);

        Log::info('执行定时任务【' . $this->description . '】，耗时' . $jobUsedTime . '秒');
    }

    // 扣减用户到期商品的流量
    private function aTest()
    {
      //20210321002 把订阅次数重置一下
      $subs = UserSubscribe::query()->get();
      foreach ($subs as $sub) {
        // code...
        $sub->times_lastday = $sub->times;
        $sub->save();
      }
      // 20210312001 替换节点内容
      // $nodeList = SsNode::query()->get();
      // foreach ($nodeList as $key => $node) {
      //   // code...
      //   $node->server=str_ireplace("nback.xyz","2021n.xyz",$node->server);
      //   $node->v2_host=str_ireplace("nback.xyz","2021n.xyz",$node->v2_host);
      //   $node->save();
      // }

    }
}
