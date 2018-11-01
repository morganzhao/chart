<?php

namespace App\Http\Controllers\Api;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Model\Blog;
use App\User;
use App\Libs\Common;
use App\Model\Contact;
use App\Libs\LSms;
use App\Model\Style;

class GamController extends Controller
{
    public $successStatus = 200;
    public $notAllow = 'Not Allowed';
    //
    public function __construct(){
        
    }

    public function index(Request $request){
        $user = DB::table('users')->select('id','name')->where('id',1)->first();
        $list = DB::table('users')->orderBy('id','desc')->Paginate('15');
        $return['status'] = 1;
        $return['data'] = $list;
        return response()->json($return);
    }

    public function UserInfo(){
        $arr = array_fill(1,33,'');
        $keys = array_keys($arr);
        print_r(implode(',',$keys));die;
    }
    /*
     *register
     */
    public function register(Request $request){
        $validator = Validator::make($request->all(),[
            'mobile'=>'required',
            'password'=>'required',
            'sms_code'=>'required'
        ]);
        if($validator->fails()){
            return response()->json(['status'=>0,'data'=>$validator->errors()],401);
        }

        if($request->sms_code!=9999){
            showMsg(2,[],'验证码不正确！');
        }

        $yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        $rand= $yCode[intval(date('Y')) - 2011] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
        $data = [
            'mobile'=>$request->mobile,
            'token'=>$rand,
            'password'=>Hash::make($request->password),
            'clear-text_password'=>$request->password,
            'age'=>$request->age,
            'sex'=>$request->sex?$request->sex:1,
            'nickname'=>$request->nickname,
            'signature'=>$request->signature

        ];
        $userModel = new User;

        $user = $userModel->hasUser($request->mobile);
        if($user&&$user->mobile){
            showMsg(2,[],'手机号已注册！');
        }
        $id = $userModel->add($data);
        if($id){
            showMsg(1,$data);
        }else{
            showMsg(2,[]);
        }
    }
    /*
     *login
     */
    public function login(Request $request){
        $validator = Validator::make($request->all(),[
            'mobile'=>'required',
            'password'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $model = new User();
        //verify password
        $info = $model->where('mobile',$request->mobile)->first();
        $password = Hash::make($request['password']);
        if(!password_verify($request->password,$info['password'])){
            showMsg(2,new class{},'账号或密码不正确！'); 
        }
        if($info){
            showMsg(1,$info); 
        }else{
            showMsg(2,new \stdClass);
        }
    }
    /*
     *同步联系人
     */
    public function syncContacts(Request $request){
        if(!$request->isMethod('post')){
            showMsg(2,[],'Not Allowed');
        }
        $validator = Validator::make($request->all(),[
            'json'=>'required',
            'token'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $arr = [
            array('name'=>'test','mobile'=>'13656227964'),
            array('name'=>'test','mobile'=>'13656227965')
        ];
        $json_to_array = json_decode($request->json,1);
        //get user_info
        $user_info = getUserInfo($request->token);
        
        foreach($json_to_array as &$v){
            $v['user_id'] = $user_info->id;
        }
        $res = Contact::insert($json_to_array);
        if($res){
            showMsg(1,$json_to_array);
        }else{
            showMsg(2,[]);
        }
    }
    /*
     *获取联系人列表
     */
    public function contactList(Request $request){
        if(!$request->isMethod('post'))showMsg(2,[],$this->notAllow);
        $validator = Validator::make($request->all(),[
            'token'=>'required'
        ]);
        $keyword = $request->keyword;
        $map =[];
        if($keyword){
            $map = [
                ['mobile','like','%'.$keyword.'%']
            ];
        }
        
        //验证用户信息
        $user_info = getUserInfo($request->token);
        if($user_info){
            $map['user_id'] = $user_info->id;
        }
        $contact = new Contact();
        $limit = $request->limit?$request->limit:10;
        $list = $contact->getList($map,$limit);
        showMsg(1,$list);

    }
    /*
     *完善信息
     */
    public function updateUserInfo(Request $request){
        $validator = Validator::make($request->all(),[
            'id'=>'required',
           'age'=>'required' 
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $data = [
            'age'=>$request->age,
            'sex'=>$request->sex,
            'mobile'=>$request->mobile,
            'nickname'=>$request->nickname,
            'signature'=>$request->signature,
            'updated_at'=>date('Y-m-d H:i:s'),
        ];
        $model = new User();
        $res = $model->where('id',$request->id)->update($data);
        if($res){
            showMsg(1,$data);
        }else{
            showMsg(2,[]);
        }

    }
    /*
     *忘记密码
     */
    public function resetPassword(Request $request){
        $validator = Validator::make($request->all(),[
            'mobile'=>'required',
            'sms_code'=>'required',
            'password'=>'required',
            'confirm_password'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        if($request->sms_code!=9999){
            showMsg(2,[],'验证码不正确！');
        }
        if($request->password!=$request->confirm_password){
            showMsg(2,[],'2次密码不一致！');
        }
        $model = new User();
        //verify user
        $info = $model->where('mobile','like','%'.$request->mobile.'%')->first();
        if(!$info){
            showMsg(2,[],'此账号未注册！');
        }
        $data = [
            'password'=>Hash::make($request->confirm_password),
            'updated_at'=>date('Y-m-d H:i:s')
        ];
        $res = $model->where('id',$info['id'])->update($data);
        if($res){
            showMsg(1);
        }else{
            showMsg(2);
        }
    }
    public function send(Request $request){
        
    }
    /*
     *关注
     */
    public function focus(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required',
            'id'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $user_info = getUserInfo($request->token);
        $map =[];
        if($user_info){
            $map['user_id'] = $user_info->id;
        }
        $map['id'] = $request->id;
        //get contact
        $model = new Contact();
        $contact = $model->where($map)->first();
        if($contact){
            $data = [
                'is_attention'=>1,
                'updated_at'=>date('Y-m-d H:i:s')
            ];
            $res = $model->where('id',$request->id)->update($data);
            if($res){
                showMsg(1,$data);
            }else{
                showMsg(2,[]);
            }
        }else{
            showMsg(2,[]);
        }
    }
    /*
     *style list
     */
    public function styleList(Request $request){
        $user_info = getUserInfo($request->token);
        $map = [
            'user_id'=>$user_info->id
        ];
        $model = new Style();
        $list = $model->getList($map,10);
        if($list){
            showMsg(1,$list);
        }else{
            showMsg(2,[]);
        }
    }

    /*
     *add tag
     */
    public function addTag(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required',
            'id'=>'required',
            'tag'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }

        $user_info = getUserInfo($request->token);
        $model = new Style();
        $style = $model->where('id',$request->id)->first();
        $data = [
            'tags'=>$style->tags!=''?$style->tags.','.$request->tag:$request->tag,
            'updated_at'=>date('Y-m-d H:i:s')
        ];
        $res = $model->where('id',$request->id)->update($data);
        if($res){
            $data['tags_arr'] = explode(',',$data['tags']);
            showMsg(1,$data);
        }else{
            showMsg(2,[]);
        }
    }
}

