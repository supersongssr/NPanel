<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Models\SsNode;
use App\Components\NodeDefaults;
use App\Components\NodeApiToken;
use Illuminate\Support\Facades\DB;

/**
 * 节点 v2 API token 自动签发测试(provisioning/register, 与 SPanel 对称)
 *
 * 回归背景: token 此前只能由 `node:generate-api-tokens` 人工批量补发, 命令跑过
 * 之后新申请(applyId)的节点 api_token 仍为空 → /api/node/config 裁掉 panelApi 段,
 * proxyInstall.sh 装完拿到的还是旧式 ssrpanel.nodeId 配置(新插件读不到配置)。
 *
 * 验证目标:
 *   1. provisioning(NodeDefaults::resetToDefaults, applyId 唯一干净态入口)签发
 *      43 字符 base64url token: 新建首发 / 已有则轮换(回收即吊销旧凭据)
 *   2. register(POST /api/node/register)自愈: 为空补发, 非空不动(重装零扰动)
 *   3. 端到端: applyId → config 下发 panelApi 段且 token 与 DB 一致
 *
 * 运行: podman exec -e APP_ENV=test php7-npanel vendor/bin/phpunit tests/Feature/NodeApiTokenProvisioningTest.php
 */
class NodeApiTokenProvisioningTest extends TestCase
{
    private $nodeId;

    protected function tearDown()
    {
        if ($this->nodeId) {
            $node = SsNode::find($this->nodeId);
            if ($node && $node->node_ids) {
                foreach (array_filter(explode(',', $node->node_ids), 'strlen') as $cid) {
                    DB::table('ss_node')->where('id', (int) $cid)->delete();
                }
            }
            DB::table('ss_node')->where('id', $this->nodeId)->delete();
        }
        parent::tearDown();
    }

    private function isBase64Url43($token)
    {
        return is_string($token) && strlen($token) === 43
            && preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1;
    }

    /** @test */
    public function provisioning_issues_and_rotates_token()
    {
        $node = new SsNode();
        NodeDefaults::resetToDefaults($node);
        $this->assertTrue($this->isBase64Url43((string) $node->api_token),
            'provisioning 应签发 43 字符 base64url token');

        $old = (string) $node->api_token;
        NodeDefaults::resetToDefaults($node);
        $this->assertTrue($this->isBase64Url43((string) $node->api_token));
        $this->assertNotSame($old, (string) $node->api_token,
            '重复 provisioning 应轮换 token(回收死节点即吊销旧凭据)');
    }

    /** @test */
    public function issue_if_needed_keeps_existing_token()
    {
        $node = new SsNode();
        NodeDefaults::resetToDefaults($node);
        $node->api_token = '';
        $this->assertTrue(NodeApiToken::issueIfNeeded($node));
        $this->assertTrue($this->isBase64Url43((string) $node->api_token));

        $existing = (string) $node->api_token;
        $this->assertFalse(NodeApiToken::issueIfNeeded($node));
        $this->assertSame($existing, (string) $node->api_token,
            'register 自愈只补空 token, 不动已有值');
    }

    /** @test */
    public function apply_id_yields_config_with_panel_api()
    {
        // ── applyId: provisioning 真实落库 ──
        $resp = $this->post('/api/node/apply_id', ['node_ip' => '198.51.100.7'], [
            'Authorization' => 'Bearer ' . env('API_TOKEN'),
        ]);
        $resp->assertStatus(200);
        $this->nodeId = json_decode($resp->getContent(), true)['node_id'];

        $dbToken = (string) DB::table('ss_node')->where('id', $this->nodeId)->value('api_token');
        $this->assertTrue($this->isBase64Url43($dbToken),
            "apply_id 落库 token 应非空: node_id={$this->nodeId}");

        // ── register: 激活节点(status=1)且 token 不被扰动 ──
        $resp = $this->post('/api/node/register', [
            'node_id' => $this->nodeId,
            'v2_name' => 'vision',
            'node_ip' => '198.51.100.7',
            'node_country_code' => 'US',
            'node_city' => 'TokenTest',
        ], ['Authorization' => 'Bearer ' . env('API_TOKEN')]);
        $resp->assertStatus(200);

        $this->assertSame($dbToken,
            (string) DB::table('ss_node')->where('id', $this->nodeId)->value('api_token'),
            'register 不应改写已有 token');

        // ── config: 下发 panelApi 段且 token 一致 ──
        $resp = $this->post('/api/node/config', ['node_id' => $this->nodeId], [
            'Authorization' => 'Bearer ' . env('API_TOKEN'),
        ]);
        $resp->assertStatus(200);
        $cfg = json_decode($resp->getContent(), true);
        $this->assertArrayHasKey('panelApi', $cfg,
            '安装流程拉到的配置应含新式 panelApi 段');
        $this->assertSame($dbToken, $cfg['panelApi']['api']['token'] ?? null);
        // 安全默认(911623e4): 已签发 token 的节点不再下发 ssrpanel/DB 凭据,
        // 回滚需显式打开 NODE_API_KEEP_MYSQL_CREDENTIALS(config/nodeapi.php)
        $this->assertArrayNotHasKey('ssrpanel', $cfg,
            '已签发 token 的节点默认不应再拿到 ssrpanel/DB 凭据段');
    }
}
