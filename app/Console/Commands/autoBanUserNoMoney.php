<?php

namespace App\Console\Commands;

use App\Http\Models\User;
use App\Http\Models\UserBalanceLog;
use Illuminate\Console\Command;
use Log;

class autoBanUserNoMoney extends Command
{
    protected $signature = 'autoBanUserNoMoney';
    protected $description = '自动禁用余额低于0的用户';

    public function __construct()
    {
        parent::__construct();
    }

//获取余额低于0的用户，且没有被禁用的用户，给仅用了 已设定每天早上运行一次
    public function handle()
    {
        $userList = User::query()->where('balance', '<', 0)->where('status','>=',0)->get();
        foreach ($userList as $user) {

            # stauts 会封禁用户登录，同时后端也会封禁用户
            @$times = $user->ban_times + 1;
            if ($times > 7) {
                # code...
                User::query()->where('id', $user->id)->update(['status' => '0','ban_times' => '0']);
            }else{
                User::query()->where('id', $user->id)->update(['status' => '-1','ban_times' => $times]);
            }
            
        }
        Log::info('------------【封禁余额低于0的用户：）】---------------');

        //禁用超过1个月没有使用的用户
        $date_check = date('Y-m-d H:i:s',strtotime('-1 month'));
        $userNoUse = User::query()->where('id', '>', 10)->where('status','>',0)->where('updated_at','<',$date_check)->get();
        foreach ($userNoUse as $user) {
            if ( $user->t +(32*86400) < time() ) {
                User::query()->where('id', $user->id)->update(['status' => '-1']);
            }
        }

        Log::info('------------【封禁超过1个月未使用的用户：）】---------------');
    }
}
