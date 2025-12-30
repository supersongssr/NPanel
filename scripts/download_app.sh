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

# macos intel 64
wget -N https://github.com/yanue/V2rayU/releases/download/v4.2.8/V2rayU-64.dmg

# macos arm 64
wget -N https://github.com/yanue/V2rayU/releases/download/v4.2.8/V2rayU-arm64.dmg


# Windows
wget -N https://github.com/2dust/v2rayN/releases/download/7.16.8/v2rayN-windows-64-desktop.zip

# v2rayng
wget -N https://github.com/2dust/v2rayNG/releases/download/1.10.31/v2rayNG_1.10.31_arm64-v8a.apk
ln -f v2rayNG_1.10.31_arm64-v8a.apk v2rayNG_arm64-v8a.apk
