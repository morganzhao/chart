<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\User;

class SysController extends Controller
{
    public $successStatus = 200;
    public $notAllow = 'Not Allowed';
    protected $nullClass ;
    //
    public function __construct(){
        $this->nullClass = new \stdClass;
    }

    /*
     *login
     */
    public function login(Request $request){
        $validator = Validator::make($request->all(),[
            'account'=>'required',
            'password'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $model = new User();
        $map = [
            'mobile'=>$request->account,
            'role'=>9
        ];

        $user_info = $model->where($map)->first();
        if(!password_verify($request->password,$user_info['password'])){
            showMsg(2,new class{},'账号密码不正确！');
        }
        
        if($user_info->role!=9){
            showMsg(2,$this->nullClass,'不是管理员账号，无权限!');
        }
        if($user_info){
            unset($user_info['clear-text_password']);
            showMsg(1,$user_info);
        }else{
            showMsg(2,$this->nullClass,'不是管理员账号，无权限!');
        }
    }

    /*
     *get userList
     */
    public function userList(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $limit = $request->limit?$request->limit:10;
        $userModel = new User();
        $map['role'] = 2;
        $list = $userModel->where($map)->paginate($limit)->toArray();
        if($list){
            showMsg(1,$list);
        }else{
            showMsg(2,[]);
        }
    }

    /*
     *聊天列表
     */
    public function chartList(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }

        $user_info = getUserInfo($request->token);

        $messageModel = new \App\Model\Message();
        $map = [
            'to'=>$user_info->id
        ];
        $list = $messageModel->where([])->paginate(10)->toArray();
        
        $userModel = new User();
        foreach($list['data'] as &$v){
            $userInfo = $userModel->userInfo($v['from']);
            $v['title'] = $userInfo->nickname;
            $v['avatar_url'] = $userInfo->avatar_url;
        }
        if($list){
            showMsg(1,$list);
        }else{
            showMsg(2,[]);
        }

    }


}
