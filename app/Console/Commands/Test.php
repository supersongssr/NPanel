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

        // echo '禁用几乎所有节点的订阅信息';
        // $nodes = SsNode::query()->where('id','>',9)->get();
        // foreach ($nodes as $node) {
        //     // $node->is_subscribe = 0;
        //     // echo $node->id . ' --- ';
        //     $node->name = str_replace('🇺🇳','',$node->name);
        //     $node->name = $node->isotoemoji($node->country_code) . $node->name;
        //     $node->save();
        // }

        // $nodes = SsNode::query()->get();
        // foreach($nodes as $node){
        //     $node->sort = 0;
        //     $node->save();
        // }
      // $all_traffic_today = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group','>',0)->where('status',1)->sum('traffic') - SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group','>',0)->where('status',1)->sum('traffic_lastday');
      // echo '总用' . floor($all_traffic_today / 1073741824) ."G \n";
      // // 每日供给量
      // $all_traffic_supply = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('status',1)->sum('node_onload');
      // echo '总供' . $all_traffic_supply."G \n";
      // //
      // // Group 1 每日消耗量
      // $group1_traffic_today = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group',1)->where('status',1)->sum('traffic') - SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group',1)->where('status',1)->sum('traffic_lastday');
      // echo '1用' . floor($group1_traffic_today / 1073741824) ."G \n";
      // // Group 1 每日供给量
      // $group1_traffic_supply = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group',1)->where('status',1)->sum('node_onload');
      // echo '1供' . $group1_traffic_supply ."G \n";

      // // Group 2 每日消耗量
      // $group2_traffic_today = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group',2)->where('status',1)->sum('traffic') - SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group',2)->where('status',1)->sum('traffic_lastday');
      // echo '2用' . floor($group2_traffic_today / 1073741824) ."G \n";
      
      // // Group 2 每日供给量
      // $group2_traffic_supply = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group',2)->where('status',1)->sum('node_onload');
      // echo '2供' . $group2_traffic_supply ."G \n";
      
      // $subs = UserSubscribe::query()->get();
      // foreach ($subs as $sub) {
      //   // code...
      //   $sub->times_lastday = $sub->times;
      //   $sub->save();
      // }
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
