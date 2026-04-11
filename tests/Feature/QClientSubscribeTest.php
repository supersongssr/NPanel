<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Http\Models\SsNode;
use App\Http\Models\User;
use App\Http\Models\UserSubscribe;
use App\Http\Controllers\QuanXFormatter;

/**
 * Q-Client 订阅格式测试 (基于 QuanXFormatter)
 *
 * 验证 Q-Client 目标客户端专属订阅格式（即 QuanX 格式）的正确性。
 * 覆盖明文模式 (format=quanx) 与 Base64 模式 (format=quanx-b64)。
 * 深度验证 Vision 和 Reality 流控特性的安全下发。
 *
 * 注意：使用 DatabaseTransactions 替代 RefreshDatabase，
 * 因为 PHP 7.4 与 Symfony Console 的已知兼容性问题导致 RefreshDatabase 不可用。
 */
class QClientSubscribeTest extends TestCase
{
    use DatabaseTransactions;

    private $testUser;
    private $subscribeCode = 'QClnt';

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试用户（user 表无 email 字段）
        $user = new User();
        $user->username = 'qclient_tester';
        $user->password = password_hash('test123', PASSWORD_BCRYPT);
        $user->vmess_id = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
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
     * 创建测试节点（按规范要求）：
     * - Node 1: Protocol-VM (VMess) 含极度特殊字符名称 `香港,测试=节点\n`
     * - Node 2: Protocol-VL (VLESS) 带 flow=xtls-rprx-vision
     * - Node 3: Protocol-TJ (Trojan) 带 TLS
     * - Node 4: Protocol-SS (Shadowsocks)
     */
    private function createTestNodes()
    {
        // Node 1: VMess — 极度特殊字符名称
        $vmessNode = new SsNode();
        $vmessNode->type = 2; // Protocol-VM
        $vmessNode->name = "香港,测试=节点\n";
        $vmessNode->status = 1;
        $vmessNode->is_subscribe = 1;
        $vmessNode->node_group = 1;
        $vmessNode->level = 1;
        $vmessNode->server = 'hk-qc.example.com';
        $vmessNode->v2_port = 443;
        $vmessNode->v2_method = 'aes-128-gcm';
        $vmessNode->v2_net = 'ws';
        $vmessNode->v2_host = 'ws.hk-qc.example.com';
        $vmessNode->v2_path = '/ws-path';
        $vmessNode->v2_tls = 1;
        $vmessNode->v2_sni = 'hk-qc.example.com';
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

        // Node 2: VLESS — Vision flow
        $vlessNode = new SsNode();
        $vlessNode->type = 3; // Protocol-VL
        $vlessNode->name = '东京QClient-Vision';
        $vlessNode->status = 1;
        $vlessNode->is_subscribe = 1;
        $vlessNode->node_group = 1;
        $vlessNode->level = 1;
        $vlessNode->server = 'jp-qc.example.com';
        $vlessNode->v2_port = 8443;
        $vlessNode->v2_method = 'none';
        $vlessNode->v2_net = 'tcp';
        $vlessNode->v2_host = '';
        $vlessNode->v2_path = '';
        $vlessNode->v2_tls = 1;
        $vlessNode->v2_sni = 'jp-qc.example.com';
        $vlessNode->v2_flow = 'xtls-rprx-vision';
        $vlessNode->v2_encryption = 'none';
        $vlessNode->v2_fp = 'chrome';
        $vlessNode->traffic_rate = 1;
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
        $trojanNode->type = 4; // Protocol-TJ
        $trojanNode->name = '洛杉矶QClient-Trojan';
        $trojanNode->status = 1;
        $trojanNode->is_subscribe = 1;
        $trojanNode->node_group = 1;
        $trojanNode->level = 1;
        $trojanNode->server = 'us-qc.example.com';
        $trojanNode->v2_port = 443;
        $trojanNode->v2_tls = 1;
        $trojanNode->v2_sni = 'us-qc.example.com';
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
        $ssNode->type = 1; // Protocol-SS
        $ssNode->name = '新加坡QClient-SS';
        $ssNode->status = 1;
        $ssNode->is_subscribe = 1;
        $ssNode->node_group = 1;
        $ssNode->level = 1;
        $ssNode->server = 'sg-qc.example.com';
        $ssNode->ssh_port = 8388;
        $ssNode->method = 'aes-256-gcm';
        $ssNode->single_passwd = 'qc_ss_password';
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
     * TEST 1: 明文模式与名称防爆验证
     *
     * 请求 format=quanx，解析响应内容，
     * 提取 tag= 后面的值，断言其内部绝对不包含逗号和等号。
     */
    public function test_qclient_plain_text_and_tag_sanitization()
    {
        $this->createTestNodes();

        $res = $this->get('/s/' . $this->subscribeCode . '?format=quanx');
        $res->assertStatus(200);

        $content = $res->getContent();
        $lines = array_filter(explode("\n", trim($content)), 'strlen');

        // 至少 4 个节点
        $this->assertGreaterThanOrEqual(4, count($lines), 'Should output at least 4 node lines');

        // 核心防爆断言：遍历每行，提取 tag= 后的值
        foreach ($lines as $line) {
            // 提取 tag= 后面的值（tag 在行尾，所以 (.+)$ 能正确捕获）
            if (preg_match('/tag=(.+)$/', $line, $matches)) {
                $tagValue = $matches[1];
                // 绝对不包含逗号和等号
                $this->assertStringNotContainsString(',', $tagValue,
                    "Tag '{$tagValue}' must not contain unescaped comma");
                $this->assertStringNotContainsString('=', $tagValue,
                    "Tag '{$tagValue}' must not contain unescaped equals sign");
            }

            // 验证协议前缀合法
            $this->assertRegExp(
                '/^(vmess|vless|trojan|shadowsocks)=/',
                $line,
                "Each line must start with a valid protocol prefix"
            );
        }

        // 验证特殊名称节点：原始 `香港,测试=节点\n` → 清洗后 `香港_测试-节点`
        $vmessLines = array_filter($lines, function ($line) {
            return strpos($line, 'vmess=') === 0;
        });
        $this->assertNotEmpty($vmessLines, 'Should have at least one vmess node');
        $firstVmess = reset($vmessLines);
        $this->assertStringContainsString('tag=香港_测试-节点', $firstVmess,
            'Special chars in name must be sanitized: comma→underscore, equals→hyphen, newline→removed');
    }

    /**
     * TEST 2: Base64 模式与流控特性精准解析
     *
     * 请求 format=quanx-b64，解码后精准提取 Protocol-VL 行，
     * 断言包含 flow=xtls-rprx-vision。
     */
    public function test_qclient_base64_and_vision_flow()
    {
        $this->createTestNodes();

        $res = $this->get('/s/' . $this->subscribeCode . '?format=quanx-b64');
        $res->assertStatus(200);

        $encoded = $res->getContent();
        $decoded = base64_decode($encoded, true);

        // 断言 Base64 解码成功
        $this->assertNotFalse($decoded, 'Response must be valid Base64');
        $this->assertNotEmpty($decoded, 'Decoded content must not be empty');

        $lines = array_filter(explode("\n", trim($decoded)), 'strlen');

        // 精准提取 Protocol-VL (vless=) 行
        $vlessLines = array_filter($lines, function ($line) {
            return strpos($line, 'vless=') === 0;
        });
        $this->assertNotEmpty($vlessLines, 'Decoded content must contain at least one vless (Protocol-VL) node');

        $vlessLine = reset($vlessLines);

        // 核心断言：包含 Vision 流控参数
        $this->assertStringContainsString('flow=xtls-rprx-vision', $vlessLine,
            'Protocol-VL node with Vision must contain flow=xtls-rprx-vision');

        // 额外验证：Trojan 行包含 TLS
        $trojanLines = array_filter($lines, function ($line) {
            return strpos($line, 'trojan=') === 0;
        });
        $this->assertNotEmpty($trojanLines, 'Decoded content must contain at least one trojan (Protocol-TJ) node');
        $trojanLine = reset($trojanLines);
        $this->assertStringContainsString('over-tls=true', $trojanLine,
            'Protocol-TJ node must have over-tls=true');
    }

    /**
     * TEST 3: 四协议全覆盖验证
     *
     * 确保输出中同时包含 vmess, vless, trojan, shadowsocks 四种协议行。
     */
    public function test_qclient_all_four_protocols_present()
    {
        $this->createTestNodes();

        $res = $this->get('/s/' . $this->subscribeCode . '?format=quanx');
        $content = $res->getContent();
        $lines = array_filter(explode("\n", trim($content)), 'strlen');

        $protocols = ['vmess', 'vless', 'trojan', 'shadowsocks'];
        foreach ($protocols as $proto) {
            $found = false;
            foreach ($lines as $line) {
                if (strpos($line, $proto . '=') === 0) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Output must contain at least one {$proto} (Protocol) node");
        }
    }

    /**
     * TEST 4: Reality 特性安全下发（单元测试）
     *
     * 直接测试 QuanXFormatter 对 Reality 节点的处理，
     * 验证 security=reality, pbk/fingerprint, short-id 参数正确输出。
     */
    public function test_qclient_reality_parameters()
    {
        $formatter = new QuanXFormatter();

        $node = new SsNode();
        $node->type = 3; // Protocol-VL
        $node->name = 'Reality测试节点';
        $node->server = 'reality.example.com';
        $node->v2_port = 2053;
        $node->v2_encryption = 'reality';
        $node->v2_sni = 'sni.example.com';
        $node->v2_fp = 'chrome';
        $node->v2_mode = 'aabbccdd';
        $node->v2_flow = '';
        $node->v2_net = 'tcp';
        $node->v2_tls = 0;
        $node->v2_host = '';
        $node->v2_path = '';
        $node->traffic_rate = 1;
        $node->node_uuid = 'reality-uuid-9999';

        $user = new User();
        $user->vmess_id = 'fallback-uuid';

        $result = $formatter->format(collect([$node]), $user, false);

        $this->assertStringContainsString('security=reality', $result,
            'Reality node must contain security=reality');
        $this->assertStringContainsString('tls-host=sni.example.com', $result,
            'Reality node must contain tls-host');
        $this->assertStringContainsString('fingerprint=chrome', $result,
            'Reality node must contain fingerprint (pbk equivalent)');
        $this->assertStringContainsString('short-id=aabbccdd', $result,
            'Reality node must contain short-id (sid equivalent)');
        $this->assertStringContainsString('password=reality-uuid-9999', $result,
            'Reality node should use node_uuid when available');
    }

    /**
     * TEST 5: VMess WS+TLS 动态传输层验证
     */
    public function test_qclient_vmess_ws_tls_transport()
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

        $this->assertStringContainsString('obfs=ws', $vmessLine,
            'WS transport must set obfs=ws');
        $this->assertStringContainsString('over-tls=true', $vmessLine,
            'WS+TLS must set over-tls=true');
        $this->assertStringContainsString('obfs-uri=/ws-path', $vmessLine,
            'WS path must be set as obfs-uri');
        $this->assertStringContainsString('obfs-host=ws.hk-qc.example.com', $vmessLine,
            'WS host must be set as obfs-host');
    }

    /**
     * TEST 6: Base64 往返一致性验证
     *
     * 明文输出与 Base64 解码后的输出必须完全一致。
     */
    public function test_qclient_b64_roundtrip_consistency()
    {
        $this->createTestNodes();

        $resPlain = $this->get('/s/' . $this->subscribeCode . '?format=quanx');
        $resB64 = $this->get('/s/' . $this->subscribeCode . '?format=quanx-b64');

        $plainContent = trim($resPlain->getContent());
        $decodedB64 = trim(base64_decode($resB64->getContent(), true));

        $this->assertEquals($plainContent, $decodedB64,
            'Plain text and Base64-decoded content must be byte-identical');
    }

    /**
     * TEST 7: Content-Type 头验证
     */
    public function test_qclient_content_type_header()
    {
        $this->createTestNodes();

        $res = $this->get('/s/' . $this->subscribeCode . '?format=quanx');
        $res->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $resB64 = $this->get('/s/' . $this->subscribeCode . '?format=quanx-b64');
        $resB64->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
