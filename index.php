<?php
require 'vendor/autoload.php';

use MyNamespace\BestIp\BestIp;

const APP_ROOT = __DIR__. DIRECTORY_SEPARATOR;
const CONFIG_PATH = APP_ROOT. 'config/config.ini';
const IP_COUNT = 10;

date_default_timezone_set('Asia/Shanghai');

function main(array$opts)
{
    
    $to_check = $to_send_vmess_msg = $to_send_ips_msg = false;
    if (isset($opts['a'])) {
        $to_check = $to_send_vmess_msg = $to_send_ips_msg = true;
    } else {
        if (isset($opts['check'])) {
            $to_check = true;
        }
        if (isset($opts['vmess'])) {
            $to_send_vmess_msg = true;
        }
        if (isset($opts['ips'])) {
            $to_send_ips_msg = true;
        }
    }

    echo '开始：'. date('Y/m/d H:i:s'). PHP_EOL;
    $config = read_config(CONFIG_PATH);

    $obj = new BestIp($config['ip_count'] ?? IP_COUNT);
    switch ($config['curl_proxy']) {
        case 'socks5':
            $obj->set_curl_socks5_proxy($config['curl_proxy_ip'], $config['curl_proxy_port'], true);
            break;
        case 'http':
            $obj->set_curl_socks5_proxy($config['curl_proxy_ip'], $config['curl_proxy_port'], false);
            break;
        default:
            break;
    }
    try {
        if ($to_check) {
            $obj->call_st(APP_ROOT . $config['cloudflare_st_path'], $config['speed_limit'] ?? 3, $to_send_vmess_msg | $to_send_ips_msg);
        }

    } catch (\Exception $e) {
        printf("%s:%d 发生错误： %s". PHP_EOL, $e->getFile(), $e->getLine(), $e->getMessage());
        return;
    }
    
    try {

        if ($to_send_vmess_msg) {
            $tpl_arr = json_decode($config["vmess_tpl"], true);
            $msg = $obj->get_msg_vmess($tpl_arr, $config["db_file"], $config['tiny_url_token'], $config['tiny_url_alias']);
            if (!$obj->send_message($config['telegram_token'], $config['telegram_chat_id'], $msg)) {
                throw new Exception("send failed.");
            }
        }

    } catch (\Exception $e) {
        printf("%s:%d 发生错误： %s". PHP_EOL, $e->getFile(), $e->getLine(), $e->getMessage());
    }
    try {

        if ($to_send_ips_msg) {
            $msg = $obj->get_msg_ips();
            if (!$obj->send_message($config['telegram_token'], $config['telegram_chat_id'], $msg)) {
                throw new Exception("send failed.");
            }
        }

    } catch (\Exception $e) {
        printf("%s:%d 发生错误： %s". PHP_EOL, $e->getFile(), $e->getLine(), $e->getMessage());
    }

    echo '完成：'. date('Y/m/d H:i:s'). PHP_EOL;
}

$opts = getopt("ah", array('check', 'vmess', "ips"));
if (isset($opts['h'])) {
    echo <<<EOT
usage: php $argv[0] [OPTION]
    -a check ip and update vmess.
    --check check ip.
    --vmess send vmess message
    --ips send ip list message
    -h display this help.

EOT;
    exit;
}


main($opts);