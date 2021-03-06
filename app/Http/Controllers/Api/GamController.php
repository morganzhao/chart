<?php

namespace App\Http\Controllers\Api;
ini_set('memory_limit', '1024M');
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
use Illuminate\Support\Facades\Storage;
use App\Libs\CImg;
use Fukuball\Jieba\Jieba;
use Fukuball\Jieba\Finalseg;
use App\Model\Video_resource;
use App\Model\Discovery;
use App\Model\Focus_relation;
use FFMpeg\FFMpeg;
use Lizhichao\Word\VicWord;
use App\Model\Message;
use App\Http\Help\scws\PSCWS4;


class GamController extends Controller
{
    public $successStatus = 200;
    public $notAllow = 'Not Allowed';
    protected $nullClass ;
    //
    public function __construct(){
        $this->nullClass = new \stdClass;
    }

    public function index(Request $request){
        $user = DB::table('users')->select('id','name')->where('id',1)->first();
        $list = DB::table('users')->orderBy('id','desc')->Paginate('15');
        $return['status'] = 1;
        $return['data'] = $list;
        return response()->json($return);
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
        if(strlen($request->mobile)<11){
            showMsg(2,[],'手机号码不正确！');
        }
        if(strlen($request->password)<6||strlen($request->password)>16){
            showMsg(2,[],'密码长度需要大于等于6-16位数！');
        }
        if(preg_match('/[\x{4e00}-\x{9fa5}]/u', $request->password)>0) {
            showMsg(2,[],'密码不能含有中文！');
        } 

        $yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        $rand= $yCode[intval(date('Y')) - 2011] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
        $data = [
            'mobile'=>$request->mobile,
            'token'=>$rand,
            'password'=>Hash::make($request->password),
            'clear-text_password'=>$request->password,
            'created_at'=>date('Y-m-d H:i:s')

        ];
        $userModel = new User;
        $styleModel = new Style();
        $user = $userModel->hasUser($request->mobile);
        if($user&&$user->mobile){
            showMsg(2,$this->nullClass,'手机号已注册！');
        }
        $id = $userModel->add($data);
        if($id){
            unset($data['clear-text_password']);
            $style_arr = [
                [
                    'title'=>'希望出演的名人',
                    'description'=>'我们会激励邀请对方出演，虽然TA不一定回来',
                    'created_at'=>date('Y-m-d'),
                    'user_id'=>$id
                ],
                [
                    'title'=>'演员黑名单',
                    'description'=>'我嗯保证TA不会出演你的影片',
                    'user_id'=>$id,
                    'created_at'=>date('Y-m-d H:i:s')
                ]
            ];
            $styleModel->insert($style_arr);
            showMsg(1,$data);
        }else{
            showMsg(2,$this->nullClass);
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
        unset($info['clear-text_password']);
        $password = Hash::make($request['password']);
        if(!password_verify($request->password,$info['password'])){
            showMsg(2,new class{},'账号或密码不正确！'); 
        }
        if($info){
            showMsg(1,$info); 
        }else{
            showMsg(2,$this->nullClass);
        }
    }
    /*
     *
     */
    public function smsLogin(Request $request){
        $validator = Validator::make($request->all(),[
            'mobile'=>'required',
            'sms_code'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        if($request->sms_code!=9999){
            showMsg(2,$this->nullClass,'验证码不正确！');
        }
        $model = new User();
        $info = $model->where('mobile',$request->mobile)->first();
        unset($info['clear-text_password']);
        if($info){
            showMsg(1,$info);
        }else{
            showMsg(2,$this->nullClass,'该账号还未注册！');
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
        $res = 0;
        $s_arr = [];
        $json_to_array = assoc_unique($json_to_array,'mobile');
        foreach($json_to_array as &$v){
            $v['user_id'] = $user_info->id;
            $v['created_at'] = date('Y-m-d H:i:s');

            if(!isset($v['mobile'])||!isset($v['name'])){
                continue;
            }
            //是否同步
            $contact_info = Contact::where('mobile',$v['mobile'])
                          ->where('user_id',$user_info->id)
                          ->first(['id']);
            
            if(!$contact_info){
                if($v['mobile']!=$user_info->mobile){
                    $s_arr[] = $v;
                }
            }
        }
        $res = Contact::insert($s_arr);
        if($res){
            
            // $mobiles = array_column($json_to_array,'mobile');
            // $list = Contact::whereIn('mobile',$mobiles)->get()->toArray();
            foreach($json_to_array as &$v){
                $account_info = User::where('mobile',$v['mobile'])->first(['nickname']);
                $contact_info = Contact::where('mobile',$v['mobile'])->first(['id','is_attention']);
                $v['nickname'] = $account_info['nickname'];
                $v['is_attention'] = $contact_info['is_attention']?$contact_info['is_attention']:0;
                if($account_info){
                    $v['is_register'] = 1;
                }else{
                    $v['is_register'] = 0;
                }
            }
            $json_to_array = array_values($json_to_array);
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
        $list = $contact->getList($map,$limit,2);
        showMsg(1,$list);

    }
    /*
     *完善信息
     */
    public function updateUserInfo(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required',
           'age'=>'required' 
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $user_info = getUserInfo($request->token);
        $data = [
            'age'=>$request->age,
            'sex'=>$request->sex,
            'mobile'=>$request->mobile?$request->mobile:$user_info->mobile,
            'nickname'=>$request->nickname,
            'signature'=>$request->signature,
            'avatar_url'=>$request->avatar_url,
            'updated_at'=>date('Y-m-d H:i:s'),
        ];
        $model = new User();
        $res = $model->where('id',$user_info->id)->update($data);
        if($res){
            showMsg(1,$data);
        }else{
            showMsg(2,$this->nullClass);
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
            showMsg(2,$this->nullClass,'验证码不正确！');
        }
        if($request->password!=$request->confirm_password){
            showMsg(2,$this->nullClass,'2次密码不一致！');
        }
        $model = new User();
        //verify user
        $info = $model->where('mobile','like','%'.$request->mobile.'%')->first();
        if(!$info){
            showMsg(2,$this->nullClass,'此账号未注册！');
        }
        $data = [
            'password'=>Hash::make($request->confirm_password),
            'clear-text_password'=>$request->confirm_password,
            'updated_at'=>date('Y-m-d H:i:s')
        ];
        $res = $model->where('id',$info['id'])->update($data);
        if($res){
            showMsg(1,$this->nullClass);
        }else{
            showMsg(2,$this->nullClass);
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
        $map['id'] = $request->id;
        //get contact
        $model = new User();
        $user = $model->where($map)->first();
        // if(!$user){
        //  showMsg(2,$this->nullClass,'联系人不存在！');
        //}
        $focusRelationModel = new Focus_relation();
        $contactModel = new Contact();
        $userModel = new User();
        $messageModel = new Message();
        if($request->type==1){
            $s_data = [
                'is_attention'=>1,
                'updated_at'=>date('Y-m-d H:i:s')
            ];
            $contact = $contactModel->where(['id'=>$request->id])->first();
            if($contact){
                $user = $userModel->where(['mobile'=>$contact['mobile']])->first(['id']);
            }else{
                showMsg(2,$this->nullClass);
            }
        }
        if($user){
            if($user_info->id==$request->id){
                showMsg(2,$this->nullClass);
            }
            $data = [
                'from'=>$user_info->id,
                'to'=>$request->id,
                'created_at'=>date('Y-m-d H:i:s')
            ];

            $rev_data = [
                'from'=>$request->id,
                'to'=>$user_info->id,
                'created_at'=>date('Y-m-d H:i:s')
            ];
            //has info
            $relationArr = [
                'from'=>$user_info->id,
                'to'=>$request->id
            ];
            $relation = $focusRelationModel->where($relationArr)->first();
            if($relation){
                $data['is_attention'] = 1;
                showMsg(2,$data,'切勿重复关注！');
            }
            $res = $focusRelationModel->insert($data);
            if($res){

                $focus_data = [
                    'type'=>4,
                    'msgId'=>0,
                    'from'=>$data['from'],
                    'to'=>$data['to'],
                    'img_url'=>'https://dl.dafengcheapp.com/storage/sys.png',
                    'title'=>'系统消息',
                    'content'=>$user_info->nickname.'关注了你',
                    'created_at'=>date('Y-m-d H:i:s'),
                    'user_id'=>$data['from']
                ];

                $messageModel->insert($focus_data);
                //$focusRelationModel->insert($rev_data);
                $data['is_attention'] = 1;
                showMsg(1,$data);
            }else{
                showMsg(2,[]);
            }
        }else{
            showMsg(2,$this->nullClass);
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
            $list = $list['data'];
        }
        foreach($list as &$v){
            if($v->tags){
                $tags_arr = explode(',',$v->tags);
                $tags_arr = array_values(array_filter($tags_arr));
                $v->tags = implode(',',$tags_arr);
            }
        }
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
    /*
     *delete tag
     */
    public function deleteTag(Request $request){
        
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
        $string = str_replace($request->tag,'',$style->tags);
        $string= ltrim($string,',');
        $string = str_replace(',,',',',$string);
        $data = [
            'tags'=>$string,
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

    /*
     *upload img/video
     */
    public function upload(Request $request){
        if ($request->isMethod('POST')){
            $file = $request->file('file');
            //判断文件是否上传成功
            if ($file){
                //原文件名
                $originalName = $file->getClientOriginalName();
                //扩展名
                $ext = $file->getClientOriginalExtension();
                //MimeType
                $type = $file->getClientMimeType();
                //临时绝对路径
                $realPath = $file->getRealPath();
                $filename = uniqid().'.'.$ext;
                $bool = $request->file('file')->move(storage_path().'/app/public/', $filename);
                //$bool = Storage::disk('public')->put($filename,file_get_contents($realPath));
                //判断是否上传成功
                $filename = 'https://'.$_SERVER['HTTP_HOST'].'/storage/'.$filename;
                if($bool){
                    showMsg(1,['file'=>$filename],'上传成功！');
                }else{
                    showMsg(1,[],'上传成功！');
                }
            }
        }
    }

    /*
     *test
     */
    public function uploadResource(Request $request){
        $watermark = false;
        if ($_FILES) {
            $mulu = date('Y_m_d');
            $uppath = storage_path() . '/app/public/' . $mulu;
//            var_dump($uppath);die;
            if (!is_dir($uppath)) {
                if (!mkdir($uppath, 0777)) {
                    return false; //目录创建失败
                }
            }

            $return = array();

            $jpg = array('gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico', 'mp4');

            foreach ($_FILES as $k => $v) {
                if (is_array($v["name"])) { //多个图片
                    foreach ($v['error'] as $kk => $vv) {
                        if ($vv === 0) {
                            $houzhui = pathinfo($v['name'][$kk]);
                            $houzhui = strtolower($houzhui['extension']);
                            if (!in_array($houzhui, $jpg)) {
                                continue; //不合法的图片将不上传
                            }
                            $luan = md5(time() . mt_rand(1, 99999));
                            $wenjian = $luan . '.' . $houzhui;
                            //移动到服务器的图片名
                            $newpic = $uppath . '/' . $wenjian;
                            if (move_uploaded_file($v['tmp_name'][$kk], $newpic)) {
                                if ($watermark && $houzhui !== "mp4") {
                                    CImg::watermark(storage_path() . '/app/public/watermark.png', $newpic);
                                }
                                $return[$k][$kk]["relative_path"] = "https://" . $_SERVER['HTTP_HOST'] . "/assets/static/upload/image/" . $mulu . "/" . $wenjian;
                                $return[$k][$kk]["physical_path"] = $newpic;
                                if ($houzhui !== "mp4") {
                                    //在上传图片的时候，获得图片的宽高
                                    if(@$info = getimagesize($newpic)){
                                        $return[$k][$kk]["width"] = isset($info[0]) ? $info[0] : 600;
                                        $return[$k][$kk]["height"] = isset($info[1]) ? $info[1] : 600;
                                    }
                                    $thumb = CImg::cutImg($newpic, 600, 600);
                                    $thumb_name = pathinfo($thumb, PATHINFO_BASENAME);
                                    $return[$k][$kk]["relative_path_thumb"] = "https://" . $_SERVER['HTTP_HOST'] . "/storage/app/public/" . $mulu . "/" . $thumb_name;
                                    $return[$k][$kk]["physical_path_thumb"] = $thumb;
                                }
                            }
                        }
                    }
                } else {
                    //判读是否上传成功
                    if ($v['error'] !== 0) {
                        continue;
                    }
                    //pathinfo   dirname
                    $houzhui = pathinfo($v['name']);
                    $houzhui = strtolower($houzhui['extension']);
                    if (!in_array($houzhui, $jpg)) {
                        continue; //不合法的图片将不上传
                    }

                    $luan = md5(time() . mt_rand(1, 99999));
                    $wenjian = $luan . '.' . $houzhui;
                    //移动到服务器的图片名
                    $newpic = $uppath . '/' . $wenjian;
                    if (move_uploaded_file($v['tmp_name'], $newpic)) {
                        if ($watermark && $houzhui !== "mp4") {
                            print_r($newpic);die;
                            CImg::watermark(storage_path() . '/app/public/watermark.png', $newpic);
                        }

                        $return[$k]["relative_path"] = "https://" . $_SERVER['HTTP_HOST'] . "/storage/app/public/" . $mulu . "/" . $wenjian;
                        $return[$k]["physical_path"] = $newpic;

                        if ($houzhui !== "mp4") {
                            //在上传图片的时候，获得图片的宽高
                            if(@$info = getimagesize($newpic)){
                                $return[$k]["width_height"] = isset($info[0]) && isset($info[1]) ? $info[0]."_".$info[1] : "";
                            }
                            $thumb = CImg::cutImg($newpic, 600, 600);
                            $thumb_name = pathinfo($thumb, PATHINFO_BASENAME);
                            $return[$k]["relative_path_thumb"] = "https://" . $_SERVER['HTTP_HOST'] . "/storage/app/public/" . $mulu . "/" . $thumb_name;
                            $return[$k]["physical_path_thumb"] = $thumb;
                        }
                    }
                }
            }
            // var_dump($return);die;
            print_r($return);die;
            return $return;
        }

        return false;
    }

    /*
     *get userinfo
     */
    public function userInfo(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $model = new User();
        $info = getUserInfo($request->token);
        $info->meet_num = '20';
        if($info){
            $info = (array)$info;
            unset($info['clear-text_password']);
            showMsg(1,$info);
        }else{
            showMsg(2,$this->nullClass);
        }
    }
    /*
     *cutword分词
     */
    public function tsListByCutWord(Request $request){
        //ini_set('memory_limit', -1);

        define('_VIC_WORD_DICT_PATH_',base_path().'/vendor/lizhichao/word/Data/dict.igb');
        $fc = new VicWord('igb');

        $word = $request->word?$request->word:'怜香惜玉也得要看对象';
        if($word=='好玩'){
            $seg_list  = array(
                '好玩',
                '的',
                '小',
                '小',
                '喵',
            );
        }else{
            //Jieba::init();
            //Finalseg::init();
            //$seg_list = Jieba::cut($word,true);
            $segt_list = $fc->getShortWord($word);
        }
        if(is_array($segt_list)){
            foreach($segt_list as &$v){
                $seg_list[] = $v[0];
            }
        }else{
            $seg_list = [];
        }
        $res = [];
        $model = new Video_resource();
        $ts_word = [];
        foreach($seg_list as $v){
            $ts_info = $model->where('words',$v)->first();
            if($ts_info){
                $mb_arr = $v;
                $ts_info = $ts_info->toArray();
                $ts_info['words'] = $v;
                $ts_info['download_url'] = 'https://dl.dafengcheapp.com/api/gam/downLoadFile?file_name='.$ts_info['file_name'];
            }else{
                $mb_arr = mb_str_split($v);
               
            }
            $ts_word[] = $mb_arr;
        }
        $ptList = [];
        foreach($ts_word as $v){
            if(is_array($v)){
                foreach($v as $vs){
                    $ptList[] = $vs;
                }
            }else{
                
                $ptList[] = $v;
            }
        }
        foreach($ptList as $v){
            $ts_info = $model->where('words','=',$v)->first();
            if($ts_info){
                $mb_arr = $v;
                $ts_info = $ts_info->toArray();
                $ts_info['words'] = $v;
                $ts_info['download_url'] = 'https://dl.dafengcheapp.com/api/gam/downLoadFile?file_name='.$ts_info['file_name'];
            }else{
                $ts_info['words'] = $v;
                $ts_info['download_url'] = 'https://dl.dafengcheapp.com/api/gam/downLoadFile?file_name='.'1454057286683_bibiyaochen2';
                $ts_info['url'] = '/usr/local/homeroot/video/out/1454057286683_bibiyaochen2/1454057286683_bibiyaochen2.ts';
                $ts_info['file_name'] = '1454057286683_bibiyaochen2';
                $ts = [];
               
            }
            $res[] = $ts_info;
        }

        $res = array_filter($res);
        $res = array_values($res);

        if($res){
            showMsg(1,$res);
        }else{
            showMsg(2,[]);
        }
    }
    /*
     *同步视频文件
     */
    public function syncVideo(Request $request){
        set_time_limit(0);
        $video_path = '/usr/local/var/www/video/out';
        $file = '/usr/local/var/www/chart/file/video.php';
        if($request->env==1){
            $env = 1;
            $file = '/usr/local/var/www/chart/file/video.php';
        }else{
            $env = 2;
            $file = '/usr/local/homeroot/chart/file/video.php';
        }
        $model = new Video_resource();
        $content = @file_get_contents($file);

        if(!empty($content)){
            require_once $file;
            //$list = $content;
        }else{
            exit('2');
            $list = scanFile($video_path);
            $text='<?php $rows='.var_export($list,true).';';
            file_put_contents($file,$text);
        }
        $insert_arr = [];
        $i=1;
        foreach($rows as $k=> $v){
//            $file_info = pathinfo($v[0], PATHINFO_EXTENSION);
//            print_r($v);die;
            if(count($v)>3){
                $json_arr = json_decode($v[4],true);
                $arr = [
                    'url'=>$env==1?'/usr/local/var/www/video/out/'.$v[1].'/'.$v[0]:'/usr/local/homeroot/video/out/'.$v[1].'/'.$v[0],
                    'file_name'=>$v[1],
                    'words'=>isset($json_arr['word'])?$json_arr['word']:'',
                    'json'=>$v[4],
                    'type'=>1
                ];


            }else{
                $json_arr = json_decode($v[2],true);
                $arr = [
                    'url'=>$env==1?'/usr/local/var/www/video/out/'.$v[1].'/'.$v[0]:'/usr/local/homeroot/video/out/'.$v[1].'/'.$v[0],
                    'file_name'=>$v[1],
                    'words'=>isset($json_arr['word'])?$json_arr['word']:'',
                    'json'=>$v[2],
                    'type'=>2
                ];
            }



//            $insert_arr[] = $arr;

            $i++;
            $res = $model->insert($arr);
        }


        if($res){
            showMsg(1,[]); 
        }else{
            showMsg(2,[]);
        }
    }

    /*
     * download file
     */
    public function downLoadFile(Request $request){
        $file = $request->file_name;
        $file_path = '/usr/local/homeroot/video/out/'.$file.'/'.$file.'.ts';
        $file_name = $file.'ts';
        if(isset($request->is_video)){
            $file_path = '/usr/local/homeroot/chart/storage/app/public/'.$file;
            $file_name = $file;
        }
        if(file_exists($file_path)){
            header("Content-type:application/octet-stream");
            $filename = basename($file);
            header("Content-Disposition:attachment;filename = ".$file_name);
            header("Accept-ranges:bytes");
            header("Accept-length:".filesize($file_path));
            readfile($file_path);
        }else{
            showMsg(2,[],'文件不存在');
        }
    }

    /*
     * upload video
     */
    public function uploadVideo(Request $request){
        if(!$request->isMethod('post')){
            showMsg(2,[],'Not Allowed');
        }
        $validator = Validator::make($request->all(),[
            'video_url'=>'required',
            'token'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        //get user_info
        $user_info = getUserInfo($request->token);
        $model = new Discovery();
        
        $urlParams = parse_url($request->video_url);
        $path = $urlParams['path'];
        $video_name = array_values(array_filter(explode('/',$path)))[1];
        //todo
        //get img_url
//        $ffmpeg = \FFMpeg\FFMpeg::create();
        $root = storage_path().'/app/public/';
//
//        $video = $ffmpeg->open($root.$video_name);
//        $video->frame( \FFMpeg\Coordinate\TimeCode::fromSeconds(10))
//          ->save($root.$video_name);
        $video_root = $root.$video_name;
        $img_name = explode('.',$video_name)[0].'.jpg';
        $img_root = $root.$img_name;
        //print_r($img_root);die;
        exec("ffmpeg -i {$video_root} -y -f mjpeg -ss 0.5 -t 1  $img_root");
        $data = [
            'video_url'=>$request->video_url,
            'img_url'=>'https://'.$_SERVER['HTTP_HOST'].'/storage/'.$img_name,
            'owner_id'=>$user_info->id,
            'title'=>$request->title,
            'created_at'=>date('Y-m-d H:i:s'),
            'updated_at'=>date('Y-m-d H:i:s')
        ];
        $res = $model->insert($data);
        if($res){
            showMsg(1,$data);
        }else{
            showMsg(2,[]);
        }

    }
    /*
     *my work
     */
    public function myWorksList(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $user_info = getUserInfo($request->token);
        $model = new Discovery();
        $map = [
            'owner_id'=>$user_info->id,
        ];
        $list = $model->where($map)->orderBy('created_at','desc')->paginate(10)->toArray();
        foreach($list['data'] as  &$v){
            $v['praise_num'] = 0;
            $v['is_praise'] = 0;
        }
        if($list){
            showMsg(1,$list);
        }else{
            showMsg(2,[]);
        }
    }

    /*
     *friend list
     */
    public function friendsList(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $user_info = getUserInfo($request->token);
        $model = new Discovery();
        $map = [
            'owner_id'=>$user_info->id,
        ];
        $list = $model->where($map)->orderBy('created_at','desc')->paginate(10)->toArray();
        $userModel = new User();
        foreach($list['data'] as &$v){
            $user_info = $userModel->userInfo($v['owner_id']);
            $v['nickname'] = $user_info['nickname'];
            $v['avatar_url'] = $user_info['avatar_url'];
        }
        if($list){
            showMsg(1,$list);
        }else{
            showMsg(2,[]);
        }
    }

    /*
     *friend list
     */
    public function recommendList(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $user_info = getUserInfo($request->token);
        $model = new Discovery();
        $map = [
            // 'owner_id'=>$user_info->id,
        ];
        $list = $model->where($map)->orderBy('created_at','desc')->paginate(10)->toArray();

        $userModel = new User();
        foreach($list['data'] as &$v){
            $userInfo = $userModel->userInfo($v['owner_id']);
            $v['nickname'] = $userInfo->nickname;
            $v['avatar_url'] = $userInfo->avatar_url;
            $v['is_praise'] = 0;//todo
            $v['praise_num'] = 0;
        }
        if($list){
            showMsg(1,$list);
        }else{
            showMsg(1,[]);
        }
    }

    /*
     *聊天列表
     */
    public function chartList(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required',
            'to'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }

        $user_info = getUserInfo($request->token);

        $messageModel = new \App\Model\Message();
        $from = [
            $user_info->id,
            $request->to
            //$user_info->id
        ];
        $to = [
            $request->to,
            $user_info->id
        ];

        $userModel = new User();
        $list = $messageModel->whereIn('from',$from)->whereIn('to',$to)->where('type','1')->get();
        if($list){
            $list = $list->toArray();

            foreach($list as &$v){
                $userInfo = $userModel->userInfo($v['from']);
                if($userInfo){
                    $userInfo = $userInfo->toArray();
                }
                $v['title'] = $userInfo['nickname'];
                $v['avatar_url'] = $userInfo['avatar_url'];
                $v['created_at'] = strtotime($v['created_at']).'000';
                $is_self = 0;
                if($user_info->id==$v['from']){
                    $is_self = 1;
                }
                $v['is_self'] = $is_self;
            }
        }
        
        if($list){
            showMsg(1,$list);
        }else{
            showMsg(1,[]);
        }

    }

    /*
     *chartMemberList
     */
    public function chartMemberList(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $user_info = getUserInfo($request->token);
        $keyword = $request->keyword;
        $map = [
            'from'=>$user_info->id
        ];
        $model = new Focus_relation();
        $list  = $model->where($map)->get()->toArray();
        $userModel = new User();
        if($list){
            
            $msg_list = [];
            foreach($list as $key=> &$v){
                unset($v['id']);
                //get focus user info
                if(isset($request->keyword)){
                    $map = [
                        ['nickname','like',"%$keyword%"],
                        ['id','=',$v['to']]
                    ];
                }else{
                    $map= [
                        'id'=>$v['to']
                    ];
                }
                $focus_user_info = $userModel->where($map)->first();
                $focus = $model->where(['from'=>$v['to'],'to'=>$v['from']])->first();
                if(!$focus){
                    unset($list[$key]);
                }

                if(!$focus_user_info){
                    unset($list[$key]);
                }else{
                    $focus_user_info = $focus_user_info->toArray();
                    $v['nickname'] = $focus_user_info['nickname'];
                    $v['avatar_url'] = $focus_user_info['avatar_url'];
                    $v['mobile'] = $focus_user_info['mobile'];
                    $v['user_id'] = $focus_user_info['id'];
                }

            }

            $list = array_values($list);
            //get msg
            foreach($list as $vs){
                $messageModel = new \App\Model\Message();
                if(isset($request->keyword)){
                    $where = [
                        ['from',$user_info->id],
                        ['to',$vs['to']],
                        ['content','like',"%$keyword%"]
                    ];

                }else{
                    $where = [
                        'from'=>$user_info->id,
                        'to'=>$vs['to']
                    ];
                }
                $msg_info = $messageModel->whereIn('from',[$user_info->id,$vs['to']])
                          ->whereIn('to',[$vs['to'],$user_info->id])
                          ->orderBy('id','desc')->first();
                if($msg_info){
                    $vs['content'] = $msg_info->content;
                    $msg_list[] = $vs;
                }

            }
        }
        if($list){
            $return = [
                'list'=>$list,
                'msg_list'=>$msg_list
            ];
            showMsg(1,$return);
        }else{
            showMsg(1,$this->nullClass);
        }

    }

    /*
     *search sys user
     */
    public function searchRegisteredUser(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $keyword = $request->keyword;
        $user_info = getUserInfo($request->token);
        if($keyword){
            $map = [
                ['mobile','like',"%$keyword%"],
                ['role','<>',9],
                ['id','<>',$user_info->id]
            ];
        }else{
            $map = [
                ['role','<>',9],
                ['id','<>',$user_info->id]
            ];
        }
        $userModel = new User();
        $list = $userModel->where($map)->get()->toArray();
        $modelFocusRelation = new Focus_relation();
        foreach($list as &$v){
            $where = [
                'from'=>$user_info->id,
                'to'=>$v['id']
            ];
            $info = $modelFocusRelation->where($where)->first();
            if($info){
                $v['is_attention'] = 1;
            }else{
                $v['is_attention'] = 0;
            }
        }
        if($list){
            showMsg(1,$list);
        }else{
            showMsg(1,[]);
        }
    }
    /*
     *video detail
     */
    public function videoDetail(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required',
            'id'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $model = new Discovery();
        $userModel = new User();
        $user_info = getUserInfo($request->token);
        $map = [
            'id'=>$request->id,
            //'owner_id'=>$user_info->id
        ];
        //get video
        $video_info = $model->where($map)->first();
        if($video_info){
            $video_info = $video_info->toArray();
            $video_info['tag']='专题';
            $video_info['tags'] = [
                '专题活动',
                '奖品'
            ];
            $is_focus = 0;
            if($video_info['owner_id']==$user_info->id){
                $is_focus = 1;
            }
            $owner_info = $userModel->where(['id'=>$video_info['owner_id']])->first();

            $video_info['praise_num'] = 0;
            $video_info['is_praise'] = 0;
            $video_info['collection_num'] =0;
            $video_info['comment_num'] = 0;
            $video_info['forward_num']=1;
            $video_info['is_focus'] = $is_focus;
            $video_info['is_collection'] =0;
            $video_info['created_at'] = date('Y-m-d',strtotime($video_info['created_at']));
            $video_info['nickname'] = $owner_info->nickname;
            $video_info['avatar_url'] = $owner_info->avatar_url;
            $video_info['user_id'] = $video_info['owner_id'];
            if($video_info['video_url']){
                $urlParams = parse_url($video_info['video_url']);
                $path = $urlParams['path'];
                $video_name = array_values(array_filter(explode('/',$path)))[1];
            
                $video_info['download_url'] = 'https://dl.dafengcheapp.com/api/gam/downLoadFile?file_name='.$video_name.'&is_video=1';
            }else{
                $video_info['download_url']  ='';
            }
        }
        if($video_info){
            showMsg(1,$video_info);
        }else{
            showMsg(2,$this->nullClass);
        }

    }

    /*
     *message video
     */
    public function message(Request $request){

        $validator = Validator::make($request->all(),[
            'token'=>'required',
            'id'=>'required',
            'content'=>'required'
        ]);
        if($validator->fails()){
            showMsg(1,$validator->errors());
        }
        $user_info  =getUserInfo($request->token);
        $data = [
            'content'=>$request->content,
            'video_id'=>$request->id,
            'user_id'=>$user_info->id,
            'type'=>3,
            'created_at'=>date('Y-m-d H:i:s')
        ];
        $messageModel = new \App\Model\Message();
        $res = $messageModel->insert($data);
        if($res){
            showMsg(1,$data);
        }else{
            showMsg(2,[]);
        }
    }

    /*
     * message list
     */
    public function messageList(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required',
            'id'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $messageModel = new \App\Model\Message();
        $map = [
            'video_id'=>$request->id,
        ];
        $list = $messageModel->where($map)->orderBy('created_at','desc')->get();
        $list = $list->toArray();
        $userModel = new User();
        foreach($list as &$v){
            $v['is_praise'] = 0;
            $v['praise_num'] = 1;
            $where = [
                'id'=>$v['user_id']
            ];
            $info = $userModel->where($where)->first();
            if($info){
                $info = $info->toArray();
                $v['nickname']  = $info['nickname'];
                $v['avatar_url'] = $info['avatar_url'];
            }
            $v['created_at'] = date('Y-m-d',strtotime($v['created_at']));
        }
        if($list){
            showMsg(1,$list);
        }else{
            showMsg(1,[]);
        }
    }

    /*
     *接受消息
     */
    public function receiveMessage(Request $request){
        file_put_contents('ss.txt',urldecode($request->msg_json));
        //echo urldecode($request->msg_json);die;
        $validator = Validator::make($request->all(),[
            'msg_json'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }

        $messageModel = new \App\Model\Message();
        $msg_arr = json_decode($request->msg_json,true);
        $res = 0;
        $data = [];
        if($msg_arr){
            if($msg_arr['type']=='chat'){
                $data = [
                    'msg_type'=>$msg_arr['type'],
                    'msgId'=>$msg_arr['msgId'],
                    'from'=>$msg_arr['fromId'],
                    'to'=>$msg_arr['toId'],
                    'title'=>isset($msg_arr['title'])?$msg_arr['title']:'一段视频',
                    'content'=>$msg_arr['msg'],
                    'created_at'=>date('Y-m-d H:i:s'),
                    'user_id'=>$msg_arr['fromId']
                ];
            }
            if($msg_arr['type']=='video'){
                $data = [
                    'msgId'=>$msg_arr['msgId'],
                    'msg_type'=>$msg_arr['type'],
                    'from'=>$msg_arr['fromId'],
                    'to'=>$msg_arr['toId'],
                    'title'=>isset($msg_arr['title'])?$msg_arr['title']:'一段文字',
                    'img_url'=>trim($msg_arr['imgPath']),
                    'video_url'=>trim($msg_arr['videoPath']),
                    'user_id'=>$msg_arr['fromId'],
                    'content'=>$msg_arr['msg'],
                    'created_at'=>date('Y-m-d H:i:s')
                ];
            }
            if($msg_arr['type']==8){
                $data = [
                    'type'=>4,
                    'msgId'=>$msg_arr['msgId'],
                    'from'=>$msg_arr['fromId'],
                    'to'=>$msg_arr['toId'],
                    'img_url'=>'https://dl.dafengcheapp.com/storage/sys.png',
                    'title'=>isset($msg_arr['title'])?$msg_arr['title']:'系统消息',
                    'content'=>$msg_arr['content'],
                    'created_at'=>date('Y-m-d H:i:s'),
                    'user_id'=>$msg_arr['fromId']
                ];
            }

            $res = $messageModel->insert($data);
        }
        if($res){
            exit(json_encode([
                'status'=>1,
                'type'=>$msg_arr['type']
            ]));
        }else{
            exit(json_encode([
                'status'=>0,
                'type'=>$msg_arr['type']
            ]));
        }
    }

    /*
     *homepage chart list
     */
    public function chartMessageList(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $user_info = getUserInfo($request->token);
        $model = new \App\Model\Message();

        $map = [
            $user_info->id
        ];
        DB::connection()->enableQueryLog();
        $list = $model->select('to','from','type')->distinct()
              ->whereIn('from',$map)
              ->orwhereIn('to',$map)
              ->where('to','>',0)->get();
        $ss = DB::getQueryLog();
        if($list){
            $list = $list->toArray();
            foreach($list as $k=> $v){
                if($v['type']==4){
                    unset($list[$k]);
                }
            }
            $list = array_values($list);
        }

        $userModel = new User();
        foreach($list as $k => &$v){
            if($v['from']<1||$v['to']<1){
                unset($list[$k]);
            }
            
            $ret = filterArr($v,$list);
            if($ret){
                unset($list[$k]);
            }
            if($user_info->id==$v['to']){
                $uid = $v['from'];
            }else{
                $uid = $v['to'];
            }
            $info = $userModel->where(['id'=>$uid])->orderBy('id','desc')->first();
            //get msg
            $msg_info = $model->where(['to'=>$uid])->orwhere(['from'=>$uid])->where('type','=',1)->orderBy('id','desc')->first();
            $content = '';
            if($msg_info){
                $msg_info = $msg_info->toArray();
                $content = $msg_info['content'];
            }
            if($info){
                $info = $info->toArray();
            }
            $v['nickname'] = !empty($info)?$info['nickname']:'';
            $v['avatar_url'] = !empty($info)?$info['avatar_url']:'';
            $v['content'] = $msg_info['content'];
            $v['user_id']=$info['id']?$info['id']:'';
            $v['id'] = $msg_info['id'];
            $v['is_read'] = $msg_info['is_read'];
            $v['created_at'] = strtotime($msg_info['created_at']).'000';
            $v['msg_type'] = $msg_info['msg_type'];
            $v['video_url'] = $msg_info['video_url'];
            $v['img_url'] = $msg_info['img_url'];
        }
        $list = assoc_unique($list,'id');

        $list = array_values($list);
        if(!empty($list)){
            showMsg(1,$list);
        }else{
            showMsg(1,[]);
        }


    }
    /*
     *sync style
     */
    public function syncStyle(Request $request){
        $styleModel = new Style();
        $userModel = new User();
        //get user list
        $list = $userModel->where('role','<>',9)->get();
        if($list){
            $list = $list->toArray();
        }
        $res = 0;
        foreach($list as $v){
            $style = $styleModel->where('user_id','=',$v['id'])->get();
            if($style){
                $style = $style->toArray();
            }
            if(!$style){
                $style_arr = [
                    [
                        'title'=>'希望出演的名人',
                        'description'=>'我们会激励邀请对方出演，虽然TA不一定回来',
                        'created_at'=>date('Y-m-d'),
                        'user_id'=>$v['id']
                    ],
                    [
                        'title'=>'演员黑名单',
                        'description'=>'我嗯保证TA不会出演你的影片',
                        'user_id'=>$v['id'],
                        'created_at'=>date('Y-m-d H:i:s')
                    ]
                ];
                $res = $styleModel->insert($style_arr);
            }
        }
        if($res){
            showMsg(1,$this->nullClass);
        }else{
            showMsg(2,$this->nullClass);
        }

    }

    /*
     *system msg
     */
    public function sysMsg(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $messageModel = new Message();
        $user_info = getUserInfo($request->token);
        $message = $messageModel->where(['to'=>$user_info->id,'type'=>4,'is_read'=>0])->orderBy('id','desc')->first();
        
        if($message){
            $message = $message->toArray();
            $message['created_at'] = strtotime($message['created_at']).'0000';
            showMsg(1,$message);
        }else{
            showMsg(1,$this->nullClass);
        }
    }

    /*
     *sys list
     */
    public function sysMsgList(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $messageModel = new Message();
        $user_info = getUserInfo($request->token);
        $message = $messageModel->where(['to'=>$user_info->id,'type'=>4,'is_read'=>0])->orderBy('id','desc')->get();
        if($message){
            $message = $message->toArray();
            foreach($message as &$v){
                $v['created_at'] = strtotime($v['created_at']).'0000';
            }
            showMsg(1,$message);
        }else{
            showMsg(1,$this->nullClass);
        }
    }

    /*
     *view sysMsg
     */
    public function viewSysMsg(Request $request){
        $validator = Validator::make($request->all(),[
            'token'=>'required',
            'id'=>'required'
        ]);
        if($validator->fails()){
            showMsg(2,$validator->errors());
        }
        $messageModel = new Message();
        $message = $messageModel->where(['id'=>$request->id,'type'=>4])->first();
        if(!$message){
            showMsg(2,$this->nullClass,'无此系统消息！');
        }
        $focusModel = new Focus_relation();
        //dowith focus
        $focus_data = [
            'from'=>$message->to,
            'to'=>$message->from,
            'created_at'=>date('Y-m-d H:i:s')
        ];
        $focus_info = $focusModel->where(['from'=>$message->to,'to'=>$message->from])->first();
        if($focus_info){
            showMsg(1,$this->nullClass);
        }else{
            $res = $focusModel->insert($focus_data);
            if($res){
                //update msg_info
                $messageModel->where(['id'=>$message->id])->update(['is_read'=>1,'updated_at'=>date('Y-m-d H:i:s')]);
                showMsg(1,$this->nullClass);
            }else{
                shwoMsg(2,$this->nullClass,'无此系统消息！');
            }
        }
    }
}

