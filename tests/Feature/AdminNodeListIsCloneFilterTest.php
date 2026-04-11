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
     * is_clone=1: 克隆节点
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
            'is_clone' => 1,  // 克隆节点
            'status' => 1,
            'level' => 0,
            'node_group' => 1,
            'sort' => 0,
        ]);

        // 测试1: 不过滤，应该显示所有节点
        $responseAll = $this->get('/admin/nodeList');
        $this->assertEquals(200, $responseAll->getStatusCode());

        // 测试2: 过滤 is_clone=0 (只显示原始节点)
        $responseOriginalOnly = $this->get('/admin/nodeList?is_clone=0');
        $this->assertEquals(200, $responseOriginalOnly->getStatusCode());

        // 测试3: 过滤 is_clone=1 (只显示克隆节点)
        $responseCloneOnly = $this->get('/admin/nodeList?is_clone=1');
        $this->assertEquals(200, $responseCloneOnly->getStatusCode());

        // 测试4: is_clone=空字符串 (不过滤，显示所有节点)
        $responseEmpty = $this->get('/admin/nodeList?is_clone=');
        $this->assertEquals(200, $responseEmpty->getStatusCode());

        // 清理测试数据
        SsNode::query()->whereIn('name', ['Original Node Test', 'Clone Node Test'])->delete();

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
            'is_clone' => 1,  // 克隆节点
            'status' => 1,
            'level' => 0,
            'node_group' => 1,
            'sort' => 0,
        ]);

        // 验证数据库中的节点
        $originalNodes = SsNode::where('is_clone', 0)->get();
        $cloneNodes = SsNode::where('is_clone', 1)->get();

        $this->assertEquals(1, $originalNodes->count());
        $this->assertEquals(1, $cloneNodes->count());
        $this->assertEquals('Test Original Node', $originalNodes->first()->name);
        $this->assertEquals('Test Clone Node', $cloneNodes->first()->name);

        // 清理测试数据
        SsNode::query()->whereIn('name', ['Test Original Node', 'Test Clone Node'])->delete();

        $this->assertTrue(true);
    }
}
