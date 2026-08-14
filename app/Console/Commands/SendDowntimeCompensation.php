<?php

namespace App\Console\Commands;

use App\Http\Models\User;
use App\Http\Models\UserBalanceLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Log;

/**
 * 一次性停机补偿批次
 *
 * 给 status=0/1 且非管理员用户余额 +3 元 (300 分),
 * 并写入用户余额变动日志 (user_balance_log)。
 *
 * 一次性保证 (must):
 *   1. 整批成功完成后, 写入 storage/downtime_compensation.lock 永久锁文件;
 *      再次运行检测到锁存在 -> 直接拒绝, 禁止二次运行。
 *   2. 余额变动日志中的固定 desc (含批次KEY) 作为每用户的幂等标记,
 *      保证即使中途崩溃 (尚未写锁) 也可安全重跑补齐, 绝不重复发放。
 *
 * 不进入 schedule, 仅人工手动执行。
 *
 * Class SendDowntimeCompensation
 */
class SendDowntimeCompensation extends Command
{
    // 金额: 3 元 = 300 分 (balance 字段单位为「分」)
    const AMOUNT_CENTS = 300;

    // 批次唯一标识, 同时写入 desc 用于幂等
    const BATCH_KEY = 'DOWNTIME_COMP_20250813_3YUAN';

    // 写入 user_balance_log.desc 的固定字符串 (精确匹配, 用于幂等查重)
    const DESC = '停机补偿3元 #DOWNTIME_COMP_20250813_3YUAN';

    // 永久一次性锁 (整批完成后写入); 运行期并发锁
    const LOCK_FILE         = 'downtime_compensation.lock';
    const RUNNING_LOCK_FILE = 'downtime_compensation.running.lock';

    protected $signature = 'downtime:compensation
        {--dry-run : 仅统计不写入}
        {--yes : 跳过交互确认 (危险, 慎用)}';

    protected $description = '一次性停机补偿: 给 status=0/1 且非管理员用户余额 +3元 (仅可运行一次)';

    public function handle()
    {
        $lockPath = storage_path(self::LOCK_FILE);

        // ---------------------------------------------------------------
        // 1. 一次性硬锁检测: 已运行过则禁止二次运行
        // ---------------------------------------------------------------
        if (is_file($lockPath)) {
            $info   = json_decode((string) file_get_contents($lockPath), true);
            $doneAt = is_array($info) && !empty($info['completed_at']) ? $info['completed_at'] : '(未知)';
            $counts = is_array($info) && isset($info['counts']) ? $info['counts'] : [];

            $this->error('----------------------------------------------');
            $this->error(' ⛔ 该停机补偿批次已执行完毕, 禁止二次运行!');
            $this->error('----------------------------------------------');
            $this->line('  锁文件  : ' . $lockPath);
            $this->line('  完成时间: ' . $doneAt);
            if ($counts) {
                $this->line(sprintf(
                    '  上次结果: 成功 %s / 跳过 %s / 失败 %s',
                    $counts['success'],
                    $counts['skipped'],
                    $counts['failed']
                ));
            }
            $this->line('  如确需重置, 请人工核查 user_balance_log 后删除该锁文件 (风险自负).');

            return 1;
        }

        $isDry = (bool) $this->option('dry-run');

        // ---------------------------------------------------------------
        // 2. 打印批次信息 + 目标统计
        // ---------------------------------------------------------------
        $this->info('==================================================');
        $this->info(' 停机补偿 · 一次性批次');
        $this->info('==================================================');
        $this->line(' 批次KEY : ' . self::BATCH_KEY);
        $this->line(' 金额    : ' . self::AMOUNT_CENTS . ' 分 (= ' . (self::AMOUNT_CENTS / 100) . ' 元/人)');
        $this->line(' 范围    : status IN (0,1) AND is_admin=0 AND id>1');
        $this->line(' 模式    : ' . ($isDry ? 'DRY-RUN (不写入)' : 'REAL (写入)'));
        $this->line(' 锁文件  : ' . $lockPath);
        $this->info('==================================================');

        $baseQuery = User::query()
            ->where('id', '>', 1)
            ->where('is_admin', 0)
            ->whereIn('status', [0, 1]);

        $totalTarget = (clone $baseQuery)->count();

        // 幂等预检: 本批次是否已有用户被补过 (首次运行应为 0; 崩溃重跑时 >0)
        $alreadyPaid = UserBalanceLog::query()
            ->where('desc', self::DESC)
            ->count();

        $pending = $totalTarget; // 实际待补以循环内的 desc 查重为准, 这里仅展示
        $this->line(' 目标用户总数  : ' . $totalTarget);
        $this->line(' 本批次已补过  : ' . $alreadyPaid . ' (首次运行应为 0)');
        $this->line(' 预计发放总额  : ¥' . number_format($totalTarget * self::AMOUNT_CENTS / 100, 2));

        if ($totalTarget === 0) {
            $this->warn('无目标用户, 退出.');

            return 0;
        }

        if ($isDry) {
            $this->info('[DRY-RUN] 未写入任何数据. 去掉 --dry-run 执行真实补偿.');

            return 0;
        }

        // ---------------------------------------------------------------
        // 3. 交互确认
        // ---------------------------------------------------------------
        if (! $this->option('yes')) {
            $this->warn('即将对 ' . $totalTarget . ' 个用户余额各 +' . (self::AMOUNT_CENTS / 100) . ' 元, 写入余额变动日志.');
            $answer = $this->ask('确认执行请输入 yes');
            if (strtolower(trim((string) $answer)) !== 'yes') {
                $this->warn('未确认, 已取消.');

                return 0;
            }
        }

        // ---------------------------------------------------------------
        // 4. 并发运行锁 (防止两个终端同时跑)
        // ---------------------------------------------------------------
        $runningPath = storage_path(self::RUNNING_LOCK_FILE);
        $fh = @fopen($runningPath, 'c+');
        if (! $fh || ! flock($fh, LOCK_EX | LOCK_NB)) {
            $this->error('⛔ 另一个补偿进程正在运行, 或无法获取运行锁.');

            return 1;
        }
        ftruncate($fh, 0);
        fwrite($fh, json_encode(['started_at' => date('Y-m-d H:i:s'), 'pid' => getmypid()]));
        fflush($fh);

        $success   = 0;
        $skipped   = 0;
        $failed    = 0;
        $failedIds = [];
        $startTime = microtime(true);

        Log::info('[停机补偿] 开始批次 ' . self::BATCH_KEY);

        // ---------------------------------------------------------------
        // 5. 流式分批处理 + 事务 + 幂等
        // ---------------------------------------------------------------
        User::query()
            ->where('id', '>', 1)
            ->where('is_admin', 0)
            ->whereIn('status', [0, 1])
            ->orderBy('id')
            ->chunkById(500, function ($users) use (&$success, &$skipped, &$failed, &$failedIds) {
                foreach ($users as $user) {
                    try {
                        DB::beginTransaction();

                        // 幂等: 该用户在本批次是否已补过
                        $exists = UserBalanceLog::query()
                            ->where('user_id', $user->id)
                            ->where('desc', self::DESC)
                            ->exists();
                        if ($exists) {
                            DB::rollBack();
                            $skipped++;
                            continue;
                        }

                        // 行锁读取当前余额, 保证 before/after 准确 (余额单位: 分)
                        $before = (int) User::query()
                            ->where('id', $user->id)
                            ->lockForUpdate()
                            ->value('balance');
                        $after = $before + self::AMOUNT_CENTS;

                        // 加余额
                        User::query()->where('id', $user->id)->increment('balance', self::AMOUNT_CENTS);

                        // 写入余额变动日志
                        $log             = new UserBalanceLog();
                        $log->user_id    = $user->id;
                        $log->order_id   = 0;
                        $log->before     = $before;
                        $log->after      = $after;
                        $log->amount     = self::AMOUNT_CENTS;
                        $log->coupon_id  = null;
                        $log->desc       = self::DESC;
                        $log->created_at = date('Y-m-d H:i:s');
                        $log->save();

                        DB::commit();
                        $success++;
                    } catch (\Exception $e) {
                        DB::rollBack();
                        $failed++;
                        $failedIds[] = $user->id;
                        Log::error('[停机补偿] 用户 ' . $user->id . ' 失败: ' . $e->getMessage());
                    }
                }
                $this->line(' 进度累计: 成功 ' . $success . ' / 跳过 ' . $skipped . ' / 失败 ' . $failed);
            });

        $elapsed = round(microtime(true) - $startTime, 2);

        // ---------------------------------------------------------------
        // 6. 写入永久一次性锁 (整批完成, 无论有无失败; 失败明细记入锁)
        // ---------------------------------------------------------------
        $lockData = [
            'batch_key'    => self::BATCH_KEY,
            'amount_cents' => self::AMOUNT_CENTS,
            'status'       => 'completed',
            'started_at'   => date('Y-m-d H:i:s'),
            'completed_at' => date('Y-m-d H:i:s'),
            'elapsed_sec'  => $elapsed,
            'counts'       => [
                'success' => $success,
                'skipped' => $skipped,
                'failed'  => $failed,
            ],
            'failed_ids'   => $failedIds,
        ];
        file_put_contents($lockPath, json_encode($lockData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 释放并发运行锁
        flock($fh, LOCK_UN);
        fclose($fh);
        @unlink($runningPath);

        Log::info('[停机补偿] 批次完成 ' . self::BATCH_KEY, $lockData['counts']);

        // ---------------------------------------------------------------
        // 7. 汇总
        // ---------------------------------------------------------------
        $this->info('==================================================');
        $this->info(' 执行完成 (耗时 ' . $elapsed . 's)');
        $this->info('==================================================');
        $this->info(' 成功 : ' . $success);
        $this->line(' 跳过 : ' . $skipped . ' (本批次已补过)');
        $this->line(' 失败 : ' . $failed . ($failed ? ' [' . implode(',', array_slice($failedIds, 0, 20)) . ']' : ''));
        $this->info(' 永久锁已写入: ' . $lockPath . ' (禁止二次运行)');

        return $failed > 0 ? 2 : 0;
    }
}
