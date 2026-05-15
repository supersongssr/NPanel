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
        $nodes = SsNode::all();
        $data = [];
        $gb = 1024 * 1024 * 1024;

        foreach ($nodes as $node) {
            $data[] = [
                'ip' => $node->ip,
                'ipv6' => $node->ipv6,
                'traffic_used' => (int)($node->traffic_used / $gb),
                'traffic_left' => (int)(($node->traffic_limit - $node->traffic_used) / $gb),
                'node_id' => $node->id,
                'status' => $node->status,
            ];
        }

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
