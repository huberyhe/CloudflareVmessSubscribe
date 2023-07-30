#!/usr/bin/env bash
CURDIR=$(cd "$(dirname "$0")" && pwd)

. set_proxy.sh

# 下载CloudflareST
# https://github.com/XIU2/CloudflareSpeedTest/releases/download/v2.2.4/CloudflareST_linux_amd64.tar.gz
if [[ ! -f ${CURDIR}/CloudflareST/CloudflareST ]]; then
    mkdir -p ${CURDIR}/CloudflareST && cd ${CURDIR}/CloudflareST

    if [[ "$(uname)"=="Darwin" ]];then
        url=https://github.com/XIU2/CloudflareSpeedTest/releases/download/v2.2.4/CloudflareST_darwin_amd64.zip
        wget -N ${url} -q
        unzip -oq CloudflareST_darwin_amd64.zip && rm -f CloudflareST_darwin_amd64.zip
    else
        url=https://github.com/XIU2/CloudflareSpeedTest/releases/download/v2.2.4/CloudflareST_linux_amd64.tar.gz
        wget -N ${url} -q
        tar -zxf CloudflareST_linux_amd64.tar.gz && rm -f CloudflareST_linux_amd64.tar.gz
    fi

    chmod a+x ./CloudflareST
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