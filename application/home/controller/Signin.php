<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/11/14 0014
 * Time: 下午 1:04
 */

namespace app\home\controller;
//签到

class Signin extends Base {
    /**
     * 签到主页
     */
    public function index(){

        return $this->fetch();
    }

    /**
     * 签到详情页
     */
    public function detail(){

        return $this->fetch();

    }
    /**
     * 签到功能
     */
    public function sign(){
        $id = input('id');
        $userid = input('user_id');
        $result = Apply::where(array('notice_id'=>$id,'userid'=>$userid))->find();
        if($result){
            $Wechat = WechatUser::where(['userid'=>$userid])->find();
            return array('status'=>0,'header'=>$Wechat['avatar'],'name'=>$Wechat['name']);
        }else{
            $Wechat = WechatUser::where(['userid'=>$userid])->find();
            if($Wechat){
                $data = array(
                    'notice_id' => $id,
                    'userid' =>$userid,
                    'status' =>0
                );
                $Apply = new Apply();
                $res = $Apply->save($data);
                if($res){
                    $Wechat = WechatUser::where(['userid'=>$userid])->find();
                    return array('status'=>1,'header'=>$Wechat['avatar'],'name'=>$Wechat['name']);
                }else{
                    return array('status'=>2,'header'=>null,'name'=>null);
                }
            }else{
                return array('status'=>2,'header'=>null,'name'=>null);
            }

        }
    }
}