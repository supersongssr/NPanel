#!/bin/bash

echo "🔄 正在同步远程 Tags 数据..."
# 获取所有标签，--force 确保本地标签与远程绝对同步
# git fetch --all --tags --prune --prune-tags --force > /dev/null 2>&1
git fetch --all --tags --prune

# 1. 获取当前所在的 Tag 名
CURRENT_TAG=$(git describe --tags --exact-match 2>/dev/null)

if [ -z "$CURRENT_TAG" ]; then
    echo "⚠️ 警告：当前未处于任何 Tag 上。"
    echo "脚本无法自动判定大版本范围。请手动切换到一个初始 Tag，或修改脚本跳过此检查。"
    exit 1
fi

# 2. 提取当前主版本号 (例如从 v3.1.2 提取 v3)
# 逻辑：以第一个点或第一个横杠为界限取前面部分
CURRENT_MAJOR=$(echo "$CURRENT_TAG" | cut -d'.' -f1 | cut -d'-' -f1)

# 3. 按【创建日期】获取全局最新的 Tag
LATEST_TAG=$(git for-each-ref --sort=-creatordate --format='%(refname:short)' refs/tags | head -n1)

# 4. 提取最新 Tag 的主版本号
LATEST_MAJOR=$(echo "$LATEST_TAG" | cut -d'.' -f1 | cut -d'-' -f1)

echo "---------------------------------------"
echo "当前版本: $CURRENT_TAG (主版本: $CURRENT_MAJOR)"
echo "最后创建: $LATEST_TAG (主版本: $LATEST_MAJOR)"
echo "---------------------------------------"

# 5. 核心判断逻辑
if [ "$CURRENT_TAG" == "$LATEST_TAG" ]; then
    echo "✅ 已经是最新创建的 Tag，无需更新。"
    exit 0
fi

# 检查大版本是否一致
if [ "$CURRENT_MAJOR" != "$LATEST_MAJOR" ]; then
    echo "🛑 [安全拦截] 检测到大版本变更！"
    echo "当前处于 $CURRENT_MAJOR，但最新创建的 Tag 属于 $LATEST_MAJOR。"
    echo "跨大版本更新可能包含破坏性改动，脚本已跳过自动升级。"
    echo "如需升级，请手动执行: git checkout $LATEST_TAG"
    exit 1
fi

# 6. 执行升级
echo "🚀 大版本一致，正在更新至最新 Tag: $LATEST_TAG..."
git checkout "$LATEST_TAG" --quiet --detach

if [ $? -eq 0 ]; then
    echo "✨ 更新成功！目前代码已同步至 $LATEST_TAG"
else
    echo "❌ 错误：切换失败，请检查本地是否存在未提交的修改。"
    exit 1
fi

# 7. 应用数据库迁移 (代码同步后自动升级数据库)
# 经 ./run 菜单入口触发, 幂等. 失败不阻断 (代码已就位, 可后续手动 ./run migrate).
echo "📦 应用数据库迁移..."
if [ -x ./run ]; then
    ./run migrate
else
    php artisan migrate --force
fi