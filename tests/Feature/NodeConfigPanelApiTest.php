<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Models\SsNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

/**
 * POST /api/node/config panelApi 段下发测试(v2 兼容模式, 与 SPanel 对称)
 *
 * 验证目标(安全默认: DB 凭据不下发给 API 节点):
 *   1. api_token 为空 → 与旧版一致: 无 panelApi 段, ssrpanel 段完整(零回归)
 *   2. api_token 非空 → panelApi 填真实 baseURL/token, 默认无 ssrpanel 段
 *      (不再下发库地址/账号/密码 — 最低安全保证)
 *   3. NODE_API_KEEP_MYSQL_CREDENTIALS=true → 恢复双段下发(显式回滚开关)
 *   4. NODE_API_DROP_MYSQL_CREDENTIALS=true → 删除 ssrpanel(Phase 4 收尾,
 *      含未签发 token 的旧插件节点)
 *   5. panelApi.user 的 inboundTags/flows 填充正确
 *
 * 运行: podman exec -e APP_ENV=test php7-npanel vendor/bin/phpunit tests/Feature/NodeConfigPanelApiTest.php
 */
class NodeConfigPanelApiTest extends TestCase
{
    private $nodeId;

    protected function setUp()
    {
        parent::setUp();
        DB::table('ss_node')->where('name', 'panelapi-cfg-test')->delete();
        $this->nodeId = DB::table('ss_node')->insertGetId([
            'name' => 'panelapi-cfg-test',
            'type' => 1,
            'server' => 's1.cfgtest.example.com',
            'v2_name' => 'xhttp-hy2-ws-grpc',
            'traffic_rate' => 1.0,
            'level' => 0,
            'node_group' => 0,
            'traffic_limit' => 0,
            'traffic_used' => 0,
            'status' => 1,
            'sort' => 0,
            'is_clone' => 0,
            'is_subscribe' => 1,
            'country_code' => 'CN',
            'api_token' => null,
            'api_ip_allowlist' => null,
            'v2_port' => 443,
        ]);
    }

    protected function tearDown()
    {
        DB::table('ss_node')->where('name', 'panelapi-cfg-test')->delete();
        $this->clearEnv('NODE_API_DROP_MYSQL_CREDENTIALS');
        $this->clearEnv('NODE_API_KEEP_MYSQL_CREDENTIALS');
        parent::tearDown();
    }

    private function clearEnv($key)
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    private function setEnv($key, $value)
    {
        // env() 读取顺序: $_ENV / $_SERVER / getenv
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }

    private function fetchConfig()
    {
        $resp = $this->post('/api/node/config', ['node_id' => $this->nodeId], [
            'Authorization' => 'Bearer ' . env('API_TOKEN'),
        ]);
        $resp->assertStatus(200);
        return json_decode($resp->getContent(), true);
    }

    /** @test */
    public function no_token_yields_legacy_config_without_panelapi()
    {
        $cfg = $this->fetchConfig();

        $this->assertArrayNotHasKey('panelApi', $cfg, '未签发 token 的节点不应下发 panelApi 段');
        $this->assertArrayHasKey('ssrpanel', $cfg, 'ssrpanel 段应保留');
        $this->assertSame($this->nodeId, $cfg['ssrpanel']['nodeId']);
        $this->assertNotEquals('__dbHost__', $cfg['ssrpanel']['mysql']['host'], 'dbHost 占位符应已替换');
        $this->assertArrayHasKey('inbounds', $cfg);
        $this->assertContains('StatsService', $cfg['api']['services']);
    }

    /** @test */
    public function with_token_drops_ssrpanel_by_default()
    {
        DB::table('ss_node')->where('id', $this->nodeId)
            ->update(['api_token' => 'CfgTestTokenPanelApi0000000000000000']);
        $cfg = $this->fetchConfig();

        // panelApi 段填充
        $expectedBase = rtrim(env('NODE_API_BASE_URL', config('app.url')), '/') . '/api/v2/backend';
        $this->assertArrayHasKey('panelApi', $cfg);
        $this->assertSame($expectedBase, $cfg['panelApi']['api']['baseURL']);
        $this->assertSame('CfgTestTokenPanelApi0000000000000000', $cfg['panelApi']['api']['token']);

        // 安全默认: 不再下发 ssrpanel/DB 凭据(最低安全保证)
        $this->assertArrayNotHasKey('ssrpanel', $cfg, '已签发 token 的节点不应再拿到 ssrpanel/DB 凭据段');

        // user 段填充正确; xhttp-hy2-ws-grpc 组无 vision → flows 不应存在
        $this->assertContains('proxy-xhttp', $cfg['panelApi']['user']['inboundTags']);
        $this->assertArrayNotHasKey('flows', $cfg['panelApi']['user']);
    }

    /** @test */
    public function keep_mysql_credentials_switch_restores_dual_section()
    {
        DB::table('ss_node')->where('id', $this->nodeId)
            ->update(['api_token' => 'CfgTestTokenPanelApi0000000000000000']);
        $this->setEnv('NODE_API_KEEP_MYSQL_CREDENTIALS', 'true');

        $cfg = $this->fetchConfig();

        // 显式回滚开关: 恢复双段下发(旧插件可用)
        $this->assertArrayHasKey('panelApi', $cfg);
        $this->assertArrayHasKey('ssrpanel', $cfg);
        $this->assertNotEquals('__dbHost__', $cfg['ssrpanel']['mysql']['host']);
        $this->assertSame(
            $cfg['ssrpanel']['user']['inboundTags'],
            $cfg['panelApi']['user']['inboundTags']
        );
    }

    /** @test */
    public function vision_group_sets_flow_mapping()
    {
        DB::table('ss_node')->where('id', $this->nodeId)
            ->update(['api_token' => 'CfgTestTokenPanelApi0000000000000000', 'v2_name' => 'vision']);
        $cfg = $this->fetchConfig();

        $this->assertSame(
            ['proxy-vision' => 'xtls-rprx-vision'],
            $cfg['panelApi']['user']['flows'],
            'vision 节点 panelApi.flows 应设置'
        );
        $this->assertContains('proxy-vision', $cfg['panelApi']['user']['inboundTags']);
    }

    /** @test */
    public function drop_mysql_credentials_switch_removes_ssrpanel()
    {
        // Phase 4 收尾开关: 连未签发 token 的旧插件节点也强制删 ssrpanel
        $this->setEnv('NODE_API_DROP_MYSQL_CREDENTIALS', 'true');

        // ① 无 token 旧节点: 本应保留 ssrpanel, 开关打开后也删
        $cfg = $this->fetchConfig();
        $this->assertArrayNotHasKey('ssrpanel', $cfg, 'Phase 4 开关应删除 ssrpanel/DB 凭据段');
        $this->assertArrayNotHasKey('panelApi', $cfg);

        // ② 有 token 新节点: panelApi 保留
        DB::table('ss_node')->where('id', $this->nodeId)
            ->update(['api_token' => 'CfgTestTokenPanelApi0000000000000000']);
        $cfg = $this->fetchConfig();
        $this->assertArrayNotHasKey('ssrpanel', $cfg);
        $this->assertArrayHasKey('panelApi', $cfg, 'panelApi 段应保留');
    }

    /** @test */
    public function offline_node_gets_403()
    {
        DB::table('ss_node')->where('id', $this->nodeId)->update(['status' => 0]);
        $this->post('/api/node/config', ['node_id' => $this->nodeId], [
            'Authorization' => 'Bearer ' . env('API_TOKEN'),
        ])->assertStatus(403);
    }
}
