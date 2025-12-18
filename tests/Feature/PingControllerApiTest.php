<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Env;

class PingControllerApiTest extends TestCase
{
    use RefreshDatabase;

    private $apiToken;
    private $testApiUrl;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 从根目录.env文件读取配置
        if (file_exists(base_path('.env'))) {
            $content = file_get_contents(base_path('.env'));
            if (preg_match('/API_TOKEN=(.+)/', $content, $matches)) {
                $this->apiToken = $matches[1];
            }
            if (preg_match('/TEST_API_URL=(.+)/', $content, $matches)) {
                $this->testApiUrl = $matches[1];
            }
        }
        
        // 默认值
        $this->apiToken = $this->apiToken ?? env('API_TOKEN', 'test_token_123456');
        $this->testApiUrl = $this->testApiUrl ?? env('TEST_API_URL', 'http://localhost/api');
        
        // 记录配置（用于调试）
        if (empty($this->apiToken)) {
            $this->markTestSkipped('API_TOKEN not found in .env file');
        }
    }

    /**
     * 测试ping API - 无token
     */
    public function test_ping_api_without_token()
    {
        $response = $this->getJson('/api/ping?host=www.baidu.com');
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 0,
                    'message' => 'token invalid'
                ]);
    }

    /**
     * 测试ping API - 有效token但缺少host参数
     */
    public function test_ping_api_without_host()
    {
        $response = $this->getJson("/api/ping?token={$this->apiToken}");
        
        // 应该返回使用说明HTML
        $response->assertStatus(200);
        $this->assertStringContainsString('使用方法', $response->getContent());
    }

    /**
     * 测试ping API - 完整参数测试
     */
    public function test_ping_api_with_valid_params()
    {
        $response = $this->getJson("/api/ping?token={$this->apiToken}&host=www.baidu.com&port=80&transport=tcp&timeout=1");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message'
                ]);
        
        // 状态应该是0或1
        $this->assertContains($response->json('status'), [0, 1]);
    }

    /**
     * 测试ping API - IPv4地址
     */
    public function test_ping_api_with_ipv4()
    {
        $response = $this->getJson("/api/ping?token={$this->apiToken}&host=8.8.8.8&port=53");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message'
                ]);
    }

    /**
     * 测试simpleApiTools API - 缺少token
     */
    public function test_simple_api_tools_without_token()
    {
        $response = $this->getJson('/api/simpleApiTools');
        
        $response->assertStatus(200)
                ->assertJson([
                    'err' => 'token-empty'
                ]);
    }

    /**
     * 测试simpleApiTools API - 无效token
     */
    public function test_simple_api_tools_invalid_token()
    {
        $response = $this->getJson('/api/simpleApiTools?token=invalid&salt=test');
        
        $response->assertStatus(200)
                ->assertJson([
                    'err' => 'token-invalid'
                ]);
    }

    /**
     * 测试simpleApiTools API - 有效token和参数
     */
    public function test_simple_api_tools_with_valid_token()
    {
        $salt = 'test_salt';
        $validToken = md5($this->apiToken . $salt);
        
        $response = $this->getJson("/api/simpleApiTools?token={$validToken}&salt={$salt}&ip=true&time=true");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'ip',
                    'time'
                ]);
    }

    /**
     * 测试getNodeConfig API - 缺少token
     */
    public function test_get_node_config_without_token()
    {
        $response = $this->getJson('/api/node_config?node_id=1');
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Invalid token'
                ]);
    }

    /**
     * 测试getNodeConfig API - 缺少node_id
     */
    public function test_get_node_config_without_node_id()
    {
        $response = $this->getJson("/api/node_config?token={$this->apiToken}");
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'node_id is required'
                ]);
    }

    /**
     * 测试getNodeConfig API - 不存在的节点
     */
    public function test_get_node_config_nonexistent_node()
    {
        $response = $this->getJson("/api/node_config?token={$this->apiToken}&node_id=99999");
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Node not found'
                ]);
    }

    /**
     * 测试ssn_sub API - 无效token
     */
    public function test_ssn_sub_invalid_token()
    {
        $response = $this->postJson("/api/ssn_sub/1?token=invalid_token");
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Invalid token'
                ]);
    }

    /**
     * 测试ssn_v2 API - 无效token
     */
    public function test_ssn_v2_invalid_token()
    {
        $response = $this->postJson("/api/ssn_v2/1?token=invalid_token");
        
        // 该接口使用exit()返回空响应
        $response->assertStatus(200);
    }
}