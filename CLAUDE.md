# NPanel Development Guidelines

## 命令与语法约束
```where```: 修改的文件的位置 或 模块位置
```why```: 为什么修改
```how```: 如何修改代码

```input```: 模块接受哪些数据
```in```: 功能和```input```相同

```output```: 模块应该输出什么数据
```out```: 功能和```output```相同
```do```: 模块做哪些任务和工作
```must```:  模块收到哪些 约束 , 这些约束会作为 完成代码的自动化测试的 条件. 


## 工作流程 wordflow 
0. 从 @README.md @AI/ 中的 md 、 yaml 文件中 加载 内容.
1. 根据 where , 定位要修改的代码的部分. 
2. 要修改的代码要在一个模块内完成, 不要修改其他模块的 代码
3. 根据 why, 知道为什么要修改
4. 根据 how 了解 如何做修改. 
5. 查询对应的模块, 阅读模块的 in / input 需要哪些数据, 这是硬性约束,  查看 模块 do 哪些功能, 然后确定要修改的代码如何融入
6. 完成代码修改后, 根据 must 中的限制条件, 生成 test , 运行 test 检测是否符合 must的约束. 
7. 如果 代码 test 未通过 , 重新回到 步骤 1 根据 test的报错信息 检查
8. 如果 代码 test 通过, 为这次重新生成 *模块* or api 的 文档 和 yaml文件在 @AI 文件夹

##  Test  
```yaml
remote:
    host : test.srp
    user: root 
    sync : rsync -av 
    path : /www/wwwroot/Npanel/

```
请将代码在 remote.host 上 test , 使用 rsync 同步到 remote.path 路径
must:
    1. 不能在 macos 本机运行! 因为没有部署环境
    2. 测试前,先 rsync 同步到 remote.host remote.path 



## 完成代码编辑后到记录
在 @AI/updates 目录 按照 <YYYY-MM-DD_HH-mm>.yaml
按照如下格式记录
```yaml
when: 2000-01-01T11:22:30 #什么时候更新的  <ISO 8601 时间格式>
where: #更新了哪些文件
why: # 为什么更新
how: # 怎么更新的
do: # 做了哪些更改
must: # 约束条件是什么
```

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


