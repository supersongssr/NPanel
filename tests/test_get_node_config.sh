#!/bin/bash

# 测试获取节点配置API
# 使用方法: ./test_get_node_config.sh

API_BASE_URL="http://your-domain.com/api"
API_TOKEN="your_api_token_here"

echo "=== 测试获取节点配置API ==="

# 测试1: 缺少node_id参数
echo "测试1: 缺少node_id参数"
curl -X GET "${API_BASE_URL}/node_config?token=${API_TOKEN}"
echo -e "\n"

# 测试2: 无效的token
echo "测试2: 无效的token"
curl -X GET "${API_BASE_URL}/node_config?token=invalid_token&node_id=1"
echo -e "\n"

# 测试3: 不存在的节点ID
echo "测试3: 不存在的节点ID"
curl -X GET "${API_BASE_URL}/node_config?token=${API_TOKEN}&node_id=99999"
echo -e "\n"

# 测试4: 正确的请求 (假设节点ID为1存在)
echo "测试4: 正确的请求"
curl -X GET "${API_BASE_URL}/node_config?token=${API_TOKEN}&node_id=1"
echo -e "\n"

echo "=== 测试完成 ==="