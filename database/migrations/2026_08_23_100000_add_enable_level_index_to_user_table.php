<?php

/**
 * v2 后端节点 API GET /api/v2/backend/users 查询加速索引.
 *
 * 背景: users() 过滤条件 WHERE enable=1 AND transfer_enable > u+d AND level >= X
 *   [AND node_group = Y]。现有索引均以 enable 开头但第二列(status/expire_time)
 *   不在查询条件里, level 只能残行过滤; 且线上存在 idx_search 与
 *   idx_enable_level 之外还有一个完全重复的 (enable,status) 索引
 *   (idx_search 与 idx_enable_status 同列同序, 白付一份写放大)。
 *
 * idx_enable_level (enable, level):
 *   enable 等值 + level 范围, 候选集直接缩到"启用且等级达标"。
 *   transfer_enable/u+d 为残行过滤, 不适合再入索引(选择性差且写频繁)。
 *
 * 顺带: 现存重复索引 idx_enable_status(与 idx_search 同列同序, 代码中无
 *   FORCE INDEX 引用)在本迁移中一并删除 —— 每行写入省一份索引维护。
 *
 * 幂等: up()/down() 先查 information_schema, 重复执行不报错。
 * 契约: xray-plugin-api docs/openapi.yaml
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEnableLevelIndexToUserTable extends Migration
{
    const TABLE = 'user';
    const INDEX = 'idx_enable_level';
    const DUP_INDEX = 'idx_enable_status'; // 与 idx_search 同列同序的重复索引

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!$this->indexExists(self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['enable', 'level'], self::INDEX);
            });
        }

        // 删除重复的 (enable,status) 索引(idx_search 保留, 同列同序)
        if ($this->indexExists(self::DUP_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::DUP_INDEX);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if ($this->indexExists(self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    private function indexExists($name)
    {
        $count = Schema::getConnection()->select(
            'SELECT COUNT(*) AS c FROM information_schema.statistics '
            . "WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
            [self::TABLE, $name]
        );

        return (int)$count[0]->c > 0;
    }
}
