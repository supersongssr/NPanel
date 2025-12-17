# NPanel Development Guidelines

请使用中文回答

##  Test  
### Test 测试
本地环境 **不是真实运行环境**
最终测试与验证发生在远程服务器
- 远程服务器信息如下（固定假设）：
  - 服务器地址：`root@test`
  - 目标目录：`/www/wwwroot/Npanel/`
  - 文件同步工具：`rsync`

当你认为代码与测试逻辑已经完成,**必须执行以下步骤**：
- 将你改动的文件 上传到 远程服务器
- 在远程服务器运行测试, 如果测试报错,  请修改代码 ->> 上传远程服务器 ->> 运行测试代码 , 直至 代码测试通过.

## Code Style Guidelines

### PHP/Laravel Conventions
- Use PSR-4 autoloading with `App\` namespace
- Controllers in `app/Http/Controllers/` with descriptive names
- Models in `app/Http/Models/` (non-standard location)
- Use Laravel's built-in validation and request handling
- Follow Laravel naming conventions: snake_case for variables, camelCase for methods

### Import Organization
- Group imports: Laravel framework first, then third-party, then app-specific
- Use fully qualified class names where appropriate
- Avoid unused imports

### Error Handling
- Use Laravel's validation system for form validation
- Implement proper exception handling in controllers
- Log errors using `Log::` facade
- Return proper HTTP status codes and error messages

### Security
- Always hash passwords using `Hash::make()`
- Validate all user input
- Use CSRF protection
- Sanitize user-generated content
- Never commit sensitive data to repository


