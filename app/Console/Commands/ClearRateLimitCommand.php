<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ClearRateLimitCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rate-limit:clear {code?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear subscription rate limit cache from Redis';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $code = $this->argument('code');

        if ($code) {
            // 清除特定代码的限流缓存
            $this->clearCodeLimit($code);
        } else {
            // 清除所有订阅限流缓存
            $this->clearAllLimits();
        }

        return 0;
    }

    /**
     * 清除特定代码的限流缓存
     *
     * @param string $code
     * @return void
     */
    private function clearCodeLimit($code)
    {
        $this->info("Clearing rate limit cache for code: {$code}");

        $pattern = 'subscribe_limit:' . $code . ':*';
        $scan = Redis::scan(0, ['MATCH' => $pattern, 'COUNT' => 100]);

        $deletedCount = 0;

        if (!empty($scan[1])) {
            foreach ($scan[1] as $key) {
                Redis::del($key);
                $deletedCount++;
                $this->line("  Deleted: {$key}");
            }
        }

        // 删除 IP 集合
        $ipKey = 'subscribe_ip:' . $code;
        if (Redis::exists($ipKey)) {
            Redis::del($ipKey);
            $deletedCount++;
            $this->line("  Deleted: {$ipKey}");
        }

        $this->info("✅ Deleted {$deletedCount} key(s) for code: {$code}");
    }

    /**
     * 清除所有订阅限流缓存
     *
     * @return void
     */
    private function clearAllLimits()
    {
        $this->info("Clearing ALL subscription rate limit cache");

        $pattern = 'subscribe_limit:*';
        $scan = Redis::scan(0, ['MATCH' => $pattern, 'COUNT' => 1000]);

        $deletedCount = 0;

        if (!empty($scan[1])) {
            foreach ($scan[1] as $key) {
                Redis::del($key);
                $deletedCount++;
            }
        }

        // 删除所有 IP 集合
        $ipPattern = 'subscribe_ip:*';
        $ipScan = Redis::scan(0, ['MATCH' => $ipPattern, 'COUNT' => 1000]);

        if (!empty($ipScan[1])) {
            foreach ($ipScan[1] as $key) {
                Redis::del($key);
                $deletedCount++;
            }
        }

        $this->info("✅ Deleted {$deletedCount} key(s) in total");
    }
}
