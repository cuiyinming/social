<?php

namespace App\Http\Libraries\Crypt;
class Rsa
{
    /**
     * 获取私钥
     * @return bool|resource
     */
    private static function getPrivateKey($key = 0)
    {
        if ($key == 0) {
            $content = config('subscribe.fast_login')['pri_key'];
        } else {
            $content = config('subscribe.api_rsa')['pri_key'];
        }
        return openssl_pkey_get_private($content);
    }

    /**
     * 获取公钥
     * @return bool|resource
     */
    private static function getPublicKey($key = 0)
    {
        if ($key == 0) {
            $content = config('subscribe.fast_login')['pub_key'];
        } else {
            $content = config('subscribe.api_rsa')['pub_key'];
        }
        return openssl_pkey_get_public($content);
    }

    /**
     * 私钥加密
     * @param string $data
     * @return null|string
     */
    public static function privEncrypt($data = '', $key = 0)
    {
        if (!is_string($data)) {
            return null;
        }
        return openssl_private_encrypt($data, $encrypted, self::getPrivateKey($key)) ? base64_encode($encrypted) : null;
    }

    /**
     * 公钥加密
     * @param string $data
     * @return null|string
     */
    public static function publicEncrypt($data = '', $key = 0)
    {
        if (!is_string($data)) {
            return null;
        }
        return openssl_public_encrypt($data, $encrypted, self::getPublicKey($key)) ? base64_encode($encrypted) : null;
    }

    /**
     * 私钥解密
     * @param string $encrypted
     * @return null
     */
    public static function privDecrypt($encrypted = '', $key = 0)
    {
        if (!is_string($encrypted)) {
            return null;
        }
        return openssl_private_decrypt(base64_decode($encrypted), $decrypted, self::getPrivateKey($key)) ? $decrypted : null;
    }

    /**
     * 公钥解密
     * @param string $encrypted
     * @return null
     */
    public static function publicDecrypt($encrypted = '', $key = 0)
    {
        if (!is_string($encrypted)) {
            return null;
        }
        return (openssl_public_decrypt(base64_decode($encrypted), $decrypted, self::getPublicKey($key))) ? $decrypted : null;
    }
}
