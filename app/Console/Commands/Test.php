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

        $this->decGoodsTraffic();

        $jobEndTime = microtime(true);
        $jobUsedTime = round(($jobEndTime - $jobStartTime), 4);

        Log::info('执行定时任务【' . $this->description . '】，耗时' . $jobUsedTime . '秒');
    }

    // 扣减用户到期商品的流量
    private function decGoodsTraffic()
    {
        /**
        $goods = Goods::all();
        foreach ($goods as $good) {
            $good->level = $good->sort;
            $good->save();
        }
        **/
/**
        $nodeList = SsNode::query()->orderBy('id', 'asc')->get(); 
        foreach ($nodeList as $node) {
            $group = $node->id % 2 + 1;
            SsNode::query()->where('id',$node->id)->update(['group'=>$group]);
        }
        
**/
        $users = User::query()->get();
        foreach ($users as $user) {
            $group = $user->id % 2 +1 ;
            User::query()->where('id', $user->id)->update(['group' => $group]);
            echo $user->id.'|';
        }
        

/**
        $date_check = date('Y-m-d H:i:s',strtotime('-1 month'));
        $userNoUse = User::query()->where('id', '>', 10)->where('status','>',0)->where('updated_at','<',$date_check)->get();
        foreach ($userNoUse as $user) {
            if ( $user->t +(32*86400) < time() ) {
                User::query()->where('id', $user->id)->update(['status' => '-1']);
            }
        }
        **/
/**
        // 把所有用户的充值记录，就是已用的用户的ID，记录到卡券那里！很重要！
        $balance_logs = UserBalanceLog::->where('amount','>',0)->orderBy('id', 'desc')->get();
        foreach ($balance_logs as $balance_log) {
            # code...

        }
        **/
        /**
        $balance_logs = UserBalanceLog::query()->where('amount','>',0)->get();
        foreach ($balance_logs as $balance_log) {
            # code...
            $coupon_key = explode('：',$balance_log->desc);
            if (!empty($coupon_key['1'])) {
                # code...
                $coupon_key['1'] = str_replace("]","",$coupon_key['1']);
                // 更新coupon 使用者
                $coupon = Coupon::type(3)->where('sn', $coupon_key['1'])->first();
                $coupon->user_id = $balance_log->user_id;
                $coupon->save();
                // 更新userbalance log 使用者的coupinID
                $balance_log->coupon_id = $coupon->id;
                $balance_log->save();
                //
                echo $coupon_key['1'].'-';
                echo $balance_log->user_id.'-';
                echo $coupon->id.'-';
            }
        }
        **/
/**
        // 把所有edu.cn结尾的账号都给设定为 status=0需要激活一下的那种！
        $userList = User::query()->where('status', 1)->get();
        foreach ($userList as $user) {
            # code...
            if (strrchr($user->username, 'edu.cn') == 'edu.cn') {
                # code...
                echo $user->username ;
                echo ' | ';
                User::query()->where('id', $user->id)->update(['status' => 0]);
            }
        }
**/
        /**
        //所有节点前面加上： 撸白嫖
        $nodeList = SsNode::query()->where('id','>',9)->orderBy('id', 'asc')->get(); 
        foreach ($nodeList as $node) {
            # code...
            $node->name = '撸啊撸' . $node->name;
            SsNode::query()->where('id',$node->id)->update(['name'=>$node->name]);
        }
        **/

/**
        // 批量替换所有节点里面的名字
        $nodeList = SsNode::query()->where('id','>',9)->orderBy('id', 'asc')->get(); 
        foreach ($nodeList as $node) {
            # code...
            #$node->name = str_replace("-","",$node->name);
            SsNode::query()->where('id',$node->id)->update(['bandwidth'=>1000]);
        }
**/
        /**
        $userList = User::query()->where('id', '>', 1)->where('d',0)->where('t',0)->where('balance',0)->where('last_login',0)->where('status', 1)->get();
        foreach ($userList as $user) {
            # code...
            User::query()->where('id', $user->id)->update(['status' => 0]);

        }

        **/


        /**
        // 批量替换所有节点里面的域名
        $nodeList = SsNode::query()->where('id','>',9)->orderBy('id', 'asc')->get(); 
        foreach ($nodeList as $node) {
            # code...
            $node->server = str_replace("ssvss.tk","ssyes.xyz",$node->server);
            SsNode::query()->where('id',$node->id)->update(['server'=>$node->server]);
        }
        **/

        /**
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
        **/



/**
        # 先获取所有的 删除记录，然后，再取查找 那个返利记录，如果返利记录有就返回1 
        $referrals = ReferralLog::query()->where('order_id','=',-1)->where('status','=',2)->get();
        foreach ($referrals as $ref) {
            # code...
            @User::query()->where('id', $ref->ref_user_id)->increment('balance', $ref->ref_amount*100);
            echo $ref->id;
            echo ' ';
        }
        **/
    }
}