# 1 功能

背景：我自己搭建vmess的vps被墙后，使用了CloudFlare的CDN代理拯救了vps。CDN的IP并不是一直可用，需要经常使用CloudFlareST工具优选IP，为了避免手动重复这些复杂的工作，就有了这个工具，每天定时执行优选IP并生成vmess订阅配置，这样小火箭就可以自动更新订阅，保证vmess节点一直是可用。

工具借助了[CloudflareST](https://github.com/XIU2/CloudflareSpeedTest)来优选IP，订阅文件上传到了[transfer.sh](https://transfer.sh/)，由于transfer.sh的url是变化的，就用[tinyurl](https://tinyurl.com/)将一个固定的url指向transfer.sh的url，然后发送到telegram。

# 2 使用方法

## 2.1 工具需要准备如下：
- 1. vmess模板配置。生成vmess订阅其实就是复制模板成多个，并替换模板的ip
- 2. tinyurl的token。去官网免费注册申请
- 3. telegram的Bot token和user id。用于发送消息到手机，可在 @BotFather 注册一个新Bot并得到token，通过 @userinfobot 获取user id
- 4. 代理。tinyurl和telegram被墙，需要代理才能用
- 5. CloudFlareST优选工具，放到`CloudflareST`目录下

## 2.2 准备好后修改`/config/config.ini`

```yaml
curl_proxy = ""
curl_proxy_ip = ""
curl_proxy_port = 19820
db_file = "config/db.sqlite"
vmess_tpl = ""
telegram_token = "123:abc"
telegram_chat_id = 123
tiny_url_token = "abc"
ip_count = 10
speed_limit = 3
cloudflare_st_path = "CloudflareST/"
```

## 2.3 开始优选IP，并生成订阅，上传到transfer.sh

```bash
php index.php -a
```

其他命令

```bash
# 优选IP
php index.php --check

# 生成mvess订阅文件，并上传到transfer.sh，发送通知到telegram
php index.php --vmess

# 发送优选的IP列表到telegram
php index.php --ips
```