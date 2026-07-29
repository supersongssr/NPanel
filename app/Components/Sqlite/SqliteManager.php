<?php

namespace App\Components\Sqlite;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SQLite 底层模块 (全局 SQLite 基础设施).
 *
 * 职责: 统一管理库文件路径 / 目录创建 / 连接获取 / 表初始化 / 跨用户权限.
 * 所有 SQLite 用途 (如 NodeTrafficResetStore) 一律经此模块访问, 避免散落多份重复逻辑
 * 与多份配置. 新增 SQLite 状态表时, 只需声明 DDL + 调 ensureTable(), 其余全自动.
 *
 * 配置 (config/database.php 连接 sqlite_state):
 *   - 目录: env SQLITE_STATE_DIR, 默认 base_path('.sqlite')
 *   - 库文件名固定: sqlite.db  →  完整路径 {dir}/sqlite.db
 *
 * 表初始化 (必须):
 *   SQLite 不会自动建表, 每张表必须显式 CREATE TABLE. ensureTable() 以
 *   CREATE TABLE IF NOT EXISTS 懒建 (幂等, 进程内每表只建一次), 消费方只须声明 DDL.
 *
 * 权限 (跨 www-data / root 写入):
 *   - 库目录与文件需同时可写于 PHP-FPM (www-data, HTTP) 与 cron (通常 root).
 *   - 本模块 mkdir + touch + chmod(0775/0664); 若当前进程为 root, 额外 chown 给 www-data
 *     (root 自身不受属主限制仍可写), 解决容器内无 setfacl 时的跨用户写入.
 *   - 宿主机若已设 default ACL (g:www-data:rwX, 同 storage/) 则更稳妥, 二者不冲突.
 *
 * 容错: 建环境/建表失败只记 warning 不抛异常; 查询错误由调用方自行捕获.
 */
class SqliteManager
{
    /** 全局 SQLite 连接名 (config/database.php). */
    const CONNECTION = 'sqlite_state';

    /** 进程内: 环境已就绪标记 (避免重复 mkdir/touch/chmod). */
    private static $environmentReady = false;

    /** 进程内: 已建表集合 (避免重复 DDL). */
    private static $ensuredTables = [];

    /**
     * 库文件完整路径 (来自 config 连接 database 字段).
     *
     * @return string|null
     */
    public static function path()
    {
        return config('database.connections.' . self::CONNECTION . '.database');
    }

    /**
     * 库文件所在目录.
     *
     * @return string
     */
    public static function dir()
    {
        return dirname((string) self::path());
    }

    /**
     * 确保环境就绪: 目录存在 + 库文件存在 + 权限可写. 进程内只执行一次.
     *
     * Laravel 的 SQLite 连接要求库文件预先存在 (不会自动创建空文件), 故须先 touch;
     * 否则连接即抛 "Database does not exist".
     *
     * @return void
     */
    public static function ensureEnvironment()
    {
        if (self::$environmentReady) {
            return;
        }
        try {
            $path = (string) self::path();
            if ($path === '' || $path === ':memory:') {
                self::$environmentReady = true;
                return;
            }
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (!file_exists($path)) {
                @touch($path);
            }
            @chmod($dir, 0775);
            @chmod($path, 0664);
            // root 创建时把属主交给 www-data, 使 PHP-FPM (www-data) 也能写;
            // root 自身不受属主限制仍可写. 容器内无 setfacl, 用 chown 解决跨用户写入.
            if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
                $www = function_exists('posix_getpwnam') ? @posix_getpwnam('www-data') : false;
                if (is_array($www) && isset($www['uid'], $www['gid'])) {
                    @chown($dir, (int) $www['uid']);
                    @chgrp($dir, (int) $www['gid']);
                    @chown($path, (int) $www['uid']);
                    @chgrp($path, (int) $www['gid']);
                }
            }
            self::$environmentReady = true;
        } catch (\Exception $e) {
            Log::warning('[SqliteManager] ensureEnvironment 失败: ' . $e->getMessage());
        }
    }

    /**
     * 取 SQLite 连接 (确保环境就绪).
     *
     * @return \Illuminate\Database\ConnectionInterface
     */
    public static function connection()
    {
        self::ensureEnvironment();
        return DB::connection(self::CONNECTION);
    }

    /**
     * 确保表存在 (CREATE TABLE IF NOT EXISTS). 幂等, 进程内每表只执行一次.
     *
     * 用法 (消费方):
     *   SqliteManager::ensureTable('my_table',
     *       'CREATE TABLE IF NOT EXISTS my_table (id INTEGER PRIMARY KEY, ...)');
     *
     * @param string $table 表名 (仅用于进程内去重缓存)
     * @param string $ddl   完整 CREATE TABLE 语句 (须含 IF NOT EXISTS)
     * @return void
     */
    public static function ensureTable($table, $ddl)
    {
        if (isset(self::$ensuredTables[$table])) {
            return;
        }
        self::ensureEnvironment();
        try {
            self::connection()->statement($ddl);
            self::$ensuredTables[$table] = true;
        } catch (\Exception $e) {
            Log::warning('[SqliteManager] ensureTable(' . $table . ') 失败: ' . $e->getMessage());
        }
    }
}
