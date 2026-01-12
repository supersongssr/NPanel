#!/bin/bash

# API自动化测试脚本
# 测试PingController的getNewNode API端点 - 自动创建节点功能
# 使用方法: ./tests/test_get_new_node.sh

# 导入环境变量
if [ -f ".env" ]; then
    export $(grep -v '^#' .env | grep -E '^(APP_ENV|API_TOKEN)' | xargs)
fi

# 检查环境变量
if [ "$APP_ENV" != "test" ]; then
    echo "错误: 测试必须在测试环境中运行"
    echo "请确保 .env 文件中 APP_ENV=test"
    echo "当前 APP_ENV=$APP_ENV"
    exit 1
fi

# 读取.env配置
if [ -f ".env" ]; then
    export $(grep -v '^#' .env | grep API_TOKEN | xargs)
fi

# 设置默认值
API_TOKEN=${API_TOKEN:-"hahajimiddga"}
TEST_API_URL=${TEST_API_URL:-"https://test-npanel.freessr.bid/api"}

echo "=== NPanel getNewNode API 自动化测试 ==="
echo "测试URL: ${TEST_API_URL}"
echo "Token: ${API_TOKEN:0:10}..."
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 测试函数
test_api() {
    local test_name="$1"
    local url="$2"
    local expected_status="$3"
    local expected_pattern="$4"

    echo -e "${YELLOW}测试: ${test_name}${NC}"
    echo "请求: ${url}"

    response=$(curl -s -w "HTTPSTATUS:%{http_code}" "${url}")
    http_code=$(echo $response | tr -d '\n' | sed -e 's/.*HTTPSTATUS://')
    response_body=$(echo $response | sed -e 's/HTTPSTATUS:.*//g')

    echo "响应码: ${http_code}"
    echo "响应内容: ${response_body}"

    # 检查HTTP状态码
    if [ "$http_code" = "$expected_status" ]; then
        echo -e "${GREEN}✓ 状态码正确${NC}"
    else
        echo -e "${RED}✗ 状态码错误，期望: ${expected_status}, 实际: ${http_code}${NC}"
    fi

    # 检查响应内容模式
    if [ ! -z "$expected_pattern" ]; then
        if echo "$response_body" | grep -q "$expected_pattern"; then
            echo -e "${GREEN}✓ 响应内容符合预期${NC}"
        else
            echo -e "${RED}✗ 响应内容不符合预期，应包含: ${expected_pattern}${NC}"
        fi
    fi

    echo "----------------------------------------"
    echo ""
}

# 提取node_id的函数
extract_node_id() {
    local response="$1"
    echo "$response" | grep -o '"node_id":[0-9]*' | head -1 | grep -o '[0-9]*$'
}

echo -e "${BLUE}=== 第一部分: 基础功能测试 ===${NC}"
echo ""

# 1. 测试getNewNode API - 缺少token
test_api "getNewNode API - 缺少token" \
    "${TEST_API_URL}/node/new" \
    "200" \
    "Invalid token"

# 2. 测试getNewNode API - 无效token
test_api "getNewNode API - 无效token" \
    "${TEST_API_URL}/node/new?token=invalid_token" \
    "200" \
    "Invalid token"

# 3. 测试getNewNode API - 有效token，获取或创建节点
echo -e "${BLUE}=== 第二部分: 节点获取/创建测试 ===${NC}"
echo ""

response=$(curl -s "${TEST_API_URL}/node/new?token=${API_TOKEN}")
echo -e "${YELLOW}测试: getNewNode API - 有效token，获取或创建节点${NC}"
echo "请求: ${TEST_API_URL}/node/new?token=${API_TOKEN}"
echo "响应内容: ${response}"

# 检查响应
if echo "$response" | grep -q '"status":"success"'; then
    echo -e "${GREEN}✓ API调用成功${NC}"

    if echo "$response" | grep -q '"node_id":'; then
        node_id=$(extract_node_id "$response")
        echo -e "${GREEN}✓ 成功获取节点ID: ${node_id}${NC}"

        # 检查是否是新创建的节点（名称包含"自动创建节点"）
        if echo "$response" | grep -q '"v2_host":null'; then
            echo -e "${BLUE}ℹ 这可能是新创建的节点（v2_host为空）${NC}"
        fi
    else
        echo -e "${RED}✗ 响应中没有node_id${NC}"
    fi
else
    echo -e "${RED}✗ API调用失败${NC}"
fi
echo "----------------------------------------"
echo ""

# 4. 再次调用，应该获取到同一个节点（如果它没有被标记为使用中）
echo -e "${BLUE}=== 第三部分: 多次调用测试 ===${NC}"
echo ""

for i in 1 2 3; do
    response=$(curl -s "${TEST_API_URL}/node/new?token=${API_TOKEN}")
    node_id=$(extract_node_id "$response")
    echo -e "${YELLOW}第${i}次调用 - 节点ID: ${node_id}${NC}"
    sleep 1
done
echo ""

# 5. 检查节点是否真正创建在数据库中
echo -e "${BLUE}=== 第四部分: 数据库验证 ===${NC}"
echo ""

echo -e "${YELLOW}提示: 请手动验证数据库中是否创建了新节点${NC}"
echo "可以运行以下命令检查:"
echo "podman exec php7-npanel php artisan tinker"
echo ">>> App\Http\Models\SsNode::where('name', 'like', '自动创建节点%')->get(['id', 'name', 'status', 'heartbeat_at']);"
echo ""

echo "=== 测试完成 ==="
echo ""
echo -e "${BLUE}注意事项：${NC}"
echo "1. 确保.env文件中 APP_ENV=test"
echo "2. 确保.env文件中配置了正确的API_TOKEN"
echo "3. 绿色✓表示测试通过，红色✗表示测试失败"
echo "4. 当没有可用节点时，系统会自动创建新节点"
echo "5. 新创建的节点名称格式: 自动创建节点-{ID}"
