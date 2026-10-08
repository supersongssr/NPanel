# NPanel Podman 部署方案

Podman 版运行方案。核心设计决策:

1. **不使用 pod** — 所有服务均为独立容器。pod 会强制容器共享同一个
   network namespace 并由 infra 容器统一持有端口,而本方案的 PHP-FPM 由
   宿主机 Nginx 通过 `127.0.0.1:9001` 反代接入,各服务(php7/redis/mysql)
   也需要独立端口暴露,加入 pod 反而多一层间接。
2. **统一加入 `main` 网络** — 容器间通过 `main` bridge 网络互通
   (如 PHP 连 `redis:6379`);`main` 为预建外部网络,不归 compose 管理。
3. **端口仅绑定 `127.0.0.1`** — 所有对外端口(9001/6379/3306/8081)都加
   `127.0.0.1` 前缀,不监听公网,由宿主机 Nginx 反代对外提供服务。
4. **php7 资源限制 1C1G** — `--cpus 1 --memory 1g`(禁用 swap),避免
   单个站点拖垮宿主机上共存的其他容器。
5. **不接管已有基础设施** — compose 只默认管理 `php7`;宿主机已存在同名
   `redis`/`mysql` 等容器时,compose 会因容器名冲突而 `up` 失败(不会
   覆盖/重建),因此这类服务仅在注释中提供可选项,或用幂等的 `run.sh`
   (已存在则跳过)。

## 目录结构

```
podman/
├── compose.yml            # 编排: 仅 php7 (redis/mysql/adminer 可选, 已存在同名容器时勿启用)
├── run.sh                 # 无 compose 依赖的纯 podman run 版本 (幂等)
└── pod/php7/
    ├── Containerfile      # PHP 7.4 FPM 镜像构建
    └── custom-php.ini     # 自定义 PHP 配置
```

## 使用

### 方式一: 纯 podman 命令 (推荐, 零依赖)

```sh
cd podman
./run.sh            # 预建 main 网络 + 构建镜像 + 启动 php7-npanel 与 redis
./run.sh --rebuild  # 强制重建镜像
podman ps           # 查看运行状态
```

> `run.sh` 需以 root 运行 (redis 步骤要创建 `/opt/redis/data`);
> 对已存在的容器(含宿主机手动创建的 redis)确保其在运行 —— 停止的会被
> `podman start` 拉起, 但不会覆盖/重建;
> 已存在但需应用新的 1C1G 限制时: `podman rm -f php7-npanel && ./run.sh`。
> rootless podman 下 `--restart always` 需 `loginctl enable-linger <user>` 才能开机自启。

### 方式二: podman compose

需要 `podman-compose` 或 `podman compose` provider:

```sh
cd podman
podman network create main 2>/dev/null || true   # 预建外部网络
podman-compose up -d        # 或: podman compose up -d
podman-compose ps
```

## 从 docker/ 方案迁移 (该方案已移除)

docker/ 编排方案已删除, 部署统一走本目录。曾用过 docker 版的环境迁移要点:

- php7: `docker rm -f php7-npanel` 后执行 `./run.sh`(容器同名, 先删避免冲突;
  镜像不通用, podman 会按本目录 Containerfile 自行构建)
- redis 若曾被旧版 docker compose 管理过(redis 还是正式服务的那版):
  清理旧 compose 项目时**不要**带 `--remove-orphans`, 否则会连 redis 容器
  一起删掉; 数据卷 `/opt/redis/data` 不受影响, 容器误删后 `./run.sh`
  会自动重建并复用原数据

## 与宿主机 Nginx 对接

Nginx `fastcgi_pass 127.0.0.1:9001;`,详见 `docs/install-podman-nginx.md`。
宿主机与容器均挂载在 `/var/www`,路径天然对齐。

## 常用运维命令

```sh
# 执行测试 (APP_ENV=test 注入, 不污染 dev 环境)
podman exec -e APP_ENV=test php7-npanel php tests/test_xxx.php

# 刷新 Composer 自动加载映射
podman exec php7-npanel php /var/www/NPanel/composer.phar dump-autoload

# 进入容器
podman exec -it php7-npanel /bin/bash
```

> 挂载说明: 宿主机无 SELinux 时 `-v /var/www:/var/www` 即可;SELinux 主机需追加 `:Z`。
> PHP 7.4 约束: 镜像固定 `php:7.4-fpm`,不得升级基础镜像。
