<?php
/**
 * Created by PhpStorm.
 * User: Lxx<779219930@qq.com>
 * Date: 2017/2/17
 * Time: 16:28
 */

namespace app\home\controller;
use think\Controller;
use com\wechat\TPQYWechat;
use think\Config;
use think\Db;

class Cronjob extends Controller {
    /**
     * 每天定时发送  答题提醒
     */
    public function automatic_push(){
        //推送消息的详情
        $Wechat = new TPQYWechat(Config::get('party'));
        $title = '"每日一课"已经等候您多时了...';
        $content = "休息一下,去答个题吧";
        $path = "http://tzgxpb.0571ztnet.com/home/images/user/relax.jpg";//图片链接
        $url = "http://tzgxpb.0571ztnet.com/home/Constitution/course";  //答题页面链接
        $send = array(
            "articles" => array(
                array(
                    "title" => $title,
                    "description" => $content,
                    "url" => $url,
                    "picurl" => $path,
                )
            )
        );
        //发送
        $message = array(
                'touser' => '17557289172',
//           'touser' =>"@all",
            "msgtype" => 'news',
            "agentid" => 1000002,
            "news" => $send,
            "safe" => "0"
        );
        $Wechat->sendMessage($message);
    }
    /*
     * 每天定时 发送 天气预报
     */
    public function weather(){
        $url ='http://api.map.baidu.com/telematics/v3/weather?location=台州&output=json&ak=bKkCa6mmMCFdzXD4p5bjyiNX' ;
        $info = $this ->http_get($url);
        $data = json_decode($info);
        $str = "【".$data ->results[0] ->currentCity."天气预报】$data->date"."\n\n"
            .'明天'.$data ->results[0] ->weather_data[1] ->weather.'，'.$data ->results[0] ->weather_data[1] ->temperature.'，'.$data ->results[0] ->weather_data[1] ->wind."。\n\n"
            .'穿衣指数：'.$data ->results[0] ->index[0] ->des."\n\n"
            .'洗车指数：'.$data ->results[0] ->index[1] ->des."\n\n"
            .'感冒指数：'.$data ->results[0] ->index[2] ->des."\n\n"
            .'运动指数：'.$data ->results[0] ->index[3] ->des."\n\n"
            .'紫外线指数：'.$data ->results[0] ->index[4] ->des.""
        ;
        $text = array(
//           "touser" => "@all",
//            'touser' => '15036667391',
            "msgtype" =>"text",
            "agentid" => 0,
            "text" =>[
                "content" => $str
            ]
        );
        $Wechat = new TPQYWechat(Config::get('party'));
        $result = $Wechat ->sendMessage($text);
        //errcode 成功为0 其他失败
        if($result['errcode'] === 0){
            return '推送成功';
        }else{
            return '推送失败';
        }
    }
    /**
     * http_get请求
     */
    public function http_get($url){
        $oCurl = curl_init();
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if(intval($aStatus["http_code"])==200){
            return $sContent;
        }else{
            return false;
        }
    }
    /**
     * 每天推送 答题 
     */
    public function push_award(){
        $Wechat = new TPQYWechat(Config::get('party'));
        $date = Db::name('award')->order('id asc')->value('create_time');
        $time = strtotime(date('Y-m-d',time()));
        $first_start = strtotime(date('Y-m-d',$date)) + 10*24*60*60;
        $first_end = strtotime(date('Y-m-d',$date)) + 11*24*60*60;
        if ($first_start < $time && $time < $first_end){
            // 第十一天   推送 终极 抽奖
            $award = Db::name('award')->order('id desc')->select();
            $arr = array();
            foreach($award as $value){  // 获取 连续十天参加活动的人员
                $res = $value['userid']."_".date('Y-m-d',$value['create_time']);
                if (isset($arr[$value['userid']])){
                    if (!in_array($res,$arr[$value['userid']])){
                        $arr[$value['userid']][] = $res;
                    }
                }else{
                    $arr[$value['userid']][] = $res;
                }
            }
            $user_arr = array();
            foreach($arr as $key => $value){
                if (count($value) >=10){
                    array_push($user_arr,$key);
                }
            }
            //推送消息的详情
            $title = '您有机会抽取超级大奖';
            $content = "赶快去试试手气";
            $path = "http://tzgxpb.0571ztnet.com/home/images/user/relax.jpg";//图片链接
            $url = "http://tzgxpb.0571ztnet.com/home/Award/index";  //终极抽奖页面链接
            $send = array(
                "articles" => array(
                    array(
                        "title" => $title,
                        "description" => $content,
                        "url" => $url,
                        "picurl" => $path,
                    )
                )
            );
            //发送
            $message = array(
                'touser' => '17557289172',
//           'touser' =>implode('|',$user_arr),
                "msgtype" => 'news',
                "agentid" => 1000002,
                "news" => $send,
                "safe" => "0"
            );
            $Wechat->sendMessage($message);
        }else{
            //推送消息的详情
            $title = '答题抽大奖...';
            $content = "不赚白不赚";
            $path = "http://tzgxpb.0571ztnet.com/home/images/user/relax.jpg";//图片链接
            $url = "http://tzgxpb.0571ztnet.com/home/Award/index";  //答题页面链接
            $send = array(
                "articles" => array(
                    array(
                        "title" => $title,
                        "description" => $content,
                        "url" => $url,
                        "picurl" => $path,
                    )
                )
            );
            //发送
            $message = array(
                'touser' => '17557289172',
//           'touser' =>"@all",
                "msgtype" => 'news',
                "agentid" => 1000002,
                "news" => $send,
                "safe" => "0"
            );
            $Wechat->sendMessage($message);
        }
    }
}