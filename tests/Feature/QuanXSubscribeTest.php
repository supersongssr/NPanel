<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Http\Models\SsNode;
use App\Http\Models\User;
use App\Http\Models\UserSubscribe;
use App\Services\Subscribe\Formatters\QuanXFormatter;

/**
 * Quantumult X 订阅格式测试
 *
 * 测试 QuanX 明文 (format=quanx) 与 Base64 (format=quanx-b64) 双模式。
 * 覆盖 VMess, VLESS (Vision), Trojan, Shadowsocks 四种协议。
 *
 * 注意：使用 DatabaseTransactions 替代 RefreshDatabase，
 * 因为 PHP 7.4 与 Symfony Console 的已知兼容性问题导致 RefreshDatabase 不可用。
 */
class QuanXSubscribeTest extends TestCase
{
    use DatabaseTransactions;

    private $testUser;
    private $subscribeCode = 'QuanX';

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试用户
        $user = new User();
        $user->username = 'quanx_tester';
        $user->password = password_hash('test123', PASSWORD_BCRYPT);
        $user->vmess_id = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $user->status = 1;
        $user->enable = 1;
        $user->node_group = 1;
        $user->level = 10;
        $user->expire_time = '2099-12-31 23:59:59';
        $user->transfer_enable = 1099511627776;
        $user->u = 0;
        $user->d = 0;
        $user->save();
        $this->testUser = $user;

        // 创建订阅码
        $subscribe = new UserSubscribe();
        $subscribe->user_id = $user->id;
        $subscribe->code = $this->subscribeCode;
        $subscribe->status = 1;
        $subscribe->times = 0;
        $subscribe->times_today = 0;
        $subscribe->ban_time = 0;
        $subscribe->ban_desc = '';
        $subscribe->save();
    }

    /**
     * 创建 4 个测试节点：
     * - Node 1: VMess (含特殊名称, WS+TLS)
     * - Node 2: VLESS (Vision flow)
     * - Node 3: Trojan (TLS)
     * - Node 4: Shadowsocks
     */
    private function createTestNodes()
    {
        // Node 1: VMess，名称含特殊字符
        $vmessNode = new SsNode();
        $vmessNode->type = 2;
        $vmessNode->name = "香港,测试=节点\n换行";
        $vmessNode->status = 1;
        $vmessNode->is_subscribe = 1;
        $vmessNode->node_group = 1;
        $vmessNode->level = 1;
        $vmessNode->server = 'hk.example.com';
        $vmessNode->v2_port = 443;
        $vmessNode->v2_method = 'aes-128-gcm';
        $vmessNode->v2_net = 'ws';
        $vmessNode->v2_host = 'ws.hk.example.com';
        $vmessNode->v2_path = '/vmess-ws';
        $vmessNode->v2_tls = 1;
        $vmessNode->v2_sni = 'hk.example.com';
        $vmessNode->v2_alter_id = 0;
        $vmessNode->traffic_rate = 1;
        $vmessNode->country_code = 'hk';
        $vmessNode->method = 'aes-256-cfb';
        $vmessNode->protocol = 'origin';
        $vmessNode->obfs = 'plain';
        $vmessNode->bandwidth = 100;
        $vmessNode->traffic = 1000;
        $vmessNode->traffic_limit = 1099511627776;
        $vmessNode->save();

        // Node 2: VLESS (Vision)
        $vlessNode = new SsNode();
        $vlessNode->type = 3;
        $vlessNode->name = '日本Vision节点';
        $vlessNode->status = 1;
        $vlessNode->is_subscribe = 1;
        $vlessNode->node_group = 1;
        $vlessNode->level = 1;
        $vlessNode->server = 'jp.example.com';
        $vlessNode->v2_port = 8443;
        $vlessNode->v2_method = 'none';
        $vlessNode->v2_net = 'tcp';
        $vlessNode->v2_host = '';
        $vlessNode->v2_path = '';
        $vlessNode->v2_tls = 1;
        $vlessNode->v2_sni = 'jp.example.com';
        $vlessNode->v2_flow = 'xtls-rprx-vision';
        $vlessNode->v2_encryption = 'none';
        $vlessNode->v2_fp = 'chrome';
        $vlessNode->traffic_rate = 1.5;
        $vlessNode->country_code = 'jp';
        $vlessNode->method = 'aes-256-cfb';
        $vlessNode->protocol = 'origin';
        $vlessNode->obfs = 'plain';
        $vlessNode->bandwidth = 100;
        $vlessNode->traffic = 1000;
        $vlessNode->traffic_limit = 1099511627776;
        $vlessNode->save();

        // Node 3: Trojan
        $trojanNode = new SsNode();
        $trojanNode->type = 4;
        $trojanNode->name = '美国Trojan节点';
        $trojanNode->status = 1;
        $trojanNode->is_subscribe = 1;
        $trojanNode->node_group = 1;
        $trojanNode->level = 1;
        $trojanNode->server = 'us.example.com';
        $trojanNode->v2_port = 443;
        $trojanNode->v2_tls = 1;
        $trojanNode->v2_sni = 'us.example.com';
        $trojanNode->v2_net = 'tcp';
        $trojanNode->v2_host = '';
        $trojanNode->v2_path = '';
        $trojanNode->traffic_rate = 1;
        $trojanNode->country_code = 'us';
        $trojanNode->method = 'aes-256-cfb';
        $trojanNode->protocol = 'origin';
        $trojanNode->obfs = 'plain';
        $trojanNode->bandwidth = 100;
        $trojanNode->traffic = 1000;
        $trojanNode->traffic_limit = 1099511627776;
        $trojanNode->save();

        // Node 4: Shadowsocks
        $ssNode = new SsNode();
        $ssNode->type = 1;
        $ssNode->name = '新加坡SS节点';
        $ssNode->status = 1;
        $ssNode->is_subscribe = 1;
        $ssNode->node_group = 1;
        $ssNode->level = 1;
        $ssNode->server = 'sg.example.com';
        $ssNode->ssh_port = 8388;
        $ssNode->method = 'aes-256-gcm';
        $ssNode->single_passwd = 'ss_password_123';
        $ssNode->traffic_rate = 1;
        $ssNode->country_code = 'sg';
        $ssNode->protocol = 'origin';
        $ssNode->obfs = 'plain';
        $ssNode->bandwidth = 100;
        $ssNode->traffic = 1000;
        $ssNode->traffic_limit = 1099511627776;
        $ssNode->save();
    }

    /**
     * Test 1: 明文模式与名称防爆验证
     */
    public function test_quanx_plain_text_and_sanitization()
    {
        $this->createTestNodes();

        $res = $this->get('/s/' . $this->subscribeCode . '?format=quanx');

        $res->assertStatus(200);

        $content = $res->getContent();
        $lines = array_filter(explode("\n", trim($content)), 'strlen');

        // 至少应该有 4 个节点
        $this->assertGreaterThanOrEqual(4, count($lines), 'Should have at least 4 nodes');

        // 遍历每行，验证 tag 防爆
        foreach ($lines as $line) {
            // 提取 tag= 后面的值
            if (preg_match('/tag=(.+)$/', $line, $matches)) {
                $tagValue = $matches[1];
                // tag 值中不应包含原始的逗号和等号
                $this->assertStringNotContainsString(',', $tagValue,
                    "Tag '{$tagValue}' should not contain unescaped comma");
                $this->assertStringNotContainsString('=', $tagValue,
                    "Tag '{$tagValue}' should not contain unescaped equals sign");
            }

            // 验证每行以正确的协议前缀开头
            $this->assertRegExp(
                '/^(vmess|vless|trojan|shadowsocks)=/',
                $line,
                "Line should start with a valid protocol prefix: {$line}"
            );
        }

        // 验证 VMess 节点（特殊名称节点）的 tag 被正确清洗
        $vmessLines = array_filter($lines, function ($line) {
            return strpos($line, 'vmess=') === 0;
        });
        $this->assertNotEmpty($vmessLines, 'Should have at least one vmess node');
        $firstVmess = reset($vmessLines);
        $this->assertStringContainsString('香港_测试-节点换行', $firstVmess,
            'Special characters in node name should be sanitized');
    }

    /**
     * Test 2: Base64 模式与核心协议解析
     */
    public function test_quanx_base64_and_vision_support()
    {
        $this->createTestNodes();

        $res = $this->get('/s/' . $this->subscribeCode . '?format=quanx-b64');

        $res->assertStatus(200);

        $encoded = $res->getContent();
        $plain = base64_decode($encoded, true);
        $this->assertNotFalse($plain, 'Content should be valid Base64');
        $this->assertNotEmpty($plain, 'Decoded content should not be empty');

        $lines = array_filter(explode("\n", trim($plain)), 'strlen');

        // 验证 VLESS 行包含 Vision flow
        $vlessLines = array_filter($lines, function ($line) {
            return strpos($line, 'vless=') === 0;
        });
        $this->assertNotEmpty($vlessLines, 'Should have at least one vless node');

        $vlessLine = reset($vlessLines);
        $this->assertStringContainsString('flow=xtls-rprx-vision', $vlessLine,
            'VLESS node should contain Vision flow setting');

        // 验证倍率后缀
        $this->assertStringContainsString('_x1.5', $vlessLine,
            'VLESS node with traffic_rate=1.5 should have _x1.5 suffix');

        // 验证 Trojan 行包含 TLS
        $trojanLines = array_filter($lines, function ($line) {
            return strpos($line, 'trojan=') === 0;
        });
        $this->assertNotEmpty($trojanLines, 'Should have at least one trojan node');

        $trojanLine = reset($trojanLines);
        $this->assertStringContainsString('over-tls=true', $trojanLine,
            'Trojan node should have TLS enabled');
        $this->assertStringContainsString('tls-host=', $trojanLine,
            'Trojan node should have tls-host parameter');
    }

    /**
     * Test 3: VMess WS+TLS 组合验证
     */
    public function test_quanx_vmess_ws_tls()
    {
        $this->createTestNodes();

        $res = $this->get('/s/' . $this->subscribeCode . '?format=quanx');
        $content = $res->getContent();
        $lines = array_filter(explode("\n", trim($content)), 'strlen');

        $vmessLines = array_filter($lines, function ($line) {
            return strpos($line, 'vmess=') === 0;
        });
        $this->assertNotEmpty($vmessLines);

        $vmessLine = reset($vmessLines);

        // 验证 WS+TLS 组合参数
        $this->assertStringContainsString('obfs=ws', $vmessLine,
            'VMess WS node should have obfs=ws');
        $this->assertStringContainsString('over-tls=true', $vmessLine,
            'VMess WS+TLS node should have over-tls=true');
        $this->assertStringContainsString('obfs-uri=/vmess-ws', $vmessLine,
            'VMess WS node should have correct obfs-uri');
        $this->assertStringContainsString('obfs-host=ws.hk.example.com', $vmessLine,
            'VMess WS node should have correct obfs-host');
    }

    /**
     * Test 4: Shadowsocks 节点验证
     */
    public function test_quanx_shadowsocks_node()
    {
        $this->createTestNodes();

        $res = $this->get('/s/' . $this->subscribeCode . '?format=quanx');
        $content = $res->getContent();
        $lines = array_filter(explode("\n", trim($content)), 'strlen');

        $ssLines = array_filter($lines, function ($line) {
            return strpos($line, 'shadowsocks=') === 0;
        });
        $this->assertNotEmpty($ssLines, 'Should have at least one shadowsocks node');

        $ssLine = reset($ssLines);
        $this->assertStringContainsString('sg.example.com:8388', $ssLine,
            'SS node should have correct server:port');
        $this->assertStringContainsString('method=aes-256-gcm', $ssLine,
            'SS node should have correct cipher method');
        $this->assertStringContainsString('password=ss_password_123', $ssLine,
            'SS node should have correct password');
    }

    /**
     * Test 5: QuanXFormatter 单元测试 — Reality 节点
     */
    public function test_quanx_formatter_reality_unit()
    {
        $formatter = new QuanXFormatter();

        // 创建 VLESS Reality 节点
        $node = new SsNode();
        $node->type = 3;
        $node->name = '测试_VLESS,Reality=节点';
        $node->server = 'test.server.com';
        $node->v2_port = 2053;
        $node->v2_encryption = 'reality';
        $node->v2_sni = 'sni.server.com';
        $node->v2_fp = 'chrome';
        $node->v2_mode = 'abcdef';
        $node->v2_flow = '';
        $node->v2_net = 'tcp';
        $node->v2_tls = 0;
        $node->v2_host = '';
        $node->v2_path = '';
        $node->traffic_rate = 1;
        $node->node_uuid = 'reality-uuid-1234';

        $user = new User();
        $user->vmess_id = 'uuid-test-reality';

        $nodeCollection = collect([$node]);
        $result = $formatter->format($nodeCollection, $user, false);

        // 验证 Reality 参数
        $this->assertStringContainsString('security=reality', $result);
        $this->assertStringContainsString('tls-host=sni.server.com', $result);
        $this->assertStringContainsString('fingerprint=chrome', $result);
        $this->assertStringContainsString('short-id=abcdef', $result);

        // 验证名称防爆
        $this->assertStringContainsString('tag=测试_VLESS_Reality-节点', $result);
        $this->assertStringNotContainsString('tag=测试_VLESS,Reality=节点', $result);

        // 验证 UUID 使用 node_uuid
        $this->assertStringContainsString('password=reality-uuid-1234', $result);
    }

    /**
     * Test 6: Base64 编码往返验证
     */
    public function test_quanx_b64_roundtrip()
    {
        $this->createTestNodes();

        $resPlain = $this->get('/s/' . $this->subscribeCode . '?format=quanx');
        $resB64 = $this->get('/s/' . $this->subscribeCode . '?format=quanx-b64');

        $plainContent = trim($resPlain->getContent());
        $decodedB64 = trim(base64_decode($resB64->getContent(), true));

        $this->assertEquals($plainContent, $decodedB64,
            'Plain text and Base64-decoded content should be identical');
    }
}
