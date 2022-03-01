<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Models\SsNode;
use App\Http\Models\SsNodeTrafficDaily;
use App\Http\Models\SsNodeTrafficHourly;
use App\Http\Models\UserTrafficLog;
use App\Http\Models\Config;

use Log;

class AutoStatisticsNodeDailyTraffic extends Command
{
    protected $signature = 'autoStatisticsNodeDailyTraffic';
    protected $description = '自动统计节点每日流量';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $jobStartTime = microtime(true);

        /*
        增加 统计每天流量的消耗量，和每组节点的流量消耗量
        */
        // function getConfig( $name ){
        //     $config = Config::query()->where('name',$name)->first();
        //     return $config->value;
        // }
        // function setConfig( $name, $value ){
        //     return Config::query()->where('name', $name )->update(['value' => $value]); 
        // }
        // 每日消耗量
        $all_traffic = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group','>',0)->sum('traffic');
        $all_traffic_lastday = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group','>',0)->sum('traffic_lastday');
        $all_traffic_daily_mark = Config::query()->where('name','all_traffic_daily_mark')->first();
        $all_traffic_today = $all_traffic - $all_traffic_lastday;
        $all_traffic_daily_mark = floor($all_traffic_today / 1073741824) .'G_'.$all_traffic_daily_mark->value;
        $all_traffic_daily_mark = substr($all_traffic_daily_mark, 0,1000);   //只保留1000位，防止过长！
        Config::query()->where('name', 'all_traffic_daily_mark')->update(['value' => $all_traffic_daily_mark]);    //记录进数据库
        // 每日供给量
        $all_traffic_supply = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('status',1)->sum('node_onload');
        $all_traffic_daily_supply = Config::query()->where('name','all_traffic_daily_supply')->first();
        $all_traffic_daily_supply = $all_traffic_supply .'G_'. $all_traffic_daily_supply->value;
        $all_traffic_daily_supply = substr($all_traffic_daily_supply,0,1000);
        Config::query()->where('name', 'all_traffic_daily_supply')->update(['value' => $all_traffic_daily_supply]);    //记录进数据库
        //
        // Group 1 每日消耗量
        $group1_traffic = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group',1)->sum('traffic');
        $group1_traffic_lastday = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group',1)->sum('traffic_lastday');
        $group1_traffic_daily_mark = Config::query()->where('name','group1_traffic_daily_mark')->first();
        $group1_traffic_today = $group1_traffic - $group1_traffic_lastday;
        $group1_traffic_daily_mark = floor($group1_traffic_today / 1073741824) .'G_'.$group1_traffic_daily_mark->value;
        $group1_traffic_daily_mark = substr($group1_traffic_daily_mark, 0,1000);   //只保留1000位，防止过长！
        Config::query()->where('name', 'group1_traffic_daily_mark')->update(['value' => $group1_traffic_daily_mark]);    //记录进数据库
        // Group 1 每日供给量
        $group1_traffic_supply = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group',1)->where('status',1)->sum('node_onload');
        $group1_traffic_daily_supply = Config::query()->where('name','group1_traffic_daily_supply')->first();
        $group1_traffic_daily_supply = $group1_traffic_supply .'G_'. $group1_traffic_daily_supply->value;
        $group1_traffic_daily_supply = substr($group1_traffic_daily_supply,0,1000);
        Config::query()->where('name', 'group1_traffic_daily_supply')->update(['value' => $group1_traffic_daily_supply]);    //记录进数据库

        // Group 2 每日消耗量
        $group2_traffic = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group',2)->sum('traffic');
        $group2_traffic_lastday = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group',2)->sum('traffic_lastday');
        $group2_traffic_daily_mark = Config::query()->where('name','group2_traffic_daily_mark')->first();
        $group2_traffic_today = $group2_traffic - $group2_traffic_lastday;
        $group2_traffic_daily_mark = floor($group2_traffic_today / 1073741824) .'G_'.$group2_traffic_daily_mark->value;
        $group2_traffic_daily_mark = substr($group2_traffic_daily_mark, 0,1000);   //只保留1000位，防止过长！
        Config::query()->where('name', 'group2_traffic_daily_mark')->update(['value' => $group2_traffic_daily_mark]);    //记录进数据库
        // Group 2 每日供给量
        $group2_traffic_supply = SsNode::query()->where('id','>',9)->where('node_cost','>',1)->where('node_group',2)->where('status',1)->sum('node_onload');
        $group2_traffic_daily_supply = Config::query()->where('name','group2_traffic_daily_supply')->first();
        $group2_traffic_daily_supply = $group2_traffic_supply .'G_'. $group2_traffic_daily_supply->value;
        $group2_traffic_daily_supply = substr($group2_traffic_daily_supply,0,1000);
        Config::query()->where('name', 'group2_traffic_daily_supply')->update(['value' => $group2_traffic_daily_supply]);    //记录进数据库
        // Config::query()->where('name', $name)->update(['value' => $value]);

        /*   计算每个节点的每日自动化 */
        $nodeList = SsNode::query()->where('id','>',9)->where('node_group','>',0)->get();  //获取所有节点
        // 2022-02-14 修复group0的 bug
        // 1-9 和 node_group=0的节点，是广告节点。
        foreach ($nodeList as $node) {
            if ( $node->traffic == $node->traffic_lastday ) {  // 判断节点 今日流量 = 昨日流量，说明没走流量，记录仪下，然后报告bug
                if ($node->status != 0) {
                    $node->status = 0 ;
                    $node->save();
                }
                continue ;
            } 
            // 判断节点过去2小时 是否存在心跳
            if ( strtotime($node->heartbeat_at) < (time() - 7200)) {
                if ($node->status != 0 || $node->traffic_lastday != $node->traffic) {
                    $node->status = 0;
                    $node->traffic_lastday = $node->traffic;
                    $node->save();
                }
                continue;
            } 
            //每日流量
            $traffic_today = $node->traffic - $node->traffic_lastday;
            // 记录当前流量值
            $node->traffic_lastday = $node->traffic;
            // 写入每天流量差值记录
            $node->monitor_url = floor($traffic_today / 1073741824) . '_' . $node->monitor_url;
            $node->monitor_url = substr($node->monitor_url, 0, 32);
            # 流量统计和节点故障预警 ，排除一种情况，流量少，但是实际上是 流量已用超那种
            if ($node->is_subscribe == 1) {
                $traffic_today < 1*1024*1024*1024 && $node->sort -= 10;
                $traffic_today < 8*1024*1024*1024 && $node->sort -= 3;
                $traffic_today < 16*1024*1024*1024 && $node->sort -= 1;
                $traffic_today > 32*1024*1024*1024 && $node->sort += 1;
                $traffic_today > 64*1024*1024*1024 && $node->sort += 4;
            }
            // 如果今日流量 < 0 说明重置了。就把正数的sort变为0
            $traffic_today < 0 && $node->sort > 0 && $node->sort = 0;
            $node->save();
            //
            // 记录流量记录
            $obj = new SsNodeTrafficDaily();
            $obj->node_id = $node->id;
            $obj->u = 0;
            $obj->d = 0;
            $obj->total = $traffic_today ;
            $obj->traffic = flowAutoShow($traffic_today);
            $obj->save();
        }

        $jobEndTime = microtime(true);
        $jobUsedTime = round(($jobEndTime - $jobStartTime), 4);

        Log::info('执行定时任务【' . $this->description . '】，耗时' . $jobUsedTime . '秒');
/*
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
                        'ssh_port'=>$addn['1'],
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
        Log::info('执行定时任务【检查节点status状态】完成，结果已写入文件');
        */
    }

    // private function statisticsByNode($node_id)
    // {
        /*$start_time = strtotime(date('Y-m-d 00:00:00', strtotime("-1 day")));
        $end_time = strtotime(date('Y-m-d 23:59:59', strtotime("-1 day")));

        $query = UserTrafficLog::query()->where('node_id', $node_id)->whereBetween('log_time', [$start_time, $end_time]);
        //$query = SsNodeTrafficHourly::query()->where('node_id', $node_id)->whereBetween('log_time', [$start_time, $end_time]);

        $u = $query->sum('u');
        $d = $query->sum('d');
        $total = $u + $d;
        //获取节点信息
        $node = SsNode::query()->where('id', $node_id)->first();
        //如果倍率为0的话，无法做除数，改为最低倍率0.1
        //$node->traffic_rate < 0.1 && $node->traffic_rate = 0.1;
        //empty($node->monitor_url) && $total = $total / $node->traffic_rate;
        $traffic = flowAutoShow($total);

        //写入每日流量数据 有记录才会写 显得好看些
        if ( $total ) {
            # code...
            //写入每日流量数据
            $obj = new SsNodeTrafficDaily();
            $obj->node_id = $node_id;
            $obj->u = $u;
            $obj->d = $d;
            $obj->total = $total;
            $obj->traffic = $traffic;
            $obj->save();
        }

        // 在线节点少于 10G流量的隐藏 且节点名称加 -
        // 这个主要是用来证明节点是否可以正常使用的！
        if ($total < 10737418240) {
            $node->status = 0;
            $node->sort += 1;
        }
        //节点描述里，加上每日节点流量表现数值
        $node->desc = floor($total / 1073741824) . ' ' . $node->desc;
        $node->desc = substr($node->desc, 0, 32);

        $node->save();
        //SsNode::query()->where('id',$node_id)->update(['status'=>$node->status, 'ipv6'=>$node->ipv6 , 'desc'=>$node->desc ,'sort'=>$node->sort ]);
*/

    // }
}
