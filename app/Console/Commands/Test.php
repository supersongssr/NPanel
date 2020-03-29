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

        $users = User::query()->where('node_group',2)->get();
        foreach ($users as $user) {
            $orders = Order::where('user_id','=',$user->id)->where('is_expire','=','0')->where('status',2)->where('expire_at','>',date('Y-m-d H:i:s'))->get();
            $level = 0;
            $transfer_enable = 0;
            $transfer_monthly = 0;
            foreach ($orders as $order) {
                // 选取流量 
                $order->goods->level > $level && $level = $order->goods->level;
                $transfer_enable += $order->goods->traffic * 1048576;
                if ($order->goods->type == 2) {
                    $transfer_monthly += $order->goods->traffic * 1048576;
                }
            }
            echo $user->id .' ';
            User::query()->where('id',$user->id)->update(['level' => $level, 'transfer_enable' => $transfer_enable, 'transfer_monthly' => $transfer_monthly]);
        }
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
/**
        $orderList = Order::query()->where('is_expire', 0)->where('expire_at', '<', '2015-12-31 08:00:00')->get();
        if (!$orderList->isEmpty()) {
            foreach ($orderList as $order) {
                $a_time = strtotime($order->created_at) + $order->goods->days * 86400;
                $order->expire_at = date("Y-m-d H:i:s", $a_time);
                $order->save();
                echo $order->expire_at.' ';
            }
        }
        **/
        
/**
        // 清空 余额为负数， 邀请人 !=0 的用户的 返利，并disable这个用户
        //删除过期x月，且余额低于x元的用户。
        $userNoMoneyDels = User::query()->where('balance', '<', 0)->where('referral_uid', '!=', 0)->get();
        if (!$userNoMoneyDels->isEmpty()) {
            # code...
            foreach ($userNoMoneyDels as $user) {
                //song 这里查看一下此用户是否有邀请人，然后扣除邀请人的相关的余额。
                //如果邀请人ID 不是0 就是说存在邀请人 ； 同时该用户不是 bad user
                if ( $user->referral_uid != 0 ) {
                    # 取出此用户注册邀请奖励值
                    $referral = ReferralLog::where('user_id','=',$user->id)->where('ref_user_id','=',$user->referral_uid)->where('order_id','=',0)->first();
                    $pays     = ReferralLog::where('user_id','=',$user->id)->where('ref_user_id','=',$user->referral_uid)->where('order_id','=',-1)->where('status','=',2)->count();
                    ##如果存在这个邀请ID 那么就扣除这个用户相应的邀请ID，并写入返利日志 直接扣除，直接写入
                    if (!empty($referral->ref_amount) && $pays < 1) {
                        #扣除邀请人相应的余额
                        User::query()->where('id', $user->referral_uid)->decrement('balance', $referral->ref_amount*100);
                        //扣除流量
                        $transfer_enable = self::$systemConfig['referral_traffic'] * 1048576;
                        User::query()->where('id', $user->referral_uid)->decrement('transfer_enable', $transfer_enable);

                        #写入用户余额变动日志
                        //$this->addUserBalanceLog($user->referral_uid, 0, $user->balance, $user->balance - $referral->ref_amount, -$referral->ref_amount, '邀请用户被删除扣除余额');
                        ## 写入用户邀请返利日志
                        $referrallog = new ReferralLog();
                        # 用户ID 就是被删除用户ID
                        $referrallog->user_id = $user->id;
                        # 这个用户谁邀请的
                        $referrallog->ref_user_id = $user->referral_uid;
                        #订单ID 自然是0 
                        $referrallog->order_id = -1;
                        $referrallog->amount = 0;
                        #这里是负值，就是已经扣除了相关的余额
                        $referrallog->ref_amount = -$referral->ref_amount;
                        #这里设定为2 就是已打款的意思。就是说这个款已经自动扣除了
                        $referrallog->status = 2;
                        $referrallog->save();
                    }
                }
                User::query()->where('id',$user->id)->update(['referral_uid' => 0, 'status' => -1]);
                echo $user->id . ' ';
            }
        }
        **/
/**
        $users = User::query()->where('node_group',1)->where('traffic_reset_day','!=',0)->get();
        foreach ($users as $user) {
            $orders = Order::where('user_id','=',$user->id)->where('is_expire','=','0')->get();
            $transfer_monthly = 0;
            foreach ($orders as $order) {
                // 选取流量 
                if ($order->goods->type == 2) {
                    $transfer_monthly += $order->goods->traffic * 1048576;
                }
                //echo $transfer_monthly;
            }
            
            $ret = User::where('id',$user->id)->update(['transfer_monthly' => $transfer_monthly]);
            if (!$ret) {
                echo '--W>'.$user->id;
            }else{
                echo '--R>'.$user->id;
            }
        }
   **/     

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