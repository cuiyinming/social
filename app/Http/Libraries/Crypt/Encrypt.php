<?php

namespace App\Http\Libraries\Crypt;

class Encrypt
{

    public function __construct()
    {

    }

    private function is_all_ascii($text)
    {
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $code = ord($text[$i]);
            if ($code < 32 || $code > 126) return false;
        }
        return true;
    }


    private function my_base64_encode($s)
    {
        $custom_map = "ABCDEFXYZGHIUVWJKLRSTMNOPQabcdefxyzghiuvwjklrstmnopq0123456789+/=";
        $default_map = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
        $b64 = base64_encode($s);

        $a64 = str_split($b64);

        $map = array_combine(str_split($default_map), str_split($custom_map));
        $final = "";
        foreach ($a64 as $v) {
            $final .= $map[$v];
        }
        return $final;
    }


    private function encode_header($h)
    {
        $t[0] = '!';
        $t[1] = '!';
        $t[2] = '&';
        $t[3] = 'c';
        $t[4] = '$';
        $t[5] = '%';
        $t[6] = '^';
        $t[7] = '#';
        $t[8] = chr($h['version'] + 32);
        $t[9] = chr($h['level'] + 57);
        $t[10] = chr($h['type'] + 90);
        $t[11] = chr(33);
        $t[12] = chr($h['head_length'] - 16 + 33);
        $tt = join('', $t);
        $tt .= $this->encode_int4($h['encode_length']);
        $tt .= $this->encode_int4($h['text_length']);
        return $tt;
    }

    private function encode_int4($v)
    {
        $D_MODEL_3 = 857375;
        $D_MODEL_2 = 9025;
        $D_MODEL_1 = 95;
        $e = intval($v / $D_MODEL_3);
        if ($e > 0) $v -= $e * $D_MODEL_3;
        $a = intval($v / $D_MODEL_2);
        if ($a > 0) $v -= $a * $D_MODEL_2;
        $b = intval($v / $D_MODEL_1);
        if ($b > 0) $v -= $b * $D_MODEL_1;

        return chr($e + 33) . chr($a + 33) . chr($b + 33) . chr($v + 33);
    }

    private function tableXor($key, $s)
    {
        $len_s = strlen($s);
        $len_k = strlen($key);
        $new = "";
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


    public function encrypt($text, $pubKey, $type, $version = 1)
    {
        $level = $this->is_all_ascii($text) ? 1 : 2;

        //构造文件头
        $headerInfo = array(
            'version' => $version,
            'level' => $level,
            'type' => $type,
            'head_length' => 21,
            'text_length' => strlen($text),
        );
        if ($level == 2) {
            $text = $this->my_base64_encode($text);
        }

        $headerInfo['encode_length'] = strlen($text);
        return $this->encode_header($headerInfo) . $this->tableXor($pubKey, $text);
    }
}
