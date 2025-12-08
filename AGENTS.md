# NPanel Development Guidelines

使用中文交流

## Build/Lint/Test Commands
本地为 macos 环境,无法测试.
测试环境在 ```ssh root@test.srpv2``` 服务器
目录是: /www/wwwroot/test-npanel-v2.freessr.bid

请将测试脚本 在测试后放入到 .tests/ 文件夹

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


