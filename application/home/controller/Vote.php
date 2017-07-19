<?php
/**
 * Created by PhpStorm.
 * User: 老王
 * Date: 2017/1/3
 * Time: 13:11
 */
 namespace app\home\controller;
use app\home\model\Vote as VoteModel;
use app\home\model\VoteOptions;
use app\home\model\Browse;
use app\home\model\VoteAnswer;
use app\home\model\Picture;
use app\home\model\WechatUser;
use app\home\model\Work;
use app\home\model\WechatDepartment;
use app\home\model\Like;
use app\home\model\Comment;
use think\Db;
use app\home\model\WechatUserTag;
/*
 * 选举投票主页
*/

class Vote extends Base{
    /*
     * 判断 权限
     */
    public function role(){
        $this->checkRole();
        $userId = session('userId');
        $res = WechatUserTag::where(['tagid' => 3,'userid' => $userId])->find();
        if ($res){
            // 领导权限 查看所有 三会一课  支部活动
            return 1;
        }else{
            return 0;
        }
    }
     /*
      * 投票主页
      */
     public function index(){
         $this->checkRole();
         $this->anonymous();
         $userId = session('userId');
         //  获取该用户 所在支部
         $Depart = WechatUser::where('userid',$userId)->field('department')->find();
         $depart = json_decode($Depart['department']);
         $this->jssdk();
         if (!$this->role()){ // 普通
             // 三会一课
             $con1 = array(
                 'type' => 1,
                 'status' => 0,
                 'branch' => ['in',$depart]
             );
             // 支部活动
             $con2 = array(
                 'type' => 2,
                 'status' => 0,
                 'branch' => ['in',$depart]
             );
             // 民主评议
             $con3 = array(
                 'status' => ['egt',0],
                 'publisher' => ['in',$depart]
             );
             // 选举投票  未结束
             $con4 = array(
                 'status' => array('egt',0),
                 'end_time' => array('gt',time()),  // 未结束
                 'publisher' => array('in',$depart)
             );
             // 选举投票  历史
             $con5 = array(
                 'status' => array('egt',0),
                 'end_time' => array('elt',time()),  // 历史投票
                 'publisher' => array('in',$depart)
             );
         }else{  // 领导
             $con1 = array(
                 'type' => 1,
                 'status' => 0,
             );
             $con2 = array(
                 'type' => 2,
                 'status' => 0,
             );
             $con3 = array(
                 'status' => ['egt',0],
             );
             $con4 = array(
                 'status' => array('egt',0),
                 'end_time' => array('gt',time()),  // 未结束
             );
             $con5 = array(
                 'status' => array('egt',0),
                 'end_time' => array('elt',time()),  // 历史投票
             );
         }
         // 获取 三会一课  内容
         $meet = Work::where($con1)->order('id desc')->limit(10)->select();
         $this->assign('meet',$meet);
         // 获取  支部活动 内容
         $activity = Work::where($con2)->order('id desc')->limit(10)->select();
         $this->assign('activity',$activity);
         // 获取 民主评议
         $appraise = Db::table('pb_appraise')->where($con3)->order('id desc')->limit(10)->select();
         $this->assign('appraise',$appraise);
         // 选举投票
         $voteModel = new VoteModel();
         $top = $voteModel->where($con4)->order('id desc')->select(); // 未结束
         foreach($top as $value){
             $secs = $value['end_time'] - time();
             $result = '';
             if ($secs >= 86400) {
                 $days = floor($secs / 86400);
                 $secs = $secs % 86400;
                 $result = $days . '天';
                 if ($secs > 0) {
                     $result .= ' ';
                 }
             }
             if ($secs >= 3600) {
                 $hours = floor($secs / 3600);
                 $secs = $secs % 3600;
                 $result .= $hours . '小时';
                 if ($secs > 0) {
                     $result .= ' ';
                 }
             }
             if ($secs >= 60) {
                 $minutes = floor($secs / 60);
                 $secs = $secs % 60;
                 $result .= $minutes . '分钟';
                 if($secs > 0) {
                     $result .= ' ';
                 }
             }
             $value['left'] = $result;
             $Options = VoteOptions::where(['vote_id' => $value->id , 'status' => 0])->select();
             $sum = 0;
             foreach($Options as $val){
                 $sum += $val->num;
             }
             $value['sum'] = $sum;
         }
         $list = $voteModel->where($con5)->order('id desc')->select();  // 历史记录
         foreach($list as $value){
             $Options = VoteOptions::where(['vote_id' => $value->id , 'status' => 0])->select();
             $sum = 0;
             foreach($Options as $val){
                 $sum += $val->num;
             }
             $value['sum'] = $sum;
         }
         $this->assign('top',$top);
         $this->assign('list',$list);
         return $this->fetch();
     }
   /*
    * 会议情况  详情
    */
    public function meeting(){
        //判断是否是游客
        $this->anonymous();
        $this->jssdk();
        $userId = session('userId');
        $id = input('id');
        $Model = new Work();
        //浏览加一
        $info['views'] = array('exp','`views`+1');
        $Model::where('id',$id)->update($info);
        if($userId != "visitor"){
            //浏览不存在则存入pb_browse表
            $con = array(
                'user_id' => $userId,
                'work_id' => $id,
            );
            $history = Browse::get($con);
            if(!$history && $id != 0){
                $s['score'] = array('exp','`score`+1');
                if ($this->score_up()){
                    // 未满 15 分
                    Browse::create($con);
                    WechatUser::where('userid',$userId)->update($s);
                }
            }
        }
        //活动基本信息
        $list = $Model::get($id);
        $list['user'] = session('userId');
        //分享图片及链接及描述
        $image = Picture::where('id',$list['front_cover'])->find();
        $list['share_image'] = "http://".$_SERVER['SERVER_NAME'].$image['path'];
        $list['link'] = "http://".$_SERVER['SERVER_NAME'].$_SERVER['REDIRECT_URL'];
        $list['desc'] = str_replace('&nbsp;','',strip_tags($list['content']));
        $list['images'] = json_decode($list['images']);
        //获取 文章点赞
        $likeModel = new Like;
        $like = $likeModel->getLike(7,$id,$userId);
        $list['is_like'] = $like;
        $this->assign('info',$list);

        //获取 评论
        $commentModel = new Comment();
        $comment = $commentModel->getComment(7,$id,$userId);
        $this->assign('comment',$comment);
        return $this->fetch();
    }
    /*
     * 支部活动  详情
     */
    public function activity(){
       //判断是否是游客
        $this->anonymous();
        $this->jssdk();
        $userId = session('userId');
        $id = input('id');
        $Model = new Work();
        //浏览加一
        $info['views'] = array('exp','`views`+1');
        $Model::where('id',$id)->update($info);
        if($userId != "visitor"){
            //浏览不存在则存入pb_browse表
            $con = array(
                'user_id' => $userId,
                'work_id' => $id,
            );
            $history = Browse::get($con);
            if(!$history && $id != 0){
                $s['score'] = array('exp','`score`+1');
                if ($this->score_up()){
                    // 未满 15 分
                    Browse::create($con);
                    WechatUser::where('userid',$userId)->update($s);
                }
            }
        }
        //活动基本信息
        $list = $Model::get($id);
        $list['user'] = session('userId');
        //分享图片及链接及描述
        $image = Picture::where('id',$list['front_cover'])->find();
        $list['share_image'] = "http://".$_SERVER['SERVER_NAME'].$image['path'];
        $list['link'] = "http://".$_SERVER['SERVER_NAME'].$_SERVER['REDIRECT_URL'];
        $list['desc'] = str_replace('&nbsp;','',strip_tags($list['content']));
        $list['images'] = json_decode($list['images']);
        //获取 文章点赞
        $likeModel = new Like;
        $like = $likeModel->getLike(7,$id,$userId);
        $list['is_like'] = $like;
        $this->assign('info',$list);

        //获取 评论
        $commentModel = new Comment();
        $comment = $commentModel->getComment(7,$id,$userId);
        $this->assign('comment',$comment);
        return $this->fetch();
    }
    /*
     * 三会一课  支部活动 加载更多
     */
    public function more(){
        $this->checkRole();
        $this->anonymous();
        $userId = session('userId');
        //  获取该用户 所在支部
        $Depart = WechatUser::where('userid',$userId)->field('department')->find();
        $depart = json_decode($Depart['department']);
        $len = input('length');
        $type = input('type');
        if ($this->role()){
            // 领导
            $map = array(
                'type' => $type,
                'status' => 0,
            );
        }else{
            // 普通
            $map = array(
                'type' => $type,
                'status' => 0,
                'branch' => ['in',$depart],
            );
        }
        $list = Work::where($map)->order('id desc')->limit($len,10)->select();
        foreach($list as $value){
            $Pic = Picture::where('id',$value['front_cover'])->find();
            $value['front_cover'] = $Pic['path'];
            $value['create_time'] = date('Y-m-d',$value['create_time']);
        }
        if ($list){
            return $this->success('加载成功','',$list);
        }else{
            return $this->error('加载失败');
        }
    }
    /*
     * 民主评议 加载更多
     */
    public function plus(){
        $this->checkRole();
        $this->anonymous();
        $userId = session('userId');
        //  获取该用户 所在支部
        $Depart = WechatUser::where('userid',$userId)->field('department')->find();
        $depart = json_decode($Depart['department']);
        $len = input('length');
        if ($this->role()){
            // 领导
            $map = array(
                'status' => ['egt',0],
            );
        }else{
            // 普通
            $map = array(
                'status' => ['egt',0],
                'publisher' => ['in',$depart],
            );
        }
        $list = Db::table('pb_appraise')->where($map)->order('id desc')->limit($len,10)->select();
        foreach($list as $key => $value){
            $Pic = Picture::where('id',$value['front_cover'])->find();
            $list[$key]['front_cover'] = $Pic['path'];
            $list[$key]['create_time'] = date('Y-m-d',$value['create_time']);
        }
        if ($list){
            return $this->success('加载成功','',$list);
        }else{
            return $this->error('加载失败');
        }
    }
    /*
     * 投票 页面
     */
     public function vote(){
         $this->checkRole();
         $id = input('get.id');  // 主题id
         $type = input('get.type');  // 类型
         $userId = session('userId');
         // 浏览量 +1
         $voteModel = new voteModel();
         $info['views'] = array('exp','`views`+1');
         $voteModel::where('id',$id)->update($info);
         if($userId != "visitor"){
             //浏览不存在则存入sw_browse表
             $con = array(
                 'user_id' => $userId,
                 'vote_id' => $id, // 投票主题 id
             );
             $history = Browse::get($con);
             if(!$history && $id != 0){
                 $s['score'] = array('exp','`score`+1');
                 if ($this->score_up()){
                     // 未满 15分
                     Browse::create($con);
                     WechatUser::where('userid',$userId)->update($s);
                 }
             }
         }
         if ($type == 1){
             // 进行中
             $Answer = VoteAnswer::where(['userid' => $userId,'vote_id' => $id])->find();  // 获取投票信息
             if (empty($Answer)){
                 // 未投票
                 $list = VoteModel::where('id',$id)->find();
                 $list['num'] = VoteOptions::where(['vote_id' => $id,'status' => 0])->count();
                 $sum = 0;
                 $Options= VoteOptions::where(['vote_id' => $id , 'status' => 0])->select();
                 foreach($Options as $value){
                     $sum += $value->num;
                 }
                 $list['sum'] = $sum;
                 $this->assign('list',$list);
                 $this->assign('link',$_SERVER['HTTP_HOST']);
                 return $this->fetch();
             }else{
                 //  已经投票  排名页面
                 $Vote = VoteModel::where('id',$id)->find();   // 获取 投票信息
                 $Vote['num'] = VoteOptions::where(['vote_id' => $id,'status' => 0])->count();  // 候选人
                 $sum = 0;
                 $Options= VoteOptions::where(['vote_id' => $id , 'status' => 0])->select();
                 foreach($Options as $value){
                     $sum += $value->num;
                 }
                 $Vote['sum'] = $sum;  // 投票人次
                 $data = VoteAnswer::where(['vote_id' => $id,'userid' => $userId])->find();
                 $Vote['vote'] = $data['content'];
                 return $this->fetch('rank',['list' => $Vote]);
             }
         }else{
             // 已结束
             $Vote = VoteModel::where('id',$id)->find();   // 获取 投票信息
             $Vote['num'] = VoteOptions::where(['vote_id' => $id,'status' => 0])->count();  // 候选人
             $sum = 0;
             $Options= VoteOptions::where(['vote_id' => $id , 'status' => 0])->select();
             foreach($Options as $value){
                 $sum += $value->num;
             }
             $Vote['sum'] = $sum;  // 投票人次
             $data = VoteAnswer::where(['vote_id' => $id,'userid' => $userId])->find();
             $Vote['vote'] = $data['content'];
             return $this->fetch('rank',['list' => $Vote]);
         }
         
     }
    /*
     * 投票 功能
     */
    public function polling(){
        $this->checkRole();
        $id = input('id');  // 选项id
        $vid = input('vid');  // 主题id
        $userId = session('userId');
        $map = array(
            'userid' => $userId,
            'vote_id' => $vid
        );
        $data['userid'] = $userId;
        $data['vote_id'] = $vid;
        $data['content'] = $id;
        $res = VoteAnswer::where($map)->find();  // 防止二次投票
        if (empty($res)){
            $Answer = new VoteAnswer();
            $result = $Answer->save($data);
            if ($result){
                // 保存成功后  修改 选项表
                VoteOptions::where('id',$id)->setInc('num');
            }
        }
    }
    /*
     * 投票 排名
     */
     public function rank(){
         $this->checkRole();
         $id = input('id');  // 主题id
         $userId = session('userId');
         $list = VoteModel::where('id',$id)->find();
         $list['num'] = VoteOptions::where(['vote_id' => $id,'status' => 0])->count();
         $sum = 0;
         $Options= VoteOptions::where(['vote_id' => $id , 'status' => 0])->select();
         foreach($Options as $value){
             $sum += $value->num;
         }
         $list['sum'] = $sum;  // 投票总人次
         $data = VoteAnswer::where(['vote_id' => $id,'userid' => $userId])->find();
         $list['vote'] = $data['content'];
         $this->assign('list',$list);  // 投票主题,选项内容
         return $this->fetch();
     }
    /*
     * 视频会议
     */
    public function meet(){
        $this->checkRole();
        return $this->fetch();
    }
    /**
     * 上传笔记
     */
    public function notes(){
        $this->checkRole();
        $userId = session('userId');
        $a = array('1'=>'a','2'=>'b','3'=>'c','4'=>'d','5'=>'e','6'=>'f','7'=>'g','8'=>'h','9'=>'i','10'=>'j','11'=>'k','12'=>'l','13'=>'m','14'=>'n','15'=>'o',
            '16'=>'p','17'=>'q','18'=>'r','19'=>'s','20'=>'t','21'=>'u','22'=>'v','23'=>'w','24'=>'x','25'=>'y','26'=>'z');
        $Work = new Work();
        if(IS_POST) {
            $data = input('post.');
            $data['front_cover'] = array_rand($a,1);
            $data['publisher'] = $userId;
            if (isset($data['images'])){
                $data['images'] = json_encode($data['images']);
            }
            if (isset($data['meet_time'])){
                $data['meet_time'] = strtotime($data['meet_time']);
            }
            $res = $Work -> save($data);
            if ($res){
                return $this->success('发布成功');
            }else{
                return $this->error('发布失败');
            }
        }else{
            $Department = WechatDepartment::where(['parentid' => ['neq',0]])->field('id,name')->select();
            $this->assign('info',$Department);
            return $this->fetch();
        }
    }
    /*
     * 民主评议  页面
     */
    public function appraise(){
        $this->checkRole();
        $id = input('get.id');  // 主题id
        $userId = session('userId');
        // 浏览量 +1
        $info['views'] = array('exp','`views`+1');
        Db::table('pb_appraise')->where('id',$id)->update($info);
        if($userId != "visitor"){
            //浏览不存在则存入sw_browse表
            $con = array(
                'user_id' => $userId,
                'appraise_id' => $id, // 投票主题 id
            );
            $history = Browse::get($con);
            if(!$history && $id != 0){
                $s['score'] = array('exp','`score`+1');
                if ($this->score_up()){
                    // 未满 15分
                    Browse::create($con);
                    WechatUser::where('userid',$userId)->update($s);
                }
            }
        }
        $list = Db::table('pb_appraise')->where('id',$id)->find();
        $list['num'] = Db::table('pb_appraise_options')->where(['app_id' => $id,'status' => 0])->count();
        $sum = 0;
        $Options = Db::table('pb_appraise_options')->where(['app_id' => $id,'status' => 0])->select();
        foreach($Options as $value){
            $sum += $value['num'];
        }
        $list['sum'] = $sum;
        $list['options'] = $Options;
        $this->assign('list',$list);
        return $this->fetch();
    }
    /*
     * 民主评议 详情
     */
    public function detail(){
        $this->checkRole();
        $id = input('id');
        $list = Db::table('pb_appraise_answer')->where(['op_id' => $id,'status' => 0])->order('id desc')->limit(10)->select();
        $this->assign('list',$list);
        $this->assign('id',$id);
        return $this->fetch();
    }
    /*
     * 民主评议  评论 更多
     */
    public function commentmore(){
        $this->checkRole();
        $id = input('id');
        $len = input('length');
        $list = Db::table('pb_appraise_answer')->where(['op_id' => $id,'status' => 0])->order('id desc')->limit($len,10)->select();
        foreach($list as $key => $value){
            $list[$key]['name'] = get_name($value['userid']);
            $list[$key]['header'] = get_header($value['userid']);
            $list[$key]['create_time'] = date('Y-m-d',$value['create_time']);
        }
        return $this->success('加载成功','',$list);
    }
    /*
     * 民主评议  评论
     */
    public function comment(){
        $data['op_id'] = input('id');
        $data['content'] = input('content');
        $data['userid'] = session('userId');
        $data['create_time'] = time();
        $res = Db::table('pb_appraise_answer')->insert($data);
        if ($res){
            $id = Db::table('pb_appraise_answer')->getLastInsID();
            Db::table('pb_appraise_options')->where(['id' => $data['op_id'],'status' => 0])->setInc('num');
            $list = Db::table('pb_appraise_answer')->where('id',$id)->find();
            $list['name'] = get_name(session('userId'));
            $list['header'] = get_header(session('userId'));
            $list['create_time'] = date('Y-m-d',$list['create_time']);
            return  $this->success('评论成功','',$list);
        }else{
            return $this->error('评论失败');
        }
    }
}