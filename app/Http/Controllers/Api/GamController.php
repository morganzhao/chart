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

        $yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        $rand= $yCode[intval(date('Y')) - 2011] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
        $data = [
            'mobile'=>$request->mobile,
            'token'=>$rand,
            'password'=>Hash::make($request->password),
            'clear-text_password'=>$request->password,

        ];
        $userModel = new User;

        $user = $userModel->hasUser($request->mobile);
        if($user&&$user->mobile){
            showMsg(2,$this->nullClass,'手机号已注册！');
        }
        $id = $userModel->add($data);
        if($id){
            unset($data['clear-text_password']);
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
            showMsg(2,$this->nullClass);
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
            $v['created_at'] = date('Y-m-d H:i:s');
            $v['is_attention'] = 0;
            //是否同步
            $contact_info = Contact::where('mobile',$v['mobile'])->first();
            if($contact_info){
                $v['updated_at'] = date('Y-m-d H:i:s');
                $res = Contact::where('id',$contact_info['id'])->update($v);
            }else{
                
                $res = Contact::insert($v);
            }
        }
        if($res){
            $mobiles = array_column($json_to_array,'mobile');
            $list = Contact::whereIn('mobile',$mobiles)->get()->toArray();
            foreach($json_to_array as &$v){
                $account_info = User::where('mobile',$v['mobile'])->first();
                $v['nickname'] = $account_info['nickname'];
                if($account_info){
                    $v['is_attention'] = $v['is_attention']?$v['is_attention']:0;
                    $v['is_register'] = 1;
                }else{
                    $v['is_attention'] = $v['is_attention']?$v['is_attention']:0;
                    $v['is_register'] = 0;
                }
            }
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
            $map['id'] = $user_info->id;
        }
        $map['id'] = $request->id;
        //get contact
        $model = new User();
        $user = $model->where($map)->first();
        if(!$user){
            showMsg(2,$this->nullClass,'联系人不存在！');
        }
        $focusRelationModel = new Focus_relation();
        if($user){
            $data = [
                'from'=>$user_info->id,
                'to'=>$request->id,
                'created_at'=>date('Y-m-d H:i:s')
            ];
            //has info
            $relationArr = [
                'from'=>$user_info->id,
                'to'=>$request->id
            ];
            $relation = $focusRelationModel->where($relationArr)->first();
            if($relation){
                showMsg(1,$data);
            }
            $res = $focusRelationModel->insert($data);
            if($res){
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
                $filename = 'http://'.$_SERVER['HTTP_HOST'].'/storage/'.$filename;
                if($bool){
                    showMsg(1,['file'=>$filename],'上传成功！');
                }else{
                    showMsg(2,[],'上传失败！');
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
                                $return[$k][$kk]["relative_path"] = "http://" . $_SERVER['HTTP_HOST'] . "/assets/static/upload/image/" . $mulu . "/" . $wenjian;
                                $return[$k][$kk]["physical_path"] = $newpic;
                                if ($houzhui !== "mp4") {
                                    //在上传图片的时候，获得图片的宽高
                                    if(@$info = getimagesize($newpic)){
                                        $return[$k][$kk]["width"] = isset($info[0]) ? $info[0] : 600;
                                        $return[$k][$kk]["height"] = isset($info[1]) ? $info[1] : 600;
                                    }
                                    $thumb = CImg::cutImg($newpic, 600, 600);
                                    $thumb_name = pathinfo($thumb, PATHINFO_BASENAME);
                                    $return[$k][$kk]["relative_path_thumb"] = "http://" . $_SERVER['HTTP_HOST'] . "/storage/app/public/" . $mulu . "/" . $thumb_name;
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

                        $return[$k]["relative_path"] = "http://" . $_SERVER['HTTP_HOST'] . "/storage/app/public/" . $mulu . "/" . $wenjian;
                        $return[$k]["physical_path"] = $newpic;

                        if ($houzhui !== "mp4") {
                            //在上传图片的时候，获得图片的宽高
                            if(@$info = getimagesize($newpic)){
                                $return[$k]["width_height"] = isset($info[0]) && isset($info[1]) ? $info[0]."_".$info[1] : "";
                            }
                            $thumb = CImg::cutImg($newpic, 600, 600);
                            $thumb_name = pathinfo($thumb, PATHINFO_BASENAME);
                            $return[$k]["relative_path_thumb"] = "http://" . $_SERVER['HTTP_HOST'] . "/storage/app/public/" . $mulu . "/" . $thumb_name;
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
            $seg_list = $fc->getShortWord($word);
        }

        if(is_array($seg_list)){

        }else{
            $seg_list = [];
        }
        $res = [];
        $model = new Video_resource();
        foreach($seg_list as $v){
            $ts_info = $model->where('words',$v)->first();
            if($ts_info){
                $ts_info['download_url'] = 'http://39.104.17.209:8090/api/gam/downLoadFile?file_name='.$ts_info['file_name'];
            }else{
                $ts_info['words'] = $v;
            }
            $res[] = $ts_info;
        }
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
                    'url'=>$env==1?'/usr/local/让var/www/video/out/'.$v[1].'/'.$v[0]:'/usr/local/homeroot/video/out/'.$v[1].'/'.$v[0],
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
        if(file_exists($file_path)){
            header("Content-type:application/octet-stream");
            $filename = basename($file);
            header("Content-Disposition:attachment;filename = ".$file.'.ts');
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
        exec("ffmpeg -i {$video_root} -y -f mjpeg -ss 1 -t 1  $img_root");
        $data = [
            'video_url'=>$request->video_url,
            'img_url'=>'http://'.$_SERVER['HTTP_HOST'].'/storage/'.$img_name,
            'owner_id'=>$user_info->id,
            'title'=>$request->title,
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
            $user_info = $userModel->userInfo($v['id']);
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
            'owner_id'=>$user_info->id,
        ];
        $list = $model->where($map)->orderBy('created_at','desc')->paginate(10)->toArray();

        $userModel = new User();
        foreach($list['data'] as &$v){
            $userInfo = $userModel->userInfo($v['owner_id']);
            $v['nickname'] = $userInfo->nickname;
            $v['avatar_url'] = $userInfo->avatar_url;
            $v['is_praise'] = 0;//todo
        }
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

        $userModel = new User();
        $list = $messageModel->where($map)->get();
        if($list){
            foreach($list as &$v){
                $userInfo = $userModel->userInfo($v['from']);
                $v['title'] = $userInfo->nickname;
                $v['avatar_url'] = $userInfo->avatar_url;
            }
        }
        
        if($list){
            showMsg(1,$list);
        }else{
            showMsg(2,[]);
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
        $map = [
            'from'=>$user_info->id
        ];
        $model = new Focus_relation();
        $list  = $model->where($map)->get();
        $userModel = new User();
        if($list){
            foreach($list as &$v){
                unset($v['id']);
                //get focus user info
                $focus_user_info = $userModel->userInfo($v['to']);
                $v['nickname'] = $focus_user_info->nickname;
                $v['avatar_url'] = $focus_user_info->avatar_url;
                $v['mobile'] = $focus_user_info->mobile;
            }
        }
        if($list){
            showMsg(1,$list);
        }else{
            showMsg(2,[]);
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
        if($keyword){
            $map = [
                ['mobile','like',"%$keyword%"],
                ['role','<>',9]
            ];
        }else{
            $map = [
                ['role','<>',9]
            ];
        }
        $userModel = new User();
        $list = $userModel->where($map)->get()->toArray();
        if($list){
            showMsg(1,$list);
        }else{
            showMsg(2,[]);
        }
    }
}

