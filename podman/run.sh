#!/usr/bin/env bash
# NPanel Podman 启动脚本 (幂等, 可重复执行)
#
# 设计决策:
#   - 不使用 pod: 每个服务都是独立容器, PHP-FPM 由宿主机 Nginx 反代 127.0.0.1:9001
#   - 统一加入 main 网络: 容器间互通 (如 PHP 连 redis:6379)
#   - 端口仅绑定 127.0.0.1: 不监听公网
#
# 用法:
#   ./run.sh            # 启动 php7-npanel + redis (镜像不存在时自动构建)
#   ./run.sh --rebuild  # 强制重建镜像后启动
set -euo pipefail

cd "$(dirname "$0")"

IMAGE="localhost/php7-npanel:latest"
WORKDIR="/var/www"
# 宿主机有 SELinux 时在挂载后追加 :Z
MOUNT_OPTS=""

# ── 1. 预建 main 网络 (已存在则忽略) ─────────────────────────────
podman network create main 2>/dev/null || true

# ── 2. 构建镜像 ─────────────────────────────────────────────────
if [[ "${1:-}" == "--rebuild" ]] || ! podman image exists "$IMAGE"; then
    echo "==> 构建 $IMAGE"
    podman build -t "$IMAGE" -f pod/php7/Containerfile pod/php7
fi

# ── 3. 启动 php7-npanel (存在则跳过) ────────────────────────────
if podman container exists php7-npanel; then
    echo "==> php7-npanel 已存在, 跳过 (如需重建: podman rm -f php7-npanel && ./run.sh)"
else
    echo "==> 启动 php7-npanel (127.0.0.1:9001 -> 9000)"
    podman run -d \
        --name php7-npanel \
        --network main \
        -p 127.0.0.1:9001:9000 \
        -v /var/www:/var/www${MOUNT_OPTS:+:$MOUNT_OPTS} \
        --restart always \
        --workdir "$WORKDIR" \
        "$IMAGE"
fi

# ── 4. 启动 redis (存在则跳过) ───────────────────────────────────
if podman container exists redis; then
    echo "==> redis 已存在, 跳过"
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
