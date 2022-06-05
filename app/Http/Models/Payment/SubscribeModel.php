<?php

namespace App\Http\Models\Payment;

use App\Http\Helpers\H;
use Illuminate\Database\Eloquent\Model;

class SubscribeModel extends Model
{
    public static function getRightByName($vip_type, $re_purchase): array
    {
        $price_gear = config('subscribe.vip_list')[$vip_type];
        foreach ($price_gear as $k => &$gear) {
            if (CHANNEL == 'android' && in_array($gear['id'], ['quzhi701', 'quzhi401', 'quzhi501', 'quzhi201', 'quzhi301'])) {
                unset($price_gear[$k]);
                continue;
            }
            unset($gear['type']);
            $gear['product_id'] = $gear['id'];
            $gear['discount'] = $gear['origin_price'] - $gear['price'];
            $gear['discount'] = '';
        }
        //vip 权限区别
        $right_str = '开通VIP享专属特权';
        $right_list = [];
        if ($vip_type == 'swordsman') {
            $right_name = '包周';
            $right_str = '心友包周专属特权5/12';
        }
        if ($vip_type == 'knight') {
            $right_name = '包月';
            $right_str = '心友包月专属特权9/12';
        }
        if ($vip_type == 'suzerain') {
            $right_name = '包季';
            $right_str = '心友包季专属特权11/12';
        }
        if ($vip_type == 'lord') {
            $right_name = '包年';
            $right_str = '心友包年专属特权12/12';
        }
        if ($vip_type == 'swordsman') {
            $right = self::getRightTimes(1);
            $right_list = [
                ['name' => '查看访客', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ckfk.png'],
                ['name' => '专属礼物', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zslw.png'],
                ['name' => 'VIP标识', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vbs.png'],
                ['name' => '急速注销', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jszx.png'],
                ['name' => '超级曝光' . $right['super_show'] . '次', 'icon' => 'http://static.hfriend.cn/vips/icon_4_cjbg.png'],
                ['name' => '赠送' . $right['reward'] . '友币', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsjb.png'],
                ['name' => '解锁联系方式' . $right['contact'] . '/日', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jslx.png'],
            ];
        }
        if ($vip_type == 'knight') {
            $right = self::getRightTimes(4);
            $right_list = [
                ['name' => '查看访客', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ckfk.png'],
                ['name' => '专属礼物', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zslw.png'],
                ['name' => 'VIP标识', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vbs.png'],
                ['name' => '急速注销', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jszx.png'],
                ['name' => '解锁联系方式' . $right['contact'] . '/日', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jslx.png'],
                ['name' => '超级曝光' . $right['super_show'] . '次', 'icon' => 'http://static.hfriend.cn/vips/icon_4_cjbg.png'],
                ['name' => '赠送' . $right['reward'] . '友币', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsjb.png'],
                ['name' => '开通广播', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ktgb.png'],
                ['name' => '昵称变红', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsnc.png'],
            ];
        }
        if ($vip_type == 'suzerain') {
            $right = self::getRightTimes(7);
            $right_list = [
                ['name' => '查看访客', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ckfk.png'],
                ['name' => '专属礼物', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zslw.png'],
                ['name' => 'VIP标识', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vbs.png'],
                ['name' => '急速注销', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jszx.png'],
                ['name' => '解锁联系方式' . $right['contact'] . '/日', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jslx.png'],
                ['name' => '超级曝光' . $right['super_show'] . '次', 'icon' => 'http://static.hfriend.cn/vips/icon_4_cjbg.png'],
                ['name' => '赠送' . $right['reward'] . '友币', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsjb.png'],
                ['name' => '开通广播', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ktgb.png'],
                ['name' => '专属昵称色', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsnc.png'],
                ['name' => '隐身模式', 'icon' => 'http://static.hfriend.cn/vips/icon_4_dtys.png'],
                ['name' => 'VIP客服', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vkha.png'],
            ];
        }
        if ($vip_type == 'lord') {
            $right = self::getRightTimes(10);
            $right_list = [
                ['name' => '查看访客', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ckfk.png'],
                ['name' => '专属礼物', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zslw.png'],
                ['name' => 'VIP标识', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vbs.png'],
                ['name' => '急速注销', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jszx.png'],
                ['name' => '解锁联系方式' . $right['contact'] . '/日', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jslx.png'],
                ['name' => '超级曝光' . $right['super_show'] . '次', 'icon' => 'http://static.hfriend.cn/vips/icon_4_cjbg.png'],
                ['name' => '赠送' . $right['reward'] . '友币', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsjb.png'],
                ['name' => '开通广播', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ktgb.png'],
                ['name' => '专属昵称色', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsnc.png'],
                ['name' => '隐身模式', 'icon' => 'http://static.hfriend.cn/vips/icon_4_dtys.png'],
                ['name' => 'VIP客服', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vkha.png'],
                ['name' => '专属消息背景', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsbj.png'],
            ];
        }
        $right = [
            're_purchase' => $re_purchase,
            'right_str' => $right_str,
            'right_name' => $right_name,
            'right_list' => $right_list,
            'price_gear' => array_values($price_gear)
        ];
        return $right;
    }


    //3.0版本使用的VIP中心
    public static function getRightByNameHighVer($vip = false): array
    {
        $votes = [];
        $price_gear = CHANNEL == 'android' ? config('subscribe.vip_list_ver_android') : config('subscribe.vip_list_ver');
        foreach ($price_gear as $k => &$gear) {
            $gear['product_id'] = $gear['id'];
            $gear['discount'] = $gear['id'] == 'quzhi701' ? '优惠￥' . ($gear['origin_price'] - $gear['price']) : '';
            $right_str = '成为VIP会员免费解锁超多权益';
            if (in_array($gear['id'], ['quzhi201', 'quzhi200'])) {
                if ($vip) $right_str = '心友包周专属特权5/12';
                $right = self::getRightTimes(1);
                $right_list = [
                    ['name' => '查看访客', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ckfk.png'],
                    ['name' => '专属礼物', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zslw.png'],
                    ['name' => 'VIP标识', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vbs.png'],
                    ['name' => '急速注销', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jszx.png'],
                    ['name' => '超级曝光' . $right['super_show'] . '次', 'icon' => 'http://static.hfriend.cn/vips/icon_4_cjbg.png'],
                    ['name' => '赠送' . $right['reward'] . '友币', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsjb.png'],
                    ['name' => '解锁联系方式' . $right['contact'] . '/日', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jslx.png'],
                ];
            }
            if (in_array($gear['id'], ['quzhi701', 'quzhi700'])) {
                if ($vip) $right_str = '心友包月专属特权9/12';
                $right = self::getRightTimes(4);
                $right_list = [
                    ['name' => '查看访客', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ckfk.png'],
                    ['name' => '专属礼物', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zslw.png'],
                    ['name' => 'VIP标识', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vbs.png'],
                    ['name' => '急速注销', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jszx.png'],
                    ['name' => '解锁联系方式' . $right['contact'] . '/日', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jslx.png'],
                    ['name' => '超级曝光' . $right['super_show'] . '次', 'icon' => 'http://static.hfriend.cn/vips/icon_4_cjbg.png'],
                    ['name' => '赠送' . $right['reward'] . '友币', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsjb.png'],
                    ['name' => '开通广播', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ktgb.png'],
                    ['name' => '昵称变红', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsnc.png'],
                ];
            }
            if (in_array($gear['id'], ['quzhi400'])) {
                if ($vip) $right_str = '心友包季专属特权11/12';
                $right = self::getRightTimes(7);
                $right_list = [
                    ['name' => '查看访客', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ckfk.png'],
                    ['name' => '专属礼物', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zslw.png'],
                    ['name' => 'VIP标识', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vbs.png'],
                    ['name' => '急速注销', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jszx.png'],
                    ['name' => '解锁联系方式' . $right['contact'] . '/日', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jslx.png'],
                    ['name' => '超级曝光' . $right['super_show'] . '次', 'icon' => 'http://static.hfriend.cn/vips/icon_4_cjbg.png'],
                    ['name' => '赠送' . $right['reward'] . '友币', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsjb.png'],
                    ['name' => '开通广播', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ktgb.png'],
                    ['name' => '专属昵称色', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsnc.png'],
                    ['name' => '隐身模式', 'icon' => 'http://static.hfriend.cn/vips/icon_4_dtys.png'],
                    ['name' => 'VIP客服', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vkha.png'],
                ];
            }
            if (in_array($gear['id'], ['quzhi500'])) {
                if ($vip) $right_str = '心友包年专属特权12/12';
                $right = self::getRightTimes(10);
                $right_list = [
                    ['name' => '查看访客', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ckfk.png'],
                    ['name' => '专属礼物', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zslw.png'],
                    ['name' => 'VIP标识', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vbs.png'],
                    ['name' => '急速注销', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jszx.png'],
                    ['name' => '解锁联系方式' . $right['contact'] . '/日', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jslx.png'],
                    ['name' => '超级曝光' . $right['super_show'] . '次', 'icon' => 'http://static.hfriend.cn/vips/icon_4_cjbg.png'],
                    ['name' => '赠送' . $right['reward'] . '友币', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsjb.png'],
                    ['name' => '开通广播', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ktgb.png'],
                    ['name' => '专属昵称色', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsnc.png'],
                    ['name' => '隐身模式', 'icon' => 'http://static.hfriend.cn/vips/icon_4_dtys.png'],
                    ['name' => 'VIP客服', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vkha.png'],
                    ['name' => '专属消息背景', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsbj.png'],
                ];
            }
            $votes[] = [
                'right_str' => $right_str,
                'right_list' => $right_list,
                'price_gear' => $gear
            ];
        }
        return $votes;
    }


    public static function fastPayRight($vip_type, $jump_type = 1, $tips_type = 0): array
    {
        $rights = [
            ['name' => '查看访客', 'desc' => '看看都谁浏览了我的主页，找到对我中意的Ta'],
            ['name' => '专属礼物', 'desc' => '专属VIP礼物特权，让你送出的礼物与众不同'],
            ['name' => 'VIP标识', 'desc' => 'vip标识，人群中显现您特殊尊贵的身份'],
            ['name' => '急速注销', 'desc' => '无需等待时间，用户信息自助急速注销'],
            ['name' => '查看联系方式', 'desc' => '查看他的联系方式，更快速的联系到Ta'],
            ['name' => '超级曝光', 'desc' => '更多的展现次数，让你的好友轻松翻倍'],
            ['name' => '赠送友币', 'desc' => '赠送友币，打赏消费两不误，让聊天更加顺畅'],
            ['name' => '开通广播', 'desc' => '开通vip全服广播，让更多人知道你的非凡存在'],
            ['name' => '专属昵称色', 'desc' => '昵称专属颜色，与不同用户区分显示，体现尊享特权身份'],
            ['name' => '隐身模式', 'desc' => '全服隐身，满足你低调交友的需要，浏览聊天不留痕迹'],
            ['name' => 'VIP客服', 'desc' => '专属一对一VIP客服，解决您在使用过程中遇到的各种问题'],
            ['name' => '专属消息背景', 'desc' => '专属消息背景设置，您可以设置自己的照片等为聊天背景'],
        ];
        $right_str = '开通VIP享专属特权';
        if ($vip_type == 'swordsman') $right_str = '心友包周专属特权5/12';
        if ($vip_type == 'knight') $right_str = '心友包月专属特权6/12';
        if ($vip_type == 'suzerain') $right_str = '心友包季专属特权11/12';
        if ($vip_type == 'lord') $right_str = '心友包年专属特权12/12';

        $data['right_info']['right_str'] = $right_str;
        $price_gear = config('subscribe.vip_list')[$vip_type];
        foreach ($price_gear as &$gear) {
            unset($gear['id_num']);
            $gear['product_id'] = $gear['id'];
            $gear['origin_price'] = number_format($gear['origin_price'], 2);
            $gear['price'] = number_format($gear['price'], 2);
            $gear['discount'] = number_format($gear['origin_price'] - $gear['price'], 2);
            $gear['discount'] = '';
            if (in_array($gear['id'], ['quzhi11', 'quzhi21', 'quzhi31', 'quzhi301', 'quzhi300'])) {
                $gear['month_str'] = '￥' . ceil($gear['price']) . '/月';
                $gear['type_name'] = '1个月';
                $gear['slogan'] = '';
            }
            if (in_array($gear['id'], ['quzhi12', 'quzhi22', 'quzhi32', 'quzhi401', 'quzhi400'])) {
                $gear['month_str'] = '￥' . ceil($gear['price'] / 3) . '/月';
                $gear['type_name'] = '3个月';
                $gear['slogan'] = '新人特惠';
            }
            if (in_array($gear['id'], ['quzhi13', 'quzhi23', 'quzhi33', 'quzhi501', 'quzhi500'])) {
                $gear['month_str'] = '￥' . ceil($gear['price'] / 12) . '/月';
                $gear['type_name'] = '12个月';
                $gear['slogan'] = '';
            }
        }
        $data['right_info']['price_gear'] = $price_gear;
        $data['right_list'] = $rights;
        $data['jump_type'] = $jump_type;
        return $data;
    }


    //封装方法获取不同等级VIP 特殊权限次数
    public static function getRightTimes($vip_level = 0, $sex = 0): array
    {
        $data = [
            'contact' => $sex == 1 ? 10 : 0,
            'chat' => $sex == 1 ? 10 : 0,   //女孩默认10次私信机会
            'super_show' => 0,
            'reward' => 0,
        ];
        if ($vip_level >= 1 && $vip_level <= 3) {
            $data['contact'] = 10;
            $data['chat'] = 10;
            $data['super_show'] = 1;
            $data['reward'] = 120;
        }
        if ($vip_level >= 4 && $vip_level <= 6) {
            $data['contact'] = 15;
            $data['chat'] = 15;
            $data['super_show'] = 2;
            $data['reward'] = 150;
        }
        if ($vip_level >= 7 && $vip_level <= 9) {
            $data['contact'] = 20;
            $data['chat'] = 20;
            $data['super_show'] = 4;
            $data['reward'] = 160;
        }
        if ($vip_level >= 10 && $vip_level <= 12) {
            $data['contact'] = 25;
            $data['chat'] = 25;
            $data['super_show'] = 8;
            $data['reward'] = 180;
        }
        return $data;
    }
}
