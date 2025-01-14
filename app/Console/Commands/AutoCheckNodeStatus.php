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
            $server_ip = gethostbyname($server);
            $status_url = 'http://'.$server_ip.':'.$node['ssh_port'].'/status';
            $vnstat_url = 'http://'.$server_ip.':'.$node['ssh_port'].'/vnstat';
            $s1_url = 'http://'.$server_ip.':'.$node['ssh_port'].'/s1';
            $v2_url = 'http://'.$server_ip.':'.$node['ssh_port'].'/v2';
            $status = @file_get_contents($status_url);
            $vnstat = @file_get_contents($vnstat_url);
            $s1 = @file_get_contents($s1_url);
            $v2 = @file_get_contents($v2_url);
            //判断一下节点状态 7 = running restart
            if ($status == 7) {
                # code...
                SsNode::query()->where('id',$node['id'])->update(['status'=>1]);
                //将数据写入文件
                $data = $node['name']."#".$server."#".$server_ip."#".$node['status']."\n".$node_line."\n".$node['desc'].$vnstat."\n\n\n";
                $nodes_log = @file_put_contents($file, $data, FILE_APPEND);
            }elseif($status == 4 ) {
                # code...  4 = stop
                SsNode::query()->where('id',$node['id'])->update(['status'=>0]);
                //将数据写入文件
                $data = $node['name']."#".$server."#".$server_ip."#".$node['status']."\n".$node_line."\n".$node['desc'].$vnstat."\n\n\n";
                $nodes_log = @file_put_contents($file, $data, FILE_APPEND);
            }else {
                # code...  4 = stop
                SsNode::query()->where('id',$node['id'])->update(['status'=>0]);
                //同样写入数据，是获取不到运行状态
                $data = $node['name']."#".$server."#".$server_ip."#".$node['status'].$node_error."\n".$node_line."\n".$node['desc']."\n\n\n";
                $nodes_log = @file_put_contents($file, $data, FILE_APPEND);
            }
            //这里开始 全自动更改 后端节点的配置信息，可以有 这个可以有
            // 通过判断 $node['monitor_url'] 来判断是否是 单独那种节点
            if (!empty($node['monitor_url'])) { //如果不为空那么就代表着可用
                if ($node['type'] == 1 && !empty($s1)) {
                    # code...
                    $addn = explode('#',$s1);
                    $data = [
                        'ip'=>$addn['0'] , 
                        'bandwidth'=>$addn['1'], 
                        'monitor_url'=>$addn['2'], 
                        'method'=>$addn['3']
                    ];
                    SsNode::query()->where('id',$node['id'])->update($data);
                }
                # code...
                if ($node['type'] == 2 && !empty($v2)) {
                    # code...
                    $addn = explode('#',$v2);
                    $data = [
                        'ip'=>$addn['0'], 
                        'v2_port'=>$addn['1'], 
                        'v2_alter_id'=>$addn['2'], 
                        'v2_net'=>$addn['3'], 
                        'v2_type'=>$addn['4'], 
                        'monitor_url'=>$addn['5']
                    ];
                    SsNode::query()->where('id',$node['id'])->update($data);
                }
            }
        }

        $jobEndTime = microtime(true);
        $jobUsedTime = round(($jobEndTime - $jobStartTime), 4);

        Log::info('执行定时任务【' . $this->description . '】');

        
    }

}
