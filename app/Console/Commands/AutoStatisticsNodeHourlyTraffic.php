<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Models\SsNode;
use App\Http\Models\SsNodeTrafficHourly;
use App\Http\Models\UserTrafficLog;
use Log;

class AutoStatisticsNodeHourlyTraffic extends Command
{
    protected $signature = 'autoStatisticsNodeHourlyTraffic';
    protected $description = '自动统计节点每小时流量';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $jobStartTime = microtime(true);

        $nodeList = SsNode::query()->where('status', 1)->orderBy('id', 'asc')->get();
        foreach ($nodeList as $node) {
            //$this->statisticsByNode($node->id);
            # 按照之前的算法来计算。不错的选择。
            #获取节点
            #计算 差值
            #记录每日流量
            #写入新的记录值
            $traffic_hour = $node->traffic - $node->traffic_lasthour;

            $obj = new SsNodeTrafficHourly();
            $obj->node_id = $node->id;
            $obj->u = 0;
            $obj->d = 0;
            $obj->total = $traffic_hour ;
            $obj->traffic = flowAutoShow($traffic_hour);
            $obj->save();

            #记录当前流量值
            $node->traffic_lasthour = $node->traffic;
            $node->save();
        }

        $jobEndTime = microtime(true);
        $jobUsedTime = round(($jobEndTime - $jobStartTime), 4);

        Log::info('执行定时任务【' . $this->description . '】，耗时' . $jobUsedTime . '秒');
    }

    private function statisticsByNode($node_id)
    {
        $start_time = strtotime(date('Y-m-d H:i:s', strtotime("-1 hour")));
        $end_time = time();

        $query = UserTrafficLog::query()->where('node_id', $node_id)->whereBetween('log_time', [$start_time, $end_time]);

        $u = $query->sum('u');
        $d = $query->sum('d');
        $total = $u + $d;
        $traffic = flowAutoShow($total);

        if ($total) { // 有数据才记录
            $obj = new SsNodeTrafficHourly();
            $obj->node_id = $node_id;
            $obj->u = $u;
            $obj->d = $d;
            $obj->total = $total;
            $obj->traffic = $traffic;
            $obj->save();
        }
    }
}
