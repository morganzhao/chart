<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Contact extends Model
{
    //

    public function insertAll($arr){
        return DB::table($this->getTable())->insert($arr);
    }

    public function getList($map,$limit=10){
        return DB::table($this->getTable())->where($map)->paginate($limit)->toArray();
    }
}
