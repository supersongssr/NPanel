#!/usr/bin/env bash
# NPanel Podman 启动脚本 (幂等, 可重复执行)
#
# 设计决策:
#   - 不使用 pod: 每个服务都是独立容器, PHP-FPM 由宿主机 Nginx 反代 127.0.0.1:9001
#   - 统一加入 main 网络: 容器间互通 (如 PHP 连 redis:6379)
#   - 端口仅绑定 127.0.0.1: 不监听公网
#   - php7 容器资源限制 1C1G: --cpus 1 --memory 1g (禁用 swap)
#   - 幂等: 已存在的容器确保在运行 (停止则 start), 不会覆盖/重建宿主机已有服务
#
# 前提:
#   - root 运行: redis 步骤需创建 /opt/redis/data (非 root 开头即失败, 避免半启动)
#   - rootless podman: 需 loginctl enable-linger <user> 才能开机自启,
#     redis 数据目录 /opt/redis/data 亦需自行调整为用户可写路径
#
# 用法:
#   ./run.sh            # 启动 php7-npanel + redis (镜像不存在时自动构建)
#   ./run.sh --rebuild  # 强制重建镜像后启动
set -euo pipefail

cd "$(dirname "$0")"

# 提前失败: 非 root 会在 redis 步骤的 mkdir -p /opt/redis/data 处半路退出
# (php7 已启动、redis 未启动), 不如在产生任何副作用前就拒绝
if [ "$(id -u)" -ne 0 ]; then
    echo "错误: 请以 root 运行 (redis 步骤需创建 /opt/redis/data)" >&2
    exit 1
fi

IMAGE="localhost/php7-npanel:latest"
WORKDIR="/var/www"
# 宿主机有 SELinux 时在挂载后追加 :Z
MOUNT_OPTS=""

# 容器是否在运行 (podman container exists 只查存在性, 不查运行状态)
is_running() {
    [ "$(podman container inspect "$1" --format '{{.State.Running}}' 2>/dev/null)" = "true" ]
}

# ── 1. 预建 main 网络 (已存在则忽略) ─────────────────────────────
podman network create main 2>/dev/null || true

# ── 2. 构建镜像 ─────────────────────────────────────────────────
if [[ "${1:-}" == "--rebuild" ]] || ! podman image exists "$IMAGE"; then
    echo "==> 构建 $IMAGE"
    podman build -t "$IMAGE" -f pod/php7/Containerfile pod/php7
fi

# ── 3. 启动 php7-npanel (存在则确保在运行) ──────────────────────
if podman container exists php7-npanel; then
    if is_running php7-npanel; then
        echo "==> php7-npanel 已在运行, 跳过 (如需重建: podman rm -f php7-npanel && ./run.sh)"
    else
        echo "==> php7-npanel 已存在但未运行, 启动中"
        podman start php7-npanel
    fi
else
    echo "==> 启动 php7-npanel (127.0.0.1:9001 -> 9000, 1C1G)"
    podman run -d \
        --name php7-npanel \
        --network main \
        -p 127.0.0.1:9001:9000 \
        --cpus 1 \
        --memory 1g \
        --memory-swap 1g \
        -v /var/www:/var/www${MOUNT_OPTS:+:$MOUNT_OPTS} \
        --restart always \
        --workdir "$WORKDIR" \
        "$IMAGE"
fi

# ── 4. 启动 redis (存在则确保在运行) ─────────────────────────────
if podman container exists redis; then
    if is_running redis; then
        echo "==> redis 已在运行, 跳过"
    else
        echo "==> redis 已存在但未运行, 启动中"
        podman start redis
    fi
else
    echo "==> 启动 redis (127.0.0.1:6379 -> 6379)"
    mkdir -p /opt/redis/data
    podman run -d \
        --name redis \
        --network main \
        -p 127.0.0.1:6379:6379 \
        --restart always \
        --sysctl net.core.somaxconn=1024 \
        -v /opt/redis/data:/data${MOUNT_OPTS:+:$MOUNT_OPTS} \
        docker.io/library/redis:alpine \
        redis-server --appendonly yes
fi

echo "==> 完成。运行状态:"
podman ps --filter network=main --format 'table {{.Names}}\t{{.Image}}\t{{.Ports}}\t{{.Status}}'
