#!/bin/bash

# API自动化测试脚本
# 测试PingController的各个API端点
# 使用方法: ./test_ping_api.sh

# 优先从.env.test_local读取配置，然后是.env.testing，最后是.env文件
if [ -f ".env.test_local" ]; then
    export $(grep -v '^#' .env.test_local | xargs)
elif [ -f ".env.testing" ]; then
    export $(grep -v '^#' .env.testing | xargs)
elif [ -f ".env" ]; then
    export $(grep -v '^#' .env | xargs)
fi

# 设置默认值
API_TOKEN=${API_TOKEN:-"test_token"}
TEST_API_URL=${TEST_API_URL:-"http://localhost/api"}

echo "=== NPanel API 自动化测试 ==="
echo "测试URL: ${TEST_API_URL}"
echo "Token: ${API_TOKEN:0:10}..."
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
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

# 1. 测试ping API - 缺少host参数
test_api "ping API - 缺少host参数" \
    "${TEST_API_URL}/ping?token=${API_TOKEN}" \
    "200" \
    "使用方法"

# 2. 测试ping API - 无效token
test_api "ping API - 无效token" \
    "${TEST_API_URL}/ping?token=invalid_token&host=www.baidu.com" \
    "200" \
    "token invalid"

# 3. 测试ping API - 有效的IPv4地址
test_api "ping API - 有效的IPv4地址" \
    "${TEST_API_URL}/ping?token=${API_TOKEN}&host=8.8.8.8&port=53" \
    "200" \
    '"status":'

# 4. 测试ping API - 有效的域名
test_api "ping API - 有效的域名" \
    "${TEST_API_URL}/ping?token=${API_TOKEN}&host=www.baidu.com&port=80" \
    "200" \
    '"status":'

# 5. 测试simpleApiTools API - 缺少token
test_api "simpleApiTools API - 缺少token" \
    "${TEST_API_URL}/simpleApiTools" \
    "200" \
    "token-empty"

# 6. 测试simpleApiTools API - 无效token
test_api "simpleApiTools API - 无效token" \
    "${TEST_API_URL}/simpleApiTools?token=invalid&salt=test" \
    "200" \
    "token-invalid"

# 7. 测试simpleApiTools API - 获取IP和时间
salt="test_salt_$(date +%s)"
valid_token=$(echo -n "${API_TOKEN}${salt}" | md5sum | cut -d' ' -f1)
test_api "simpleApiTools API - 获取IP和时间" \
    "${TEST_API_URL}/simpleApiTools?token=${valid_token}&salt=${salt}&ip=true&time=true" \
    "200" \
    '"ip":|"time":'

# 8. 测试getNodeConfig API - 缺少token
test_api "getNodeConfig API - 缺少token" \
    "${TEST_API_URL}/node_config?node_id=1" \
    "200" \
    "Invalid token"

# 9. 测试getNodeConfig API - 缺少node_id
test_api "getNodeConfig API - 缺少node_id" \
    "${TEST_API_URL}/node_config?token=${API_TOKEN}" \
    "200" \
    "node_id is required"

# 10. 测试getNodeConfig API - 不存在的节点
test_api "getNodeConfig API - 不存在的节点" \
    "${TEST_API_URL}/node_config?token=${API_TOKEN}&node_id=99999" \
    "200" \
    "Node not found"

# 11. 测试ssn_sub API - 无效token
test_api "ssn_sub API - 无效token" \
    "${TEST_API_URL}/ssn_sub/1?token=invalid_token" \
    "200" \
    "Invalid token"

# 12. 测试ssn_v2 API - 无效token
test_api "ssn_v2 API - 无效token" \
    "${TEST_API_URL}/ssn_v2/1?token=invalid_token" \
    "200" \
    ""

echo "=== 测试完成 ==="
echo ""
echo "注意事项："
echo "1. 确保.env文件中配置了正确的API_TOKEN和TEST_API_URL"
echo "2. 部分测试可能需要数据库中有对应的节点数据"
echo "3. 绿色✓表示测试通过，红色✗表示测试失败"