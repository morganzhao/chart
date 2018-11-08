<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        '', '',
    ];

    public function add($data){
        return $this->insertGetId($data);
    }

    public function hasUser($mobile){
        return $this->where('mobile',$mobile)->first();
    }

    public function userInfo($id){
        return $this->where('id',$id)->first();
    }
}
