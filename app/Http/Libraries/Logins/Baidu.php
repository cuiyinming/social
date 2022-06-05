<?php

namespace App\Http\Libraries\Logins;

class Baidu
{
    private $loginUrl;
    private $uuid;
    private $apiUrl;
    private $userName;
    private $token;
    private $ucid;
    private $st;
    private $password;
    private $acc_type;
    private $header;
    private $pub_key;

    public function __construct($config = [])
    {
        $configs = empty($config) ? config('self.baidu') : $config;
        $this->loginUrl = $configs['login_url'];
        $this->uuid = $configs['uuid'];
        $this->apiUrl = $configs['api_url'];
        $this->userName = $configs['username'];
        $this->token = $configs['token'];
        $this->password = $configs['password'];
        $this->acc_type = $configs['acc_type'];
        $this->header = ['UUID: ' . $this->uuid, 'account_type: ' . $this->acc_type, 'Content-Type:  data/gzencode and rsa public encrypt;charset=UTF-8'];
        $this->pub_key = <<<EOT
-----BEGIN PUBLIC KEY-----

-----END PUBLIC KEY-----
EOT;
    }


    public function preLogin()
    {
        $data = array(
            'username' => $this->userName,
            'token' => $this->token,
            'functionName' => 'preLogin',
            'uuid' => $this->uuid,
            'request' => array(
                'osVersion' => 'windows',
                'deviceType' => 'pc',
                'clientVersion' => '1.0',
            ),
        );
        $gzData = gzencode(json_encode($data), 9);
        $enData = '';
        for ($index = 0; $index < strlen($gzData); $index += 117) {
            $gzPackData = substr($gzData, $index, 117);
            $enData .= $this->pubEncrypt($gzPackData);
        }
        $tmpInfo = $this->my_curl($this->loginUrl, $this->header, $enData);
        $returnCode = ord($tmpInfo[0]) * 64 + ord($tmpInfo[1]);
        if ($returnCode === 0) {
            $retData = substr($tmpInfo, 8);
            $retData = gzdecode($retData, strlen($retData));
            $retArray = json_decode($retData, true);
            if ($retArray['needAuthCode'] === false) {
                echo '[notice] preLogin successfully!' . PHP_EOL;
                return true;
            }
        }
    }

    /**
     * doLogin
     * @param string $userName
     * @param string $password
     * @param string $token
     * @return array
     */
    public function doLogin()
    {

        $data = array(
            'username' => $this->userName,
            'token' => $this->token,
            'functionName' => 'doLogin',
            'uuid' => $this->uuid,
            'request' => array(
                'password' => $this->password,
            ),
        );
        $gzData = gzencode(json_encode($data), 9);
        $enData = '';
        for ($index = 0; $index < strlen($gzData); $index += 117) {
            $gzPackData = substr($gzData, $index, 117);
            $enData .= $this->pubEncrypt($gzPackData);
        }
        $tmpInfo = $this->my_curl($this->loginUrl, $this->header, $enData);
        $returnCode = ord($tmpInfo[0]) * 64 + ord($tmpInfo[1]);
        if ($returnCode === 0) {
            $retData = substr($tmpInfo, 8);
            $retData = gzinflate(substr($retData, 10, -8));
            $retArray = json_decode($retData, true);
            if ($retArray['retcode'] === 0) {
                echo '[notice] doLogin successfully!' . PHP_EOL;
                $this->ucid = $retArray['ucid'];
                $this->st = $retArray['st'];
            }
        }
    }

    /**
     * doLogout
     * @param string $userName
     * @param string $token
     * @param string $ucid
     * @param string $st
     * @return boolean
     */
    public function doLogout()
    {

        $data = array(
            'username' => $this->userName,
            'token' => $this->token,
            'functionName' => 'doLogout',
            'uuid' => $this->uuid,
            'request' => array(
                'ucid' => $this->ucid,
                'st' => $this->st,
            ),
        );
        $gzData = gzencode(json_encode($data), 9);
        $enData = '';
        for ($index = 0; $index < strlen($gzData); $index += 117) {
            $gzPackData = substr($gzData, $index, 117);
            $enData .= $this->pubEncrypt($gzPackData);
        }
        $tmpInfo = $this->my_curl($this->loginUrl, $this->header, $enData);
        $returnCode = ord($tmpInfo[0]) * 64 + ord($tmpInfo[1]);
        if ($returnCode === 0) {
            $retData = substr($tmpInfo, 8);
            $retData = gzdecode($retData, strlen($retData));
            $retArray = json_decode($retData, true);
            if ($retArray['retcode'] === 0) {
                echo '[notice] doLogout successfully!' . PHP_EOL;
                return true;
            }
        }
    }


    public function getSiteList()
    {
        echo '----------------------get site list----------------------' . PHP_EOL;

        $apiConnectionData = array(
            'header' => array(
                'username' => $this->userName,
                'password' => $this->st,
                'token' => $this->token,
                'account_type' => $this->acc_type,
            ),
            'body' => null,
        );
        return $this->send($this->apiUrl . '/getSiteList', json_encode($apiConnectionData));
    }


    public function getData($parameters)
    {
        echo '----------------------get data----------------------' . PHP_EOL;

        $apiConnectionData = array(
            'header' => array(
                'username' => $this->userName,
                'password' => $this->st,
                'token' => $this->token,
                'account_type' => $this->acc_type,
            ),
            'body' => $parameters,
        );
        return $this->send($this->apiUrl . '/getData', json_encode($apiConnectionData));
    }

    public function send($url, $data)
    {
        $headers = ['UUID: ' . $this->uuid, 'USERID: ' . $this->ucid, 'Content-Type:  data/json;charset=UTF-8'];
        $tmpRet = $this->my_curl($url, $headers, $data);
        $tmpArray = json_decode($tmpRet, true);
        if (isset($tmpArray['header']) && isset($tmpArray['body'])) {
            return array(
                'header' => $tmpArray['header'],
                'body' => $tmpArray['body'],
                'raw' => $tmpRet,
            );
        }
    }

    public function pubEncrypt($data)
    {
        if (!is_string($data)) {
            return null;
        }
        $ret = openssl_public_encrypt($data, $encrypted, openssl_pkey_get_public($this->pub_key));
        if ($ret) {
            return $encrypted;
        } else {
            return null;
        }
    }

    public function my_curl($url, $headers, $data)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:16.0) Gecko/20100101 Firefox/16.0');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $tmpRet = curl_exec($curl);
        if (curl_errno($curl)) {
            echo '[error] CURL ERROR: ' . curl_error($curl) . PHP_EOL;
        }
        curl_close($curl);
        return $tmpRet;
    }

}
