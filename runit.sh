#!/usr/bin/env bash
CURDIR=$(cd "$(dirname "$0")" && pwd)

. set_proxy.sh

# 下载CloudflareST
# https://github.com/XIU2/CloudflareSpeedTest/releases/download/v2.2.4/CloudflareST_linux_amd64.tar.gz
if [[ ! -f ${CURDIR}/CloudflareST/CloudflareST ]]; then
    url=https://github.com/XIU2/CloudflareSpeedTest/releases/download/v2.2.4/CloudflareST_linux_amd64.tar.gz
    if ["$(uname)"=="Darwin"];then
        url=https://github.com/XIU2/CloudflareSpeedTest/releases/download/v2.2.4/CloudflareST_darwin_amd64.tar.gz
    fi

    mkdir -p ${CURDIR}/CloudflareST && cd ${CURDIR}/CloudflareST

    wget -N https://ghproxy.com/${url} -q -O CloudflareST.tar.gz
    tar -zxf CloudflareST.tar.gz && rm -f CloudflareST.tar.gz
    chmod a+x CloudflareST
else
    cd ${CURDIR}/CloudflareST/

    ./CloudflareST -v
fi
wget https://www.cloudflare.com/ips-v4 -q -O ip.txt
cd $CURDIR

# composer update
composer update

# 清除代理，优选ip
php index.php -c

# 设置代理，上传订阅文件
php index.php -u

. clear_proxy.sh