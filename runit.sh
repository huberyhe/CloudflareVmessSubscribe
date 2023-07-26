#!/usr/bin/env bash

# 下载CloudflareST
# https://github.com/XIU2/CloudflareSpeedTest/releases/download/v2.2.4/CloudflareST_linux_amd64.tar.gz
# composer update

# 清除代理，优选ip
. clear_proxy.sh
php index.php -c

# 设置代理，上传订阅文件
. set_proxy.sh
php index.php -u

. clear_proxy.sh