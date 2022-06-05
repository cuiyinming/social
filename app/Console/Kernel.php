<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected $commands = [
        //
    ];


    protected function schedule(Schedule $schedule)
    {
        $schedule->command('sync:es', ['users', 'users', 'push'])->everyThirtyMinutes()->withoutOverlapping();
        //$schedule->command('sync:es', ['discover', 'discover', 'push'])->everyThirtyMinutes()->withoutOverlapping();
        $schedule->command('process:min')->everyMinute()->withoutOverlapping();
        //极光推送
        //$schedule->command('process:jpush')->everyMinute()->withoutOverlapping();

        //每日报告 ==== 移到了外部crontab
        //$schedule->command('report:daily')->dailyAt('23:58')->withoutOverlapping();
        $schedule->command('process:client ')->dailyAt('23:50')->withoutOverlapping();
//        $schedule->command('jobs')->everyMinute()->withoutOverlapping();
        //每半个小时活跃一次气氛
        //$schedule->command('auto:active')->everyThirtyMinutes()->withoutOverlapping();
        //同步活跃经纬度信息 [每十分钟同步一次] ==== 移到了外部crontab
        //$schedule->command('process:tenMin')->everyTenMinutes()->withoutOverlapping();
        //每月月初执行一次
        $schedule->command('process:month')->monthlyOn(1, '0:10')->withoutOverlapping();
        $schedule->command('process:half')->everyThirtyMinutes()->withoutOverlapping();
        //每天同步vip 信息  ==== 移到了外部crontab
        //$schedule->command('vip:daily')->daily()->withoutOverlapping();
        $schedule->command('report:baidu')->daily()->withoutOverlapping();
        //每十分钟监控系统资源
        $schedule->command('monitor')->everyTenMinutes()->withoutOverlapping();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
