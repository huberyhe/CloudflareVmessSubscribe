<?php
require 'vendor/autoload.php';

use MyNamespace\BestIp\BestIp;
use MyNamespace\TransferHistory\TransferHistory;

const APP_ROOT = __DIR__;
const CONFIG_PATH = APP_ROOT . DIRECTORY_SEPARATOR. 'config/config.ini';
const IP_COUNT = 10;

function main(array $options)
{
    $config = read_config(CONFIG_PATH);

    $db = new TransferHistory(__DIR__ . DIRECTORY_SEPARATOR. $config["db_file"]);
    $obj = new BestIp(IP_COUNT);
    if ($config["curl_proxy"] === 'socks5') {
        $obj->set_curl_socks5_proxy($config['curl_proxy_ip'], $config['curl_proxy_port']);
    }

    try {
        if (isset($options["a"])) {
            $obj->call_st(__DIR__ . DIRECTORY_SEPARATOR . 'CloudflareST/');
        }

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
    } catch (\Exception $e) {
        printf("%s:%d 发生错误： %s", $e->getFile(), $e->getLine(), $e->getMessage());
    }
}

$options = getopt("a");
main($options);