install with nginx podman 
=======

## install 
```sh 
sudo apt update
sudo apt install podman -y

podman --version


cd podman/pod/php7


# 生成镜像
podman build -t php7-npanel -f Containerfile .

# 生成容器 监听 9000 
podman run -d \
  --name php7-npanel \
  -p 9001:9000 \
  -v /var/www:/var/www:Z \
  --restart always \
  localhost/php7-npanel


# 查看是否运行
podman ps  


# nginx config 

# 确保nginx配置目录存在
sudo mkdir -p /etc/nginx/conf.d/
echo > /etc/nginx/conf.d/test-npanel.freessr.bid.conf 
nano /etc/nginx/conf.d/test-npanel.freessr.bid.conf 

echo >> ~/host.env 

# download ssl

```


写一个脚本 自动获取 证书! 

nginx config 
```conf 
# 1. 自动将 HTTP (80) 重定向到 HTTPS (443)
server {
    listen 80;
    server_name test-npanel.freessr.bid;
    return 301 https://$host$request_uri;
}

# 2. HTTPS 配置
server {
    listen 443 ssl http2;
    server_name test-npanel.freessr.bid;

    # 证书路径
    ssl_certificate     /etc/ssl/freessr.bid.pem;
    ssl_certificate_key /etc/ssl/freessr.bid.key;

    # 优化 SSL 安全设置 (推荐)
    ssl_session_timeout 1d;
    ssl_session_cache shared:MozSSL:10m;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # 网站根目录 (指向宿主机的 public)
    root /var/www/test-npanel.freessr.bid/public;
    index index.php index.html;

    # URL 重写
    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    # 处理 PHP
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass 127.0.0.1:9000;
        
        # 映射到容器内路径
        fastcgi_param SCRIPT_FILENAME /var/www/html/public$fastcgi_script_name;
    }

    # 安全限制
    location ~ /\.(ht|git|env) {
        deny all;
    }
}


```


```sh 

systemctl restart nginx 


```

导入数据库! 然后再下一步
```sh
cd /var/www/html # 改为真实的网站根目录



# 
mkdir -p storage/app
mkdir -p storage/framework/cache 
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p storage/framework/testing


# chmod -R a+x *
chmod -R 777 storage

# 生成密钥对 
# 进入容器执行: 
podman exec -it test-npanel /bin/bash 
php artisan key:generate 

```
