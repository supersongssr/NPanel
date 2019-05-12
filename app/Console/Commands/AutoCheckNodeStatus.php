<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Models\SsNode;
use App\Http\Models\SsNodeTrafficDaily;
use App\Http\Models\UserTrafficLog;
use Log;

class AutoCheckNodeStatus extends Command
{
    protected $signature = 'AutoCheckNodeStatus';
    protected $description = '自动检查节点状态status';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $jobStartTime = microtime(true); //开始时间
        //自动判断节点的状态
        $nodes_vnstat = SsNode::query()->get();
        $file = "public/".date("md");
        $node_line = '=====================================';
        $node_error = 'can not connect';
        $nodes_log = @file_put_contents($file, date("m-d H:i"));
        foreach ($nodes_vnstat as $node) {
            # code...
            $server = $node['server'] ? $node['server'] : $node['ip'];
            $status_url = 'http://'.$server.':'.$node['ssh_port'].'/status';
            $vnstat_url = 'http://'.$server.':'.$node['ssh_port'].'/vnstat';
            $status = @file_get_contents($status_url);
            $vnstat = @file_get_contents($vnstat_url);
            //判断一下节点状态 7 = running restart
            if ($status == 7) {
                # code...
                SsNode::query()->where('id',$node['id'])->update(['status'=>1]);
                //将数据写入文件
                $data = $node['name']."#".$server."#".$node['status']."\n".$node_line.$vnstat."\n\n\n";
                $nodes_log = @file_put_contents($file, $data, FILE_APPEND);
            }elseif($status == 4 ) {
                # code...  4 = stop
                SsNode::query()->where('id',$node['id'])->update(['status'=>0]);
                //将数据写入文件
                $data = $node['name']."#".$server."#".$node['status']."\n".$node_line.$vnstat."\n\n\n";
                $nodes_log = @file_put_contents($file, $data, FILE_APPEND);
            }else {
                //同样写入数据，是获取不到运行状态
                $data = $node['name']."#".$server."#".$node['status'].$node_error."\n".$node_line."\n\n\n";
                $nodes_log = @file_put_contents($file, $data, FILE_APPEND);
            }
        }

        $jobEndTime = microtime(true);
        $jobUsedTime = round(($jobEndTime - $jobStartTime), 4);

        Log::info('执行定时任务【' . $this->description . '】');

        
    }

}
