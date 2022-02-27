<?php

namespace App\Console\Commands;

use App\Http\Models\User;
use App\Http\Models\UserBalanceLog;
use Illuminate\Console\Command;
use Log;

class upgradeUserBannoPay extends Command
{
    protected $signature = 'upgradeUserBannoPay';
    protected $description = '封禁疑似滥用邀请账户';

    public function __construct()
    {
        parent::__construct();
    }

//判断标准： 实际上 查询订单即可呢。我觉得这是个办法呢。
    public function handle()
    {
        $orderList = UserBalanceLog::query()->where('amount', '<', '-10000')->get();
        foreach ($orderList as $order) {
            $totalPay = UserBalanceLog::query()->where('user_id','=',$order->user_id)->where('order_id','=','0')->sum('amount');
            if ($totalPay < 3600) {
                # code...
                User::query()->where('id', $order->user_id)->update(['status' => '-1' , 'enable' => '0']);
                Log::info('---封禁用户[ID：' . $order->user_id .  ']的账号，疑似滥用邀请---');
            }
        }
                    Log::info('------------【封禁滥用邀请用户结束：）】---------------');
    }
}
