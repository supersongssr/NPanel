<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\AutoJob::class,
        \App\Console\Commands\AutoClearLog::class,
        \App\Console\Commands\AutoDecGoodsTraffic::class,
        \App\Console\Commands\AutoResetUserTraffic::class,
        \App\Console\Commands\AutoCheckNodeTCP::class,
        \App\Console\Commands\AutoStatisticsNodeDailyTraffic::class,
        \App\Console\Commands\AutoStatisticsNodeHourlyTraffic::class,
        \App\Console\Commands\AutoStatisticsUserDailyTraffic::class,
        \App\Console\Commands\AutoStatisticsUserHourlyTraffic::class,
        \App\Console\Commands\UserTrafficAbnormalAutoWarning::class,
        \App\Console\Commands\UserExpireAutoWarning::class,
        \App\Console\Commands\UserTrafficAutoWarning::class,
        \App\Console\Commands\upgradeUserLabels::class,
        \App\Console\Commands\upgradeUserPassword::class,
        \App\Console\Commands\upgradeUserSpeedLimit::class,
        \App\Console\Commands\upgradeUserSubscribe::class,
        \App\Console\Commands\upgradeUserVmessId::class,
        \App\Console\Commands\AutoReportNode::class,
        \App\Console\Commands\upgradeUserBannoPay::class,
        \App\Console\Commands\AutoCheckNodeStatus::class,
        # 自动禁用余额小于0 的用户
        \App\Console\Commands\AutoBanUserNoMoney::class,
        \App\Console\Commands\Test::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //$schedule->command('autoJob')->everyMinute();
        //Song 定时任务改为30分钟一次
        $schedule->command('autoJob')->everyThirtyMinutes();
        //
        $schedule->command('autoClearLog')->everyThirtyMinutes();
        $schedule->command('autoDecGoodsTraffic')->everyTenMinutes();
        $schedule->command('autoResetUserTraffic')->daily();

        //$schedule->command('autoCheckNodeStatus')->everyMinute();
        //Song
        $schedule->command('autoCheckNodeTCP')->hourly();
        //

        $schedule->command('autoStatisticsNodeDailyTraffic')->dailyAt('3:13');
        $schedule->command('autoStatisticsNodeHourlyTraffic')->hourly();
        $schedule->command('autoStatisticsUserDailyTraffic')->dailyAt('3:17');
        $schedule->command('autoStatisticsUserHourlyTraffic')->hourly();
        $schedule->command('userTrafficAbnormalAutoWarning')->hourly();
        $schedule->command('userExpireAutoWarning')->dailyAt('20:00');
        $schedule->command('userTrafficAutoWarning')->dailyAt('10:30');
        $schedule->command('autoReportNode')->dailyAt('09:00');
        #song 自动禁用 余额少于0 的用户 每天一次
        $schedule->command('autoBanUserNoMoney')->dailyAt('05:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
