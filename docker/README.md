# NPanel Docker 部署方案

与 `podman/` 目录下的方案完全等价的 Docker 版本, 供使用 Docker 的环境部署 NPanel
(PHP 7.4 FPM + Redis + 宿主机 Nginx)。

## 目录结构

```
docker/
├── docker-compose.yml      # 编排: 仅 php7 (redis/mysql/adminer 可选, 已存在同名容器时勿启用)
└── php7/
    ├── Dockerfile           # 等价于 podman/pod/php7/Containerfile
    └── custom-php.ini       # 自定义 PHP 配置 (与 podman 方案一致)
```

## 安装 Docker

```sh
curl -fsSL https://get.docker.com | sh
docker --version
```

## 方式一: docker compose (推荐)

```sh
cd docker

# 构建镜像并启动 (php7, 资源限制 1C1G)
docker compose up -d

# 查看运行状态
docker compose ps

# 查看日志
docker compose logs -f php7
```

## 方式二: 手动 docker 命令 (对应 podman 文档流程)

```sh
cd docker/php7

# 生成镜像
docker build -t npanel/php7:latest -f Dockerfile .

# 创建网络; 需要互相访问的服务都加入这个网络
docker network create main

# 生成容器, 监听 9001 (仅本地访问)
docker run -d \
  --name php7-npanel \
  --network main \
  -p 127.0.0.1:9001:9000 \
  -v /var/www:/var/www \
  --restart always \
  --workdir /var/www \
  npanel/php7:latest

# 查看是否运行
docker ps

# Redis
# mkdir -p /opt/redis/data
docker run -d \
  --name redis \
  --network main \
  -p 127.0.0.1:6379:6379 \
  --restart always \
  --sysctl net.core.somaxconn=1024 \
  -v /opt/redis/data:/data \
  redis:alpine redis-server --appendonly yes
```

## Nginx 配置 (宿主机)

与 podman 方案一致, `fastcgi_pass` 指向 `127.0.0.1:9001`:

```conf
server {
    listen 80;
    server_name test-npanel.freessr.bid;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name test-npanel.freessr.bid;

    ssl_certificate     /etc/ssl/freessr.bid.pem;
    ssl_certificate_key /etc/ssl/freessr.bid.key;

    ssl_session_timeout 1d;
    ssl_session_cache shared:MozSSL:10m;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;

    # 网站根目录 (指向宿主机的 public)
    root /var/www/NPanel/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass 127.0.0.1:9001;
        # 宿主机与容器均挂载在 /var/www, 路径天然对齐
        fastcgi_param SCRIPT_FILENAME /var/www/NPanel/public$fastcgi_script_name;
    }

    location ~ /\.(ht|git|env) {
        deny all;
    }
}
```

```sh
systemctl restart nginx
```

## 初始化站点

```sh
cd /var/www/NPanel

mkdir -p storage/app
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/testing
mkdir -p storage/logs

# 生成密钥对 (进入容器执行; 容器默认工作目录是 /var/www, 需 -w 指到项目目录)
docker exec -it -w /var/www/NPanel php7-npanel php artisan key:generate
```

## 常用运维命令

```sh
# 执行测试 (APP_ENV=test 注入, 不污染 dev 环境)
docker exec -e APP_ENV=test -w /var/www/NPanel php7-npanel php tests/test_xxx.php

# 刷新 Composer 自动加载映射
docker exec -w /var/www/NPanel php7-npanel php composer.phar dump-autoload

# 进入容器
docker exec -it php7-npanel /bin/bash
```

> PHP 7.4 约束: 镜像固定为 `php:7.4-fpm`, 请勿升级基础镜像, 项目代码必须兼容 PHP 7.4。
