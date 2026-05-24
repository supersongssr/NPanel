<?php

namespace Tests\Feature;

use App\Http\Models\SsNode;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminNodeListIsCloneFilterTest extends TestCase
{
    /**
     * 测试节点列表的 is_clone 过滤功能
     *
     * is_clone=0: 原始节点
     * is_clone=1: 克隆节点(is_clone > 0)
     *
     * @return void
     */
    public function testNodeListIsCloneFilter()
    {
        // 检查是否在测试环境
        if (config('app.env') !== 'test') {
            $this->assertTrue(true);
            return;
        }

        // 创建测试数据
        SsNode::query()->delete(); // 清理现有数据

        // 创建不同 is_clone 状态的节点
        $originalNode = SsNode::create([
            'name' => 'Original Node Test',
            'type' => 1,
            'is_clone' => 0,  // 原始节点
            'status' => 1,
            'level' => 0,
            'node_group' => 1,
            'sort' => 0,
        ]);

        $cloneNode = SsNode::create([
            'name' => 'Clone Node Test',
            'type' => 1,
            'is_clone' => $originalNode->id,  // 克隆节点保存父节点ID
            'status' => 1,
            'level' => 0,
            'node_group' => 1,
            'sort' => 0,
        ]);

        $otherParentNode = SsNode::create([
            'name' => 'Other Parent Node Test',
            'type' => 1,
            'is_clone' => 0,
            'status' => 1,
            'level' => 0,
            'node_group' => 1,
            'sort' => 0,
        ]);

        $otherCloneNode = SsNode::create([
            'name' => 'Other Clone Node Test',
            'type' => 1,
            'is_clone' => $otherParentNode->id,
            'status' => 1,
            'level' => 0,
            'node_group' => 1,
            'sort' => 0,
        ]);

        // 测试1: 不过滤，应该显示所有节点
        $responseAll = $this->get('/admin/nodeList');
        $this->assertEquals(200, $responseAll->getStatusCode());
        $responseAll->assertSee('Original Node Test');
        $responseAll->assertSee('Clone Node Test');
        $responseAll->assertSee('Other Parent Node Test');
        $responseAll->assertSee('Other Clone Node Test');

        // 测试2: 过滤 is_clone=0 (只显示原始节点)
        $responseOriginalOnly = $this->get('/admin/nodeList?is_clone=0');
        $this->assertEquals(200, $responseOriginalOnly->getStatusCode());
        $responseOriginalOnly->assertSee('Original Node Test');
        $responseOriginalOnly->assertSee('Other Parent Node Test');
        $responseOriginalOnly->assertDontSee('Clone Node Test');
        $responseOriginalOnly->assertDontSee('Other Clone Node Test');

        // 测试3: 过滤 is_clone=1 (只显示全部克隆节点)
        $responseCloneOnly = $this->get('/admin/nodeList?is_clone=1');
        $this->assertEquals(200, $responseCloneOnly->getStatusCode());
        $responseCloneOnly->assertDontSee('Original Node Test');
        $responseCloneOnly->assertDontSee('Other Parent Node Test');
        $responseCloneOnly->assertSee('Clone Node Test');
        $responseCloneOnly->assertSee('Other Clone Node Test');

        // 测试4: is_clone=空字符串 (不过滤，显示所有节点)
        $responseEmpty = $this->get('/admin/nodeList?is_clone=');
        $this->assertEquals(200, $responseEmpty->getStatusCode());
        $responseEmpty->assertSee('Original Node Test');
        $responseEmpty->assertSee('Clone Node Test');

        // 清理测试数据
        SsNode::query()->whereIn('name', [
            'Original Node Test',
            'Clone Node Test',
            'Other Parent Node Test',
            'Other Clone Node Test',
        ])->delete();

        $this->assertTrue(true);
    }

    /**
     * 测试 is_clone 参数的不同值
     *
     * @return void
     */
    public function testIsCloneParameterValues()
    {
        // 检查是否在测试环境
        if (config('app.env') !== 'test') {
            $this->assertTrue(true);
            return;
        }

        // 创建测试节点
        SsNode::query()->delete();

        $originalNode = SsNode::create([
            'name' => 'Test Original Node',
            'type' => 1,
            'is_clone' => 0,  // 原始节点
            'status' => 1,
            'level' => 0,
            'node_group' => 1,
            'sort' => 0,
        ]);

        $cloneNode = SsNode::create([
            'name' => 'Test Clone Node',
            'type' => 1,
            'is_clone' => $originalNode->id,  // 克隆节点保存父节点ID
            'status' => 1,
            'level' => 0,
            'node_group' => 1,
            'sort' => 0,
        ]);

        // 验证数据库中的节点
        $originalNodes = SsNode::where('is_clone', 0)->get();
        $cloneNodes = SsNode::where('is_clone', '>', 0)->get();

        $this->assertEquals(1, $originalNodes->count());
        $this->assertEquals(1, $cloneNodes->count());
        $this->assertEquals('Test Original Node', $originalNodes->first()->name);
        $this->assertEquals('Test Clone Node', $cloneNodes->first()->name);

        // 清理测试数据
        SsNode::query()->whereIn('name', ['Test Original Node', 'Test Clone Node'])->delete();

        $this->assertTrue(true);
    }

    /**
     * 测试红色异常心跳筛选会忽略冲突 status 参数并按心跳倒序显示
     *
     * @return void
     */
    public function testHeartbeatRecentFilterIgnoresConflictingStatusAndSortsByHeartbeat()
    {
        if (config('app.env') !== 'test') {
            $this->assertTrue(true);
            return;
        }

        SsNode::query()->delete();

        SsNode::create([
            'name' => 'Recent Red Older',
            'type' => 1,
            'is_clone' => 0,
            'status' => 0,
            'level' => 0,
            'node_group' => 1,
            'sort' => 999,
            'heartbeat_at' => \Carbon\Carbon::now()->subHours(6)->toDateTimeString(),
        ]);

        SsNode::create([
            'name' => 'Recent Red Newer',
            'type' => 1,
            'is_clone' => 0,
            'status' => 0,
            'level' => 0,
            'node_group' => 1,
            'sort' => 1,
            'heartbeat_at' => \Carbon\Carbon::now()->subHour()->toDateTimeString(),
        ]);

        SsNode::create([
            'name' => 'Normal Status Node',
            'type' => 1,
            'is_clone' => 0,
            'status' => 1,
            'level' => 0,
            'node_group' => 1,
            'sort' => 0,
            'heartbeat_at' => \Carbon\Carbon::now()->subHour()->toDateTimeString(),
        ]);

        $response = $this->get('/admin/nodeList?heartbeat_recent=1&status=1');
        $this->assertEquals(200, $response->getStatusCode());
        $response->assertSee('Recent Red Newer');
        $response->assertSee('Recent Red Older');
        $response->assertDontSee('Normal Status Node');

        $content = $response->getContent();
        $this->assertTrue(
            strpos($content, 'Recent Red Newer') < strpos($content, 'Recent Red Older'),
            '红色异常节点应该按 heartbeat_at 倒序显示'
        );

        SsNode::query()->whereIn('name', [
            'Recent Red Older',
            'Recent Red Newer',
            'Normal Status Node',
        ])->delete();
    }
}
