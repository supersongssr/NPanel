#!/bin/sh
#

command -v wget  || {
    echo 'error wget 没安装'
    exit 1
}

[ -d public/clients ] || {
    echo 'error public/clients 目录不存在'
    exit 1
}

cd public/clients || {
    echo "error 0009 请在网站根目录运行"
    echo "debug 当前工作目录: $PWD"
    exit 1
}

wget -N https://github.com/2dust/v2rayNG/releases/download/2.2.6/v2rayNG_2.2.6_arm64-v8a.apk
ln -f v2rayNG_2.2.6_arm64-v8a.apk v2rayNG_arm64-v8a.apk
