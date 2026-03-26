# NPanel Development Guidelines


## 环境说明

> 📌 **详细配置**: 项目特定的环境配置、容器信息、测试规范等信息已移至 `.claude/PROJECT_CONTEXT.md` 文件中,该文件不会被提交到公共仓库。

### 基本信息
- PHP 运行环境: Podman 容器
- 测试目录: `tests/`
- 环境配置文件: `.claude/ENVIRONMENT.md`
- 项目上下文: `.claude/PROJECT_CONTEXT.md`

### Testing
测试文件位于 `tests/` 文件夹。

**重要约束**:
- `tests/` 文件夹的脚本必须在 `.env` 文件中设置 `APP_ENV=test` 才能运行
- 编写测试代码后应该自动运行脚本并调试
- 详细的测试规范请参考 `.claude/PROJECT_CONTEXT.md`

## Developing 

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




## 命令与语法约束
```where```: 修改的文件的位置 或 模块位置
```why```: 为什么修改
```how```: 如何修改代码

```input```: 模块接受哪些数据
```output```: 模块应该输出什么数据
```do```: 模块做哪些任务和工作

```must```:  模块收到哪些 约束 , 必须遵守的条件
