<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SystemCommandController extends Controller
{
    /**
     * Whitelist of safe commands.
     * key: frontend identifier, value: actual artisan command signature
     */
    protected $whitelist = [
        'initDnsRecords' => 'initDnsRecords',
        'test' => 'Test',
        'autoBanUserNoMoney' => 'autoBanUserNoMoney'
    ];

    /**
     * Display the command management page.
     */
    public function index()
    {
        $commands = [
            [
                'id' => 'initDnsRecords',
                'title' => 'DNS 强同步对账',
                'description' => '同步 Cloudflare A/AAAA 记录，清理孤立记录，耗时较长请耐心等待。',
                'last_run' => Cache::get('last_run_time_initDnsRecords'),
            ],
            [
                'id' => 'test',
                'title' => '系统测试命令',
                'description' => '执行系统预设的 Test 命令，用于验证 Artisan 执行环境。',
                'last_run' => Cache::get('last_run_time_test'),
            ],
            [
                'id' => 'autoBanUserNoMoney',
                'title' => '余额预警封禁',
                'description' => '自动检测并禁用余额小于 0 的用户。',
                'last_run' => Cache::get('last_run_time_autoBanUserNoMoney'),
            ]
        ];

        return view('admin.commands.index', compact('commands'));
    }

    /**
     * Run the specified artisan command.
     */
    public function run(Request $request)
    {
        $id = $request->input('id');

        if (!isset($this->whitelist[$id])) {
            return response()->json(['status' => 'error', 'message' => '非法指令：不在安全白名单中'], 403);
        }

        $command = $this->whitelist[$id];

        try {
            // Disable timeout for long-running scripts
            set_time_limit(0);

            // Build ArrayInput with explicit 'command' key to avoid array_unshift
            // producing numeric keys that crash Symfony ArrayInput::parse() on PHP 7.4
            $input = new \Symfony\Component\Console\Input\ArrayInput(['command' => $command]);
            $outputBuffer = new \Symfony\Component\Console\Output\BufferedOutput();

            $artisan = app('Illuminate\Contracts\Console\Kernel');
            $exitCode = $artisan->handle($input, $outputBuffer);
            $output = $outputBuffer->fetch();

            // Store last run time
            $now = date('Y-m-d H:i:s');
            Cache::forever('last_run_time_' . $id, $now);

            if ($exitCode !== 0) {
                \Log::error("Artisan command returned non-zero exit code ($id): $exitCode", ['output' => $output]);
                return response()->json([
                    'status' => 'error',
                    'message' => '执行返回非零状态码: ' . $exitCode,
                    'output' => $output,
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => '执行成功',
                'output' => $output,
                'last_run' => $now
            ]);
        } catch (\Throwable $e) {
            \Log::error("Artisan command execution failed ($id): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => '执行失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
