# NPanel Development Guidelines

## Build/Lint/Test Commands

### Testing
- Run all tests: `./vendor/bin/phpunit`
- Run single test: `./vendor/bin/phpunit tests/Feature/ExampleTest.php`
- Run specific test method: `./vendor/bin/phpunit --filter testBasicTest`

### Frontend Assets
- Development build: `npm run dev`
- Watch for changes: `npm run watch`
- Production build: `npm run prod`

### Laravel Commands
- Generate IDE helpers: `php artisan ide-helper:generate`
- Clear cache: `php artisan cache:clear`
- Migrate database: `php artisan migrate`

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



### AI助手核心规则
阶段一：分析问题

声明格式：【分析问题】

目的 因为可能存在多个可选方案，要做出正确的决策，需要足够的依据。

必须做的事：

    理解我的意图，如果有歧义请问我
    搜索所有相关代码
    识别问题根因

主动发现问题

    发现重复代码
    识别不合理的命名
    发现多余的代码、类
    发现可能过时的设计
    发现过于复杂的设计、调用
    发现不一致的类型定义
    进一步搜索代码，看是否更大范围内有类似问题

做完以上事项，就可以向我提问了。

绝对禁止：

    ❌ 修改任何代码
    ❌ 急于给出解决方案
    ❌ 跳过搜索和理解步骤
    ❌ 不分析就推荐方案

阶段转换规则 本阶段你要向我提问。 如果存在多个你无法抉择的方案，要问我，作为提问的一部分。 如果没有需要问我的，则直接进入下一阶段。

阶段二：制定方案

声明格式：【制定方案】

前置条件：

    我明确回答了关键技术决策。

必须做的事：

    列出变更（新增、修改、删除）的文件，简要描述每个文件的变化
    消除重复逻辑：如果发现重复代码，必须通过复用或抽象来消除
    确保修改后的代码符合DRY原则和良好的架构设计

如果新发现了向我收集的关键决策，在这个阶段你还可以继续问我，直到没有不明确的问题之后，本阶段结束。 本阶段不允许自动切换到下一阶段。
阶段三：执行方案

声明格式：【执行方案】

必须做的事：

    严格按照选定方案实现
    修改后运行类型检查

绝对禁止：

    ❌ 提交代码（除非用户明确要求）

如果在这个阶段发现了拿不准的问题，请向我提问。

收到用户消息时，一般从【分析问题】阶段开始，除非用户明确指定阶段的名字。