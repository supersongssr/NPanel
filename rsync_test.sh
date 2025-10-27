#!/bin/zsh

# -----------------------------------------------------------------------------
# 监控本地目录 (fswatch) 并自动同步到远程 (rsync)
# -----------------------------------------------------------------------------

# --- 用户配置 ---

# 1. 本地监控的目录 (例如: /Users/me/my-project)
#    (注意: rsync 将同步此目录的 *内容*)
# 使用 ${0:A:h} 来获取脚本的绝对路径的父目录
rsync_local="${0:A:h}"

# 2. 远程 rsync 目标 (例如: user@server:/remote/path)
rsync_remote="root@test.srp:/www/wwwroot/Npanel"

# --- 高级配置 ---

# Rsync 选项
# -a: 归档模式 (保留权限、时间等, -rlptgoD 的简写)
# -v: 详细输出
# -z: 压缩传输
# --delete: 删除远程目标上存在但本地源中已删除的文件 (保持完全一致)
# --progress: 显示进度
rsync_options=("-avz" "--progress")

# fswatch 排除规则 (使用正则表达式)
# 避免监控这些文件/目录的变更, 从而避免触发 rsync
# 例如: 排除 .git/, node_modules/ 和 .DS_Store
fswatch_excludes=(
    "--exclude=\\.git/"
    "--exclude=node_modules/"
    "--exclude=\\.DS_Store$"
)

# 防抖时间 (秒)
# 在检测到第一次变更后, 等待这么久以收集同一批次的其他变更
DEBOUNCE_SECONDS=1

# -----------------------------------------------------------------------------
# 脚本主体 (通常不需要修改)
# -----------------------------------------------------------------------------

# --- 1. 检查依赖 ---

if ! command -v fswatch &> /dev/null; then
    echo "错误: 未找到 'fswatch'. 请先安装." >&2
    echo "  - macOS (Homebrew): brew install fswatch" >&2
    echo "  - Debian/Ubuntu: sudo apt install fswatch" >&2
    exit 1
fi

if ! command -v rsync &> /dev/null; then
    echo "错误: 未找到 'rsync'. 请先安装." >&2
    exit 1
fi

# --- 2. 检查本地目录 ---

if [ ! -d "$rsync_local" ]; then
    echo "错误: 本地目录 '$rsync_local' 不存在." >&2
    exit 1
fi

# --- 3. 定义同步函数 ---

# 将 rsync 逻辑封装在一个函数中
run_rsync() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] 开始同步..."
    
    # 注意: "$rsync_local/" 末尾的斜杠是故意的.
    # 它告诉 rsync "同步 $rsync_local 的 *内容* 到 $rsync_remote"
    # 而不是 "在 $rsync_remote 内部创建一个名为 $rsync_local 的目录"
    
    rsync ${rsync_options[@]} "$rsync_local/" "$rsync_remote"
    
    if [ $? -eq 0 ]; then
        echo "[$(date +'%Y-%m-%d %H:%M:%S')] 同步成功."
    else
        echo "[$(date +'%Y-%m-%d %H:%M:%S')] 同步失败 (Rsync 错误码: $?)." >&2
    fi
    echo "------------------------------------------------------"
}


# --- 4. 启动监控 ---

echo "--- FsWatch 自动同步已启动 ---"
echo "监控本地: $rsync_local"
echo "同步目标: $rsync_remote"
echo "Rsync 选项: ${rsync_options[*]}"
echo "排除规则: ${fswatch_excludes[*]}"
echo "------------------------------------------------------"
echo "正在等待文件变更..."

# -r: 递归监控
# -o: 批量模式, 合并短时间内的多个事件
# ${fswatch_excludes[@]}: zsh 数组展开
fswatch -r -o ${fswatch_excludes[@]} "$rsync_local" | while read -r changed_file; do
    # 接收到来自 fswatch 批处理的 *第一个* 事件
    
    # 打印简报 (只显示批次中的第一个变化)
    # echo "[$(date +'%Y-%m-%d %H:%M:%S')] 检测到变化 (例如: ${changed_file#$rsync_local/})."
    # echo "等待 $DEBOUNCE_SECONDS 秒收集批次中的其他变化..."

    # 防抖 (Debounce) 逻辑:
    # 快速读取并丢弃管道中在 $DEBOUNCE_SECONDS 秒内到达的所有其他行
    # zsh 的 read -t 功能
    while read -r -t $DEBOUNCE_SECONDS; do
        : # 丢弃这些行, 它们是同一批次的一部分
    done
    
    # 等待时间结束后, 执行一次 rsync
    run_rsync
    echo "继续监控..."

done