<?php

namespace App\Components;

use App\Http\Models\SsNode;
use Illuminate\Support\Facades\Schema;

/**
 * 节点 v2 面板 API token 签发(ss_node.api_token)
 *
 * 背景: /api/node/config 双段下发方案里, api_token 非空的节点才下发 panelApi 段
 * (v2 HTTP 模式, 供 xray-plugin-api 新插件读取), 为空则只下发旧式 ssrpanel 段。
 * token 此前只能由 `php artisan node:generate-api-tokens` 人工批量补发 —— 该命令
 * 跑过之后新申请(provisioning)的节点 api_token 仍为空, 节点用 proxyInstall.sh
 * 安装后拉到的配置就还是旧式 ssrpanel.nodeId, 新插件拿不到可用配置。
 *
 * 因此 token 必须在节点生命周期入口自动签发, 不依赖人工命令:
 *   - provisioning(applyId → NodeDefaults::resetToDefaults): 无条件签发新 token
 *     (新建 = 首发; 回收死节点 = 轮换旧 token, 旧机器可能仍持有, 轮换即吊销);
 *   - register(NodeApiController::register): 为空才补发(自愈存量节点; 重装走
 *     register 不走 applyId, 已有 token 不动, 节点无需重新配置)。
 */
class NodeApiToken
{
    /** @var bool|null ss_node.api_token 列存在性缓存(每进程一次) */
    private static $columnExists = null;

    /**
     * 32 字节随机数的 base64url(43 字符, 无填充) —— 契约规定格式,
     * 与 GenerateNodeApiTokens 命令同一算法。
     */
    public static function generate()
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * ss_node.api_token 列是否已迁移。未迁移的旧面板上跳过签发,
     * 避免 INSERT/UPDATE 未知列报错拖垮 applyId。
     */
    public static function columnExists()
    {
        if (self::$columnExists === null) {
            self::$columnExists = Schema::hasColumn('ss_node', 'api_token');
        }
        return self::$columnExists;
    }

    /**
     * 签发新 token(无条件覆盖) — provisioning 语义:
     * 新建节点首发, 回收死节点轮换旧凭据。只改属性不落库, 由调用方 save()。
     */
    public static function rotate(SsNode $node)
    {
        if (!self::columnExists()) {
            return false;
        }
        $node->api_token = self::generate();
        return true;
    }

    /**
     * 仅当 token 为空时补发 — register 自愈路径: 只补缺, 不动已有 token。
     * 只改属性不落库, 由调用方 save()。
     */
    public static function issueIfNeeded(SsNode $node)
    {
        if (!self::columnExists()) {
            return false;
        }
        if ((string) ($node->api_token ?? '') !== '') {
            return false;
        }
        $node->api_token = self::generate();
        return true;
    }
}
