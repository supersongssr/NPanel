已按照 plan-v2.md 完成API v2开发：
完成的功能
1. API v2控制器 (app/Http/Controllers/Api/V2/NodeController.php)
   - getAvailableNodeId() - 获取超过30天无心跳的节点ID
   - reportConfig() - 节点配置信息上报
   - reportDaily() - 节点日常信息上报
2. 路由配置 (routes/api.php)
   - GET /api/v2/node/available - 获取可用节点ID
   - POST /api/v2/node/{id}/config - 配置上报
   - POST /api/v2/node/{id}/daily - 日常信息上报
3. 测试脚本 (.tests/test_api_v2.sh)
   - 完整的API测试脚本
   - 包含正常流程和错误处理测试
   - 使用说明文档 (.tests/README_api_v2.md)
API特点
- 统一的JSON响应格式
- 完整的错误处理和日志记录
- Token验证机制
- 兼容现有数据结构
- 支持V2协议所有配置项
测试脚本可直接在测试环境使用：./.tests/test_api_v2.sh http://test-npanel-v2.freessr.bid your_token
▣  Build · glm-4.6 · 2m 52s