<?php

namespace App\Console\Commands\Spider;

use App\Http\Models\Lib\LibBioTextModel;
use Curl\Curl;
use Illuminate\Console\Command;
use QL\QueryList;
use App\Http\Helpers\{R, HR};

class spider extends Command
{

    protected $signature = 'spider {type?}';
    protected $description = '爬虫请求';
    private static $curl;
    private static $cookiePath;

    public function __construct()
    {
        $curlBuilder = new Curl();
//        $curlBuilder->setCookieFile(self::$cookiePath);
//        $curlBuilder->setCookieJar(self::$cookiePath);
        $curlBuilder->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        self::$curl = $curlBuilder;
        parent::__construct();
    }

    public function handle()
    {
        $type = $this->argument('type') ?: 0;
        if (in_array($type, [0, 1])) {
            $arrs = $this->_spiderBioType(1);
            foreach ($arrs as $arr) {
                for ($i = 1; $i <= 200; $i++) {
                    try {
                        $this->_spiderBio($i, $arr);
                    } catch (\Exception $e) {
                        echo $e->getMessage() . PHP_EOL;
                    }

                }
            }

        }

//        if (in_array($type, [0, 2])) {
//            dd(LibBioTextModel::getRandTextBio(1));
//        }
    }

    private function _spiderBioType($page)
    {
        $header = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
            'Connection: keep-alive',
            'Pragma: no-cache',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Site: cross-site',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36',
        ];
        $url = 'http://www.qqgexingqianming.com/shanggan/' . $page . '.htm';
        self::$curl->setOpt(CURLOPT_HTTPHEADER, $header);
        $html = self::$curl->get($url);
        try {
            $ql = QueryList::html($html);
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
        $rules = [
            'cont' => ['.hd-tab a', 'text'],
            'href' => ['.hd-tab a', 'href'],
        ];
        $result = $ql->rules($rules)->query()->getData(function ($item) {
            $item['cont'] = str_replace('QQ', '', $item['cont']);
            $item['cont'] = str_replace('个性签名', '', $item['cont']);
            $item['href'] = str_replace('/', '', $item['href']);
            return $item;
        });
        $res = [];
        foreach ($result as $re) {
            if ($re['href'] == 'qinglv') continue;
            $res[] = $re;
        }
        return $res;
    }


    private function _spiderBio($page, $arr)
    {
        $header = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
            'Connection: keep-alive',
            'Pragma: no-cache',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Site: cross-site',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36',
        ];
        $url = 'http://www.qqgexingqianming.com/' . $arr['href'] . '/' . $page . '.htm';

        echo '第' . $page . '页' . PHP_EOL;
        //dd($header,$url);
//        self::$curl->setCookieFile(self::$cookiePath);
//        self::$curl->setCookieJar(self::$cookiePath);
        self::$curl->setOpt(CURLOPT_HTTPHEADER, $header);
        $html = self::$curl->get($url);
        try {
            $ql = QueryList::html($html);
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
        $rules = [
            'cont' => ['.list li p', 'text'],
        ];
        $result = $ql->rules($rules)->query()->getData(function ($item) {
            return $item;
        });
        foreach ($result as $con) {
            if (empty($con['cont'])) {
                continue;
            }
            LibBioTextModel::updateOrCreate([
                'sign' => md5($con['cont']),
            ], [
                'sign' => md5($con['cont']),
                'content' => $con['cont'],
                'main' => $arr['cont'] . '个签',
                'desc' => $arr['cont'],
            ]);
        }


    }


    //防止克隆对象
    private function __clone()
    {

    }

    public function __destruct()
    {
        self::$curl->close();
    }

    //获取实例
    static public function getInstance()
    {
        //判断$instance是否是Uni的对象
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

}
