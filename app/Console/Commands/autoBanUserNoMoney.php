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
            User::query()->where('id', $user->id)->update(['status' => '0' , 'enable' => '0']);
        }
        Log::info('------------【封禁余额低于0的用户：）】---------------');
    }
}
