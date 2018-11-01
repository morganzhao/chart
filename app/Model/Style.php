<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class Style extends Model
{
    //

    public function getList($map,$limit=10){
        return DB::table($this->getTable())->where($map)->paginate($limit)->toArray();
    }
}
