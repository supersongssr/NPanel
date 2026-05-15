# Cache System Config in Redis

## 1. What (目标)
将系统配置 (`config` 表) 的读取从 MySQL 迁移至 Redis 缓存。通过 `system_config` 缓存键存储整个配置项数组，大幅降低数据库查询频率。

## 2. Why (原因)
- **性能瓶颈**: `Helpers::systemConfig()` 是系统最基础的函数之一，几乎在每个页面加载和 API 调用时都会执行。
- **延迟降低**: 数据库查询涉及磁盘 I/O 和可能的网络延迟（尤其是数据库不在本地时），而 Redis 在内存中运行，读取速度快几个数量级。
- **一致性保障**: 通过 Eloquent 模型事件，可以确保在后台修改配置时，缓存能够被自动且可靠地清除。

## 3. Where (范围)
- **读取位置**: `app/Components/Helpers.php` -> `systemConfig()` 函数。
- **定义位置**: `app/Http/Models/Config.php` (Config 模型类)。
- **修改位置**:
    - `app/Http/Controllers/AdminController.php`: 包含多个 `Config::query()->update()` 调用的地方。
    - `app/Console/Commands/AutoStatisticsNodeDailyTraffic.php`: (如涉及非注释代码)。

## 4. How (方案)
### 4.1 引入缓存逻辑
在 `app/Components/Helpers.php` 中，将数据库查询封装在 `Cache::remember` 中：
```php
public static function systemConfig() {
    return \Cache::remember('system_config', 86400, function () {
        $config = Config::query()->get();
        // ... 原有的数组处理逻辑 ...
        return $data;
    });
}
```

### 4.2 自动化清理机制
在 `app/Http/Models/Config.php` 中利用模型观察者模式：
```php
protected static function boot() {
    parent::boot();
    static::saved(function () { \Cache::forget('system_config'); });
    static::deleted(function () { \Cache::forget('system_config'); });
}
```

### 4.3 代码重构 (适配事件)
将 `AdminController.php` 中直接调用 `update()` 的代码重构为触发事件的模式：
- **修改前**: `Config::query()->where('name', $name)->update(['value' => $value]);`
- **修改后**:
  ```php
  $config = Config::query()->where('name', $name)->first();
  if ($config) {
      $config->value = $value;
      $config->save(); // 触发 saved 事件
  }
  ```

## 5. Must (准则)
- **必须保证一致性**: 绝对不允许出现数据库已更新但缓存未清除的情况。所有对 `config` 表的写入必须通过模型实例完成，或手动追加缓存清理逻辑。
- **必须考虑 Redis 不可用**: 代码中应确保即使 Redis 宕机，系统也能自动降级读取数据库，不能导致系统崩溃。
- **必须进行线上验证**: 部署后必须手动在后台修改一项配置（如网站名称），并立即在前端观察变更是否生效，以验证清理链路的完整性。
