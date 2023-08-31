<?php

namespace MyNamespace\BestIp;

use Exception;

use MyNamespace\TransferHistory\TransferHistory;

class BestIp
{
    private $use_proxy = false; // 是否使用代理
    private $proxy_opts = array(); // curl代理选项
    private $ip_count = 10; // 优选ip的数量
    private $ip_result_file = ''; // 优选ip的结果文件
    private $vmess_result_file = ''; // vmess订阅文件
    private $transfer_url = ''; // transfer短链接url
    private $transfer_delete_url = ''; // transfer删除url
    private $tiny_url = ''; // tinyurl上完整地址

    const SPEED_TEST_FILE = "https://sangfor.0x01.party/file";
    const TRANSFER_URL_BASE = "https://transfer.sh/";
    const TINY_URL_BASE = "https://tinyurl.com/";
    const CURL_DNS = '8.8.8.8';
    const CURL_VERBOSE = true;

    public function __construct(int $ip_count)
    {
        $this->ip_count = $ip_count;

        $tmp_dir = sys_get_temp_dir();
        $this->ip_result_file = $tmp_dir. DIRECTORY_SEPARATOR. 'result_hosts.txt';
        $this->vmess_result_file = $tmp_dir. DIRECTORY_SEPARATOR. 'vmess.txt';
    }

    // 优选ip
    public function call_st(string $bin_dir, int $sl, bool $slient_mode)
    {
        echo '>>> 优选IP'. PHP_EOL;
        if ($slient_mode) {
            $cmd = Sprintf('cd %s && ./CloudflareST -tl 300 -sl %d -dn %d -p %d -url https://sangfor.0x01.party/file -o %s 2>/dev/null', $bin_dir, $sl, $this->ip_count, $this->ip_count, $this->ip_result_file);
        } else {
            $cmd = Sprintf('cd %s && ./CloudflareST -tl 300 -sl %d -dn %d -p %d -url https://sangfor.0x01.party/file -o %s', $bin_dir, $sl, $this->ip_count, $this->ip_count, $this->ip_result_file);
        }
        echo $cmd. PHP_EOL;
        system($cmd, $ret);
        if ($ret !== 0 || !file_exists($this->ip_result_file)) {
            throw new Exception("run failed.");
        }
    } 

    // 生成订阅url，得到通知消息
    public function get_msg_vmess(array $tpl_arr, string $his_db_file, string $tiny_url_token, string $tiny_url_alias) : string
    {
        $db = new TransferHistory(APP_ROOT. $his_db_file);
        
        $this->gen_vmess($tpl_arr);

        if (!$this->transfer_file($this->vmess_result_file)) {
            throw new Exception("上传到transfer.sh失败");
        }

        $this->tiny_url = self::TINY_URL_BASE . $tiny_url_alias;

        // 删除旧的地址，写入新地址
        $records = $db->get_all();
        foreach ($records as $record) {
            if (isset($record['id']) && $record['id'] !== '') {
                if (!$this->delete_transfer_file($record['delete_url'])) {
                    echo "删除失败". PHP_EOL;
                    continue;
                }
                if (!$db->del_record($record['id'])) {
                    echo "删除失败". PHP_EOL;
                    continue;
                }
            }
        }
        $db->add_record($this->transfer_url, $this->tiny_url, $this->transfer_delete_url);

        if (!$this->update_tiny_url($tiny_url_token, $tiny_url_alias)) {
            throw new Exception("修正tinyurl失败");
        }
        
        return sprintf("%s => %s", self::TINY_URL_BASE. $tiny_url_alias, $this->transfer_url);
    }

    // 包含可用ip的通知消息
    public function get_msg_ips() : string
    {
        $ips = $this->get_ips($this->ip_result_file);
        if (!$ips) {
            throw new Exception("ip empty.");
        }
        return join("\n", array_slice($ips, 0, 10));
    }

    // 设置curl代理
    public function set_curl_socks5_proxy(string $host, int $port, bool $use_socks5)
    {
        if (getenv('https_proxy')) {
            echo 'proxy already set, ignore.'. PHP_EOL;
            return;
        }
        $this->use_proxy = true;
        $this->proxy_opts = array(
            CURLOPT_PROXY => $host,
            CURLOPT_PROXYPORT => $port,
            CURLOPT_PROXYTYPE => $use_socks5 ? CURLPROXY_SOCKS5 : CURLPROXY_HTTP,
            CURLOPT_HTTPPROXYTUNNEL => true,
        );
    }


    // 发送telegram通知消息
    public function send_message(string $token, string $chat_id, string $msg): bool
    {
        echo '>>> 发送消息：'. $msg. PHP_EOL;
        $url = sprintf('https://api.telegram.org/bot%s/sendMessage?%s', $token, http_build_query(array(
            'chat_id' => $chat_id,
            'text' => $msg,
        )));

        
        $opt_array = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_DNS_LOCAL_IP4 => self::CURL_DNS,
            CURLOPT_VERBOSE => self::CURL_VERBOSE,
        );
        if ($this->use_proxy) {
            $opt_array += $this->proxy_opts;
        }
        $opt_array['CURLOPT_RESOLVE'] = $this->get_url_dns($url);

        $ch = curl_init();
        curl_setopt_array($ch, $opt_array);
        $result = curl_exec($ch);
        $err_code = curl_errno($ch);
        $err_msg = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err_code === CURLE_OK && $http_code === 200) {
            return true;
        }

        printf("errno: %d, err: %s, http code: %d, result: %s". PHP_EOL, $err_code, $err_msg, $http_code, $result);
        return false;
    }

    // 从优选ip结果中读取ip
    private function get_ips(string $file): array
    {
        $ips = array();
        $row = 0;
        if (($handle = fopen($file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;
                if ($row === 1) continue; // 忽略首行头部

                if (isset($data[0])) {
                    $ips[] = $data[0];
                }
            }
            fclose($handle);
        }

        return $ips;
    }


    // 上传vmess订阅文件
    // curl --upload-file ./vmess.txt https://transfer.sh/vmess.txt
    public function transfer_file(string $file): bool
    {
        echo '>>> 上传到transer.sh'. PHP_EOL;

        $url = self::TRANSFER_URL_BASE . basename($this->vmess_result_file);
        $opt_array = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_POST => true,
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => fopen($this->vmess_result_file, 'r'),
            CURLOPT_INFILESIZE => filesize($this->vmess_result_file),
            CURLOPT_HTTPHEADER => array(
                'Max-Downloads: 100',
                'Max-Days: 7',
            ),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_DNS_LOCAL_IP4 => self::CURL_DNS,
            CURLOPT_VERBOSE => self::CURL_VERBOSE,
        );
        if ($this->use_proxy) {
            $opt_array += $this->proxy_opts;
        }
        $opt_array['CURLOPT_RESOLVE'] = $this->get_url_dns($url);

        $ch = curl_init();
        curl_setopt_array($ch, $opt_array);
        $response = curl_exec($ch);
        $err_code = curl_errno($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err_msg = curl_error($ch);
        curl_close($ch);

        if ($err_code === CURLE_OK && $http_code === 200) {
            // 从头部获取删除地址
            // x-url-delete: https://transfer.sh/Gfvh2vHetO/hello.txt/INBwy5yQ0kw5tgGbdA2M
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);
            
            $this->transfer_url =  $body;
            // echo 'transfer_url:'. $this->transfer_url. PHP_EOL;
            $lines = explode("\r\n", $header);
            foreach ($lines as $line) {
                $parts = explode(':', $line, 2);
                if (count($parts) == 2) {
                    $name = trim($parts[0]);
                    $value = trim($parts[1]);
                    if ($name === 'x-url-delete') {
                        $this->transfer_delete_url = $value;
                        // echo 'transfer_delete_url:'. $this->transfer_delete_url. PHP_EOL;
                        break;
                    }
                }
            }

            return true;
        }

        printf("errno: %d, err: %s, http code: %d, result: %s". PHP_EOL, $err_code, $err_msg, $http_code, $response);
        return false;
    }

    // 删除文件
    function delete_transfer_file($delete_url): bool
    {
        echo '>>> 删除transer.sh上的文件：'. $delete_url. PHP_EOL;
        if (empty($delete_url)) {
            return true;
        }
        
        $opt_array = array(
            CURLOPT_URL => $delete_url,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_DNS_LOCAL_IP4 => self::CURL_DNS,
            CURLOPT_VERBOSE => self::CURL_VERBOSE,
        );
        if ($this->use_proxy) {
            $opt_array += $this->proxy_opts;
        }
        $opt_array['CURLOPT_RESOLVE'] = $this->get_url_dns($delete_url);

        $ch = curl_init();
        curl_setopt_array($ch, $opt_array);
        $result = curl_exec($ch);
        $err_code = curl_errno($ch);
        $err_msg = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err_code === CURLE_OK && $http_code === 200) {
            return true;
        }

        printf("errno: %d, err: %s, http code: %d, result: %s". PHP_EOL, $err_code, $err_msg, $http_code, $result);
        return false;
    }

    // 生成vmess订阅文件
    public function gen_vmess(array $tpl_arr)
    {
        echo '>>> 生成vmess订阅文件'. PHP_EOL;
        if (!file_exists($this->ip_result_file)) {
            throw new Exception("ip file not exist, check first.");
        }
        $ips = $this->get_ips($this->ip_result_file);
        if (!$ips) {
            throw new Exception("ip empty.");
        }

        if (file_exists($this->vmess_result_file)) {
            unlink($this->vmess_result_file);
        }

        $items = array();
        foreach ($ips as $k => $ip) {
            if ($k >= $this->ip_count) break;

            $tpl_arr["ps"] = sprintf('vmess_%d', $k + 1);
            $tpl_arr["add"] = $ip;
            $items[] = 'vmess://'. base64_encode(json_encode($tpl_arr));
        }

        $ret =  file_put_contents($this->vmess_result_file, base64_encode(join(PHP_EOL, $items)));
        if (is_bool($ret) && !$ret) {
            throw new Exception("file write failed.");
        }
    }

    // 查询dns
    private function get_url_dns(string $url): string {
        if (getenv('https_proxy')) {
            echo 'proxy already set, ignore.'. PHP_EOL;
            return '';
        }

        $url_info = parse_url($url);
        if (!isset($url_info['port'])) {
            switch ($url_info['scheme']) {
                case 'https':
                    $url_info['port'] = 443;
                    break;
                case 'http':
                    $url_info['port'] = 80;
                    break;
                default:
                    throw new Exception('url unsupport.');
            }
        }
        $retry = 3;
        while($retry--) {
            $ip = gethostbyname($url_info['host']);
            if ($ip !== $url_info['host']) {
                return sprintf('%s:%d:%s', $url_info['host'], $url_info['port'], $ip);
            }
        }
        throw new Exception('dns get failed.');
    }

    // 修改短链接，需要先创建
    public function update_tiny_url(string $token, string $url_alias): bool
    {
        echo '>>> 修正短链接'. PHP_EOL;
        // https://api.tinyurl.com/alias/tinyurl.com/hubery-vmess?api_token=1tHCY9mTHvXDJOhvgdzhDgDwbhp2llPfwrcVgyGXLjX1EldOgDq3rH5EXDRw
        
        $json_data = json_encode(array(
            "url" => $this->transfer_url,
            "domain" => "tinyurl.com",
            "alias" => $url_alias,
        ));
        $url = 'https://api.tinyurl.com/change?api_token=' . $token;
        $opt_array = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => $json_data,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_data)
            ),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_DNS_LOCAL_IP4 => self::CURL_DNS,
            CURLOPT_VERBOSE => self::CURL_VERBOSE,
        );
        if ($this->use_proxy) {
            $opt_array += $this->proxy_opts;
        }
        $opt_array['CURLOPT_RESOLVE'] = $this->get_url_dns($url);

        $ch = curl_init();
        curl_setopt_array($ch, $opt_array);
        $result = curl_exec($ch);
        $err_code = curl_errno($ch);
        $err_msg = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err_code === CURLE_OK && $http_code === 200) {
            return true;
        }

        printf("errno: %d, err: %s, http code: %d, result: %s". PHP_EOL, $err_code, $err_msg, $http_code, $result);
        return false;
    }
}