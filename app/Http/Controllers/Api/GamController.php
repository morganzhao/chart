<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Model\Blog;

class GamController extends Controller
{
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
        exit('22');
    }
}

