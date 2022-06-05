<?php

namespace App\Http\Libraries\Crypt;

class Decrypt
{


    public function __construct()
    {

    }

    private function decode_header($h)
    {
        //前8个随机数不管
        $version = ord($h[8]) - 32;
        $level = ord($h[9]) - 57;
        $type = ord($h[10]) - 90;
        $headLength = ord($h[12]) + 16 - 33;
        $encodeLength = $this->decode_int4(substr($h, 13, 4));
        $textLength = $this->decode_int4(substr($h, 17, 4));

        return array(
            'version' => $version,
            'level' => $level,
            'type' => $type,
            'head_length' => $headLength,
            'text_length' => $textLength,
            'encode_length' => $encodeLength
        );
    }

    private function decode_int4($v)
    {
        $D_MODEL_3 = 857375;
        $D_MODEL_2 = 9025;
        $D_MODEL_1 = 95;

        $e = ord($v[0]) - 33;
        $a = ord($v[1]) - 33;
        $b = ord($v[2]) - 33;
        $d = ord($v[3]) - 33;

        if ($e > 0) {
            $d += $e * $D_MODEL_3;
        }
        if ($a > 0) {
            $d += $a * $D_MODEL_2;
        }

        if ($b > 0) {
            $d += $b * $D_MODEL_1;
        }

        return $d;
    }


    private function my_base64_decode($s)
    {
        $custom_map = "ABCDEFXYZGHIUVWJKLRSTMNOPQabcdefxyzghiuvwjklrstmnopq0123456789+/=";
        $default_map = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
        $map = array_combine(str_split($custom_map), str_split($default_map));

        $s1 = '';
        $len = strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $s1 .= $map[$s[$i]];
        }

        return base64_decode($s1);
    }


    private function tableXorDecode($key, $s, $len_s)
    {

        $len_k = strlen($key);
        $new = "";
        //dd($key, $s, $len_s);
        if ($len_s > strlen($s)) {
            $len_s = strlen($s);
        }
        for ($i = 0; $i < $len_s; $i++) {
            $j = $i % $len_k;
            $temp = chr(ord($s[$i]) ^ ord($key[$j]) % 32);
            if (ord($temp) < 127) {
                $new .= $temp;
            } else {
                $new .= $s[$i];
            }
        }
        return $new;
    }

    public function decrypt($text, $pubKey)
    {
        $decodeHeader = $this->decode_header($text);
        //加密的字符串，去除header部分的21个字符
        $text = substr($text, $decodeHeader['head_length']);
        $xorDecode = $this->tableXorDecode($pubKey, $text, $decodeHeader['encode_length']);
        if ($decodeHeader['level'] == 2) {
            $xorDecode = $this->my_base64_decode($xorDecode);
        }

        return $xorDecode;
    }

}
