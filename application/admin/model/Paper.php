<?php
/**
 * Created by PhpStorm.
 * User: Lxx<779219930@qq.com>
 * Date: 2016/9/21
 * Time: 15:16
 */

namespace app\admin\model;
use think\Model;

class Paper extends Base {
    //获取后台用户名称
    public function user(){
        return $this->hasOne('Member','id','create_user');
    }

}