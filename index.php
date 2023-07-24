<?php
require 'vendor/autoload.php';

use MyNamespace\BestIp\BestIp;
use MyNamespace\TransferHistory\TransferHistory;

const APP_ROOT = __DIR__. DIRECTORY_SEPARATOR;
const CONFIG_PATH = APP_ROOT. 'config/config.ini';
const IP_COUNT = 10;

function main(bool $to_check, bool $to_update)
{
    $config = read_config(CONFIG_PATH);

    $db = new TransferHistory(APP_ROOT. $config["db_file"]);
    $obj = new BestIp($config['ip_count'] ?? IP_COUNT);
    if ($config["curl_proxy"] === 'socks5') {
        $obj->set_curl_socks5_proxy($config['curl_proxy_ip'], $config['curl_proxy_port']);
    }

    try {
        if ($to_check) {
            $obj->call_st(APP_ROOT . $config['cloudflare_st_path'], $config['speed_limit'] ?? 3);
        }

        if ($to_update) {
            $obj->gen_vmess();

            $ok = $obj->transfer_file();
            if (!$ok) {
                echo "上传失败，退出" . PHP_EOL;
                return;
            }
    
            // 删除旧的地址，写入新地址
            $records = $db->get_all();
            foreach ($records as $record) {
                if (isset($record['id']) && $record['id'] !== '') {
                    if (!$obj->delete_transfer_file($record['delete_url'])) {
                        echo "删除失败". PHP_EOL;
                        continue;
                    }
                    if (!$db->del_record($record['id'])) {
                        echo "删除失败". PHP_EOL;
                        continue;
                    }
                }
            }
            $db->add_record($obj->get_transfer_url(), $obj->get_tiny_url(), $obj->get_transfer_delete_url());
    
            $obj->update_tiny_url($config['tiny_url_token']);
    
            $ok = $obj->send_message($config['telegram_token'], $config['telegram_chat_id']);
            echo $ok ? "发送成功" . PHP_EOL : "发送失败" . PHP_EOL;
        }
    } catch (\Exception $e) {
        printf("%s:%d 发生错误： %s", $e->getFile(), $e->getLine(), $e->getMessage());
    }
}

$opts = getopt("acuh");
if (isset($opts['h'])) {
    echo <<<EOT
usage: php $argv[0] [OPTION]
    -a check ip and update vmess.
    -c check ip.
    -u update vmess.
    -h display this help.

EOT;
    exit;
}

$to_check = $to_update = false;
if (isset($opts['a'])) {
    $to_check = $to_update = true;
} else {
    if (isset($opts['c'])) {
        $to_check = true;
    }
    if (isset($opts['u'])) {
        $to_update = true;
    }
}
main($to_check, $to_update);