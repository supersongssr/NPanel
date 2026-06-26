# NPanel Development Guidelines


## 环境说明

> 📌 **详细配置**: 项目特定的环境配置、容器信息、测试规范等信息已移至 `.claude/PROJECT_CONTEXT.md` 文件中,该文件不会被提交到公共仓库。

### 基本信息
- PHP 运行环境: Podman 容器
- **PHP 版本约束: 必须使用 PHP 7.4，严禁使用 PHP 8+ 特性**
- 测试目录: `tests/`
- 环境配置文件: `.claude/ENVIRONMENT.md`
- 项目上下文: `.claude/PROJECT_CONTEXT.md`

### Testing
测试文件位于 `tests/` 文件夹。

**重要约束**:
- `tests/` 文件夹的脚本必须设置 `APP_ENV=test` 才能运行(脚本顶部会检查)
- 编写测试代码后应该自动运行脚本并调试
- 详细的测试规范请参考 `.claude/PROJECT_CONTEXT.md`

> ℹ️ **storage 权限已由 ACL 自动保障,无需任何 chown**:
> `storage/` 已设置 default ACL(`setfacl -R -d -m g:www-data:rwX storage`),任何身份
> (含 root 跑的测试 / artisan)在其中新建的文件(日志、缓存、编译视图)都会自动带
> www-data 组读写权限,PHP-FPM(www-data)可正常写入。因此可放心用任意方式运行测试。
> 历史上“root 跑测试 → 日志属主变 root → 接口 500 且日志空白”的 bug 已由此彻底消除。

## Developing 

## PHP 版本强制约束

> **必须使用 PHP 7.4，严禁使用 PHP 8.0+ 语法特性。**

### 禁止使用的 PHP 8+ 特性

| PHP 8+ 特性 | 替代方案 |
|-------------|---------|
| 命名参数 `foo(name: $val)` | 位置参数 `foo($val)` |
| Match 表达式 `match($x) {...}` | `switch` 或 `if/elseif` |
| Null 安全运算符 `$obj?->prop` | `isset()` 或 `null !== $obj->prop` 判断 |
| 联合类型 `int\|string` | 类型注释或手动校验 |
| `mixed` 类型 | 不声明类型或用注释 |
| 构造器属性提升 `public int $x` | 手动声明属性 + 在构造器中赋值 |
| `readonly` 属性 | 普通 `private`/`protected` 属性 |
| `enum` 枚举 | 常量 `const` + 静态方法 |
| `fiber` | 无替代，不要使用 |
| `first-class callable syntax` `strlen(...)` | `function($s) { return strlen($s); }` |
| `#[Attribute]` 属性 | PHPDoc 注释 |
| `never` 返回类型 | `void` 或不声明 |

### 允许使用的 PHP 7.4 特性

- 箭头函数 `fn($x) => $x * 2` ✅
- 类型属性 `public int $id;` ✅
- Null 合并赋值 `$a ??= $b;` ✅
- 数组展开运算符 `[...$arr]` ✅

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

### 权限与自动加载
- **`storage/` 与 `bootstrap/cache/` 已由 ACL 自动保障权限**:已设置
  `setfacl -R -d -m g:www-data:rwX`,任何人(含 root)在其中新建的运行时文件
  (日志、缓存、编译视图)都会自动带 www-data 组读写权限,**无需任何 chown**。
- **代码文件(`app/`、`tests/`)只读执行**,FPM 读取 644/755 即可,属主是 root 也不影响。
- **新建/移动 PHP 类文件、批量改 namespace 后**,只需刷新 Composer 自动加载映射:
  ```bash
  podman exec php7-npanel php /var/www/NPanel/composer.phar dump-autoload
  ```




## 命令与语法约束
```where```: 修改的文件的位置 或 模块位置
```why```: 为什么修改
```how```: 如何修改代码

```input```: 模块接受哪些数据
```output```: 模块应该输出什么数据
```do```: 模块做哪些任务和工作

```must```:  模块收到哪些 约束 , 必须遵守的条件
