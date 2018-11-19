<?php
use Illuminate\Support\Facades\DB;
/**
 * 公用的方法  返回json数据，进行信息的提示
 * @param $status 状态
 * @param string $message 提示信息
 * @param array $data 返回数据
 */
function showMsg($type,$data = array(),$msg=''){
    if($msg==''&&$type==1){
        $msg = '操作成功！';
    }else{
        $msg = $msg?$msg:'操作失败！';
    }
    $result = array(
        'status' => $type==1?1:0,
        'message' =>$msg,
        'data' =>$data
    );
    exit(json_encode($result));
}

function getSql($param){
    DB::connection()->enableQueryLog();
    $execute = $param;
    $sql = DB::getQueryLog();
    return $sql;
}

function getUserInfo($token){
   $info = DB::table('users')->where('token',$token)->first();
   if($info){
       return $info;
   }else{
       showMsg(2,new \stdclass,'无效的token！');
   }
}

function scanFile($path) {
    global $result;
    $files = scandir($path);
    $arr = [];
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            if (is_dir($path . '/' . $file)) {
                scanFile($path . '/' . $file);
            } else {
                $pathinfo = pathinfo($path);
                $filename = $pathinfo['filename'];
                $arr[] = basename($file);
                array_push($arr,$filename);
                $arr = array_unique($arr);
                //获取文件内容
                $intro = '';
                foreach($arr as $vs){
                    if($vs=='intro.txt'){
                        $intro = $vs;
                    }
                }

                $content = file_get_contents($path.'/'.$intro);
                array_push($arr,$content);
                $arr = array_filter($arr);
                $result[$filename] = $arr;
            }
        }

    }
    return $result;
}


function mb_str_split ($string, $len=1) {
	$start = 0;
	$strlen = mb_strlen($string);
	while ($strlen) {
		$array[] = mb_substr($string,$start,$len,"utf-8");
		$string = mb_substr($string, $len, $strlen,"utf-8");
		$strlen = mb_strlen($string);
	}
	return $array;
}


function filterArr($data,$arr){
    foreach($arr as $v){
        if($v['to']==$data['from']){
            return true;
        }
        return false;
    }
}


function assoc_unique($arr, $key) {

    $tmp_arr = array();

    foreach ($arr as $k => $v) {

        if (in_array($v[$key], $tmp_arr)) {//搜索$v[$key]是否在$tmp_arr数组中存在，若存在返回true

            unset($arr[$k]);

        } else {

            $tmp_arr[] = $v[$key];

        }

    }

    // sort($arr); //sort函数对数组进行排序

    return $arr;

}
function test($a=0,&$result=array()){
    $a++;
    if ($a<10) {
        $result[]=$a;
        test($a,$result);
    }
    print_r($a);die;
    echo $a;
    return $result;

}
