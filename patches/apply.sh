#!/usr/bin/env bash
# =============================================================================
# patches/apply.sh — PHP 8 兼容补丁自动应用器
#
# <why>   Laravel 5.6.39 / Carbon 1.25.0 在 PHP 8.x 下存在致命兼容性问题
#         (详见 docs/php8-upgrade-feasibility.md), 需要两个 vendor 补丁;
#         而 composer install/update 会还原 vendor, 故通过 composer
#         post-autoload-dump 钩子自动重新应用
# <input> 无参数 (项目根目录下执行; 补丁文件位于 patches/*.patch,
#         路径相对于 vendor/)
# <output> 终端输出应用结果; 修改 vendor/ 内文件
# <do>    依次尝试应用每个补丁; 已应用则跳过; 真失败则告警但不中断构建
# <must>  幂等(重复执行无副作用); 已应用补丁不得报错退出;
#         apply 失败不 block composer (退出码恒为 0, 但打印醒目警告)
# =============================================================================
set -u
cd "$(dirname "$0")/.."

if ! command -v patch >/dev/null 2>&1; then
    echo "[patches] ⚠️  容器缺少 'patch' 命令, 无法自动应用补丁!" >&2
    echo "[patches]    请手动检查 vendor 兼容补丁是否已生效" >&2
    exit 0
fi

shopt -s nullglob
newly=0; failed=0; uptodate=0
for p in patches/*.patch; do
    if patch --batch --forward --silent -d vendor -p1 < "$p" >/dev/null 2>&1; then
        echo "[patches] 已应用: $p"
        newly=$((newly+1))
    elif patch --batch --dry-run --reverse --silent -d vendor -p1 < "$p" >/dev/null 2>&1; then
        uptodate=$((uptodate+1))
    else
        echo "[patches] ⚠️  应用失败(源文件可能已变动): $p" >&2
        failed=$((failed+1))
    fi
done

echo "[patches] 完成: 新应用 ${newly}, 已是最新 ${uptodate}, 失败 ${failed}"
exit 0
