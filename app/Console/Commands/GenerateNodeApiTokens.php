<?php

namespace App\Console\Commands;

use App\Http\Models\SsNode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 批量生成节点 v2 API token(ss_node.api_token)
 *
 * 背景: 每节点独立 token 是 v2 后端 API 的鉴权基础。人工逐节点配置不现实,
 *       本命令给所有 api_token 为空的节点一次性生成 43 字符 base64url token,
 *       已有 token 的节点不动(token 保持不变, 节点无需重新配置)。
 *
 * 用法:
 *   php artisan node:generate-api-tokens            # 生成缺失的
 *   php artisan node:generate-api-tokens --show     # 仅列出已有
 *   php artisan node:generate-api-tokens --node=3   # 查看单节点
 */
class GenerateNodeApiTokens extends Command
{
    protected $signature = 'node:generate-api-tokens {--show : 仅列出, 不生成} {--node= : 指定节点 ID}';
    protected $description = '批量生成 v2 后端 API 的每节点 token(ss_node.api_token)';

    public function handle()
    {
        $showOnly = (bool)$this->option('show');
        $onlyNode = $this->option('node') !== null ? (int)$this->option('node') : null;

        $query = SsNode::query();
        if ($onlyNode !== null) {
            $query->where('id', $onlyNode);
        }
        $nodes = $query->orderBy('id')->get();

        if ($nodes->isEmpty()) {
            $this->info('没有匹配的节点');
            return 0;
        }

        $generated = 0;
        $rows = [];
        foreach ($nodes as $node) {
            $token = $node->api_token;
            if ($token === null || $token === '') {
                if ($showOnly) {
                    $rows[] = [$node->id, mb_substr($node->name, 0, 20), '(未生成)', '跳过(--show)'];
                    continue;
                }
                for ($attempt = 0; $attempt < 5; $attempt++) {
                    $token = $this->generateApiToken();
                    try {
                        // 条件更新防并发重复生成(唯一索引兜底)
                        $updated = DB::table('ss_node')
                            ->where('id', $node->id)
                            ->whereNull('api_token')
                            ->update(['api_token' => $token]);
                        if ($updated > 0) {
                            break;
                        }
                        $token = SsNode::query()->find($node->id)->api_token;
                        break;
                    } catch (\Exception $e) {
                        // 唯一索引冲突(极小概率): 换一个重试
                        $token = null;
                        continue;
                    }
                }
                if ($token === null) {
                    $rows[] = [$node->id, mb_substr($node->name, 0, 20), '(失败)', '生成失败, 请重跑'];
                    continue;
                }
                $generated++;
                $rows[] = [$node->id, mb_substr($node->name, 0, 20), $token, '已生成'];
            } else {
                $rows[] = [$node->id, mb_substr($node->name, 0, 20), $token, '已有'];
            }
        }

        $this->table(['ID', '名称', 'API Token', '状态'], $rows);

        if ($showOnly) {
            $this->info('仅查看模式, 未做修改');
        } else {
            $this->info("新生成 token: {$generated} 个(已有 token 的节点保持不变)");
            $this->line('提示: 把 token 填入对应节点 config.json 的 panelApi.api.token 段');
        }
        return 0;
    }

    /**
     * 32 字节随机数的 base64url(43 字符, 无填充) —— 契约规定格式
     */
    private function generateApiToken()
    {
        $raw = random_bytes(32);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
