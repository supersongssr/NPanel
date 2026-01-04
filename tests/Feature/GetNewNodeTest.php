<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Models\SsNode;

/**
 * 测试 getNewNode API 的自动创建节点功能
 */
class GetNewNodeTest extends TestCase
{
    use RefreshDatabase;

    private $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        // 从.env读取API_TOKEN
        if (file_exists(base_path('.env'))) {
            $content = file_get_contents(base_path('.env'));
            if (preg_match('/API_TOKEN=(.+)/', $content, $matches)) {
                $this->apiToken = trim($matches[1]);
            }
        }

        $this->apiToken = $this->apiToken ?? env('API_TOKEN', 'test_token_123456');

        if (empty($this->apiToken) || $this->apiToken === 'test_token_123456') {
            $this->markTestSkipped('API_TOKEN not configured properly');
        }
    }

    /**
     * 测试getNewNode API - 缺少token
     */
    public function test_get_new_node_without_token()
    {
        $response = $this->getJson('/api/node/new');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Invalid token'
                ]);
    }

    /**
     * 测试getNewNode API - 无效token
     */
    public function test_get_new_node_with_invalid_token()
    {
        $response = $this->getJson('/api/node/new?token=invalid_token_xyz');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Invalid token'
                ]);
    }

    /**
     * 测试getNewNode API - 有效token，无可用节点时自动创建
     */
    public function test_get_new_node_auto_create_when_no_available()
    {
        // 确保没有可用的节点
        SsNode::query()->delete();

        // 调用API
        $response = $this->getJson("/api/node/new?token={$this->apiToken}");

        // 验证响应
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Node found'
                ])
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'node_id',
                        'v2_host'
                    ]
                ]);

        // 验证节点已创建
        $nodeId = $response->json('data.node_id');
        $this->assertIsInt($nodeId);
        $this->assertGreaterThan(0, $nodeId);

        // 验证数据库中存在该节点
        $node = SsNode::find($nodeId);
        $this->assertNotNull($node, 'Node should be created in database');
        $this->assertEquals(0, $node->status, 'New node should have status 0 (maintenance)');
        $this->assertStringContainsString('自动创建节点', $node->name, 'Node name should contain "自动创建节点"');
    }

    /**
     * 测试getNewNode API - 有可用节点时复用现有节点
     */
    public function test_get_new_node_reuse_existing_available_node()
    {
        // 创建一个符合条件的可用节点
        // 条件: id > 9, heartbeat_at < 7天前, status = 0
        $oldNode = new SsNode();
        $oldNode->id = 100;
        $oldNode->name = 'Test Old Node';
        $oldNode->status = 0;
        $oldNode->type = 2;
        $oldNode->level = 0;
        $oldNode->group_id = 1;
        $oldNode->country_code = 'un';
        $oldNode->method = 'aes-256-cfb';
        $oldNode->protocol = 'origin';
        $oldNode->obfs = 'plain';
        $oldNode->traffic_rate = 1;
        $oldNode->bandwidth = 1000;
        $oldNode->traffic_limit = 1000 * 1024 * 1024 * 1024;
        $oldNode->is_subscribe = 0;
        $oldNode->is_nat = 0;
        $oldNode->node_speedlimit = 0;
        $oldNode->node_online = 0;
        $oldNode->node_heartbeat = 0;
        $oldNode->sort = 0;
        $oldNode->node_class = 0;
        $oldNode->heartbeat_at = date('Y-m-d H:i:s', time() - 604800 - 100); // 7天多前
        $oldNode->save();

        // 调用API
        $response = $this->getJson("/api/node/new?token={$this->apiToken}");

        // 验证返回的是现有节点
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success'
                ]);

        $nodeId = $response->json('data.node_id');
        $this->assertEquals(100, $nodeId, 'Should return existing available node');

        // 验证心跳已更新
        $updatedNode = SsNode::find(100);
        $this->assertNotNull($updatedNode);
        $heartbeatDiff = strtotime($updatedNode->heartbeat_at) - time();
        $this->assertLessThan(10, abs($heartbeatDiff), 'Heartbeat should be updated to current time');
    }

    /**
     * 测试getNewNode API - 多次调用时的行为
     */
    public function test_get_new_node_multiple_calls()
    {
        // 清空所有节点
        SsNode::query()->delete();

        // 第一次调用 - 应该创建新节点
        $response1 = $this->getJson("/api/node/new?token={$this->apiToken}");
        $nodeId1 = $response1->json('data.node_id');

        $response1->assertStatus(200)
                 ->assertJson(['status' => 'success']);

        $this->assertNotNull($nodeId1);

        // 第二次调用 - 应该返回同一个节点（因为刚更新过心跳，不符合7天前的条件）
        // 但实际上会创建新节点，因为第一个节点的心跳刚刚更新
        $response2 = $this->getJson("/api/node/new?token={$this->apiToken}");
        $nodeId2 = $response2->json('data.node_id');

        $response2->assertStatus(200)
                 ->assertJson(['status' => 'success']);

        // 由于第一个节点的心跳刚更新，不符合"7天前"的条件
        // 所以应该创建/返回另一个节点
        $this->assertNotNull($nodeId2);

        // 验证数据库中至少有一个节点
        $nodeCount = SsNode::count();
        $this->assertGreaterThanOrEqual(1, $nodeCount);
    }

    /**
     * 测试getNewNode API - 新创建节点的字段默认值
     */
    public function test_get_new_node_default_values()
    {
        // 清空所有节点
        SsNode::query()->delete();

        // 调用API创建节点
        $response = $this->getJson("/api/node/new?token={$this->apiToken}");
        $nodeId = $response->json('data.node_id');

        // 从数据库获取节点
        $node = SsNode::find($nodeId);
        $this->assertNotNull($node);

        // 验证默认值
        $this->assertEquals(2, $node->type, 'Type should be 2 (vmess)');
        $this->assertEquals(0, $node->status, 'Status should be 0 (maintenance)');
        $this->assertEquals(0, $node->level, 'Level should be 0');
        $this->assertEquals(1, $node->group_id, 'Group ID should be 1');
        $this->assertEquals('un', $node->country_code, 'Country code should be "un"');
        $this->assertEquals('aes-256-cfb', $node->method, 'Method should be aes-256-cfb');
        $this->assertEquals('origin', $node->protocol, 'Protocol should be origin');
        $this->assertEquals('plain', $node->obfs, 'Obfs should be plain');
        $this->assertEquals(1, $node->traffic_rate, 'Traffic rate should be 1');
        $this->assertEquals(1000, $node->bandwidth, 'Bandwidth should be 1000');
        $this->assertEquals(1000 * 1024 * 1024 * 1024, $node->traffic_limit, 'Traffic limit should be 1000GB');
        $this->assertEquals(0, $node->is_subscribe, 'is_subscribe should be 0');
        $this->assertEquals(0, $node->is_nat, 'is_nat should be 0');
        $this->assertEquals(0, $node->node_speedlimit, 'node_speedlimit should be 0');
        $this->assertEquals(0, $node->sort, 'sort should be 0');
        $this->assertEquals(0, $node->node_class, 'node_class should be 0');

        // 验证名称格式
        $this->assertMatchesRegularExpression('/^自动创建节点-' . $nodeId . '$/', $node->name,
            'Node name should be in format "自动创建节点-{ID}"');
    }

    /**
     * 测试getNewNode API - 节点ID的自增性
     */
    public function test_get_new_node_auto_increment_id()
    {
        // 清空所有节点
        SsNode::query()->delete();

        // 创建多个节点
        $nodeIds = [];
        for ($i = 0; $i < 3; $i++) {
            $response = $this->getJson("/api/node/new?token={$this->apiToken}");
            $nodeId = $response->json('data.node_id');
            $nodeIds[] = $nodeId;

            $response->assertStatus(200)
                     ->assertJson(['status' => 'success']);
        }

        // 验证ID是递增的
        $this->assertCount(3, $nodeIds);
        $this->assertLessThan($nodeIds[1], $nodeIds[0]);
        $this->assertLessThan($nodeIds[2], $nodeIds[1]);
    }
}
