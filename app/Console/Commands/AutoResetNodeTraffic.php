<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Models\SsNode;
use Illuminate\Support\Facades\Log;

class AutoResetNodeTraffic extends Command
{
    protected $signature = 'autoResetNodeTraffic';
    protected $description = '自动重置节点流量（按 reset_day）';

    public function handle()
    {
        $jobStartTime = microtime(true);

        $today = (int)date('d');
        $daysInMonth = (int)date('t');

        $resetNodes = SsNode::whereRaw('LEAST(reset_day, ?) = ?', [$daysInMonth, $today])
            ->get();

        $resetCount = 0;
        foreach ($resetNodes as $node) {
            $node->traffic_used = 0;
            $node->save();
            $resetCount++;
        }

        $jobEndTime = microtime(true);
        $jobUsedTime = round(($jobEndTime - $jobStartTime), 4);

        Log::info('执行定时任务【' . $this->description . '】，重置 ' . $resetCount . ' 个节点，耗时' . $jobUsedTime . '秒');
    }
}
