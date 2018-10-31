<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('signature');
            $table->string('nickname');
            $table->integer('age');
            $table->tinyInteger('sex')->default(1)->comment('性别');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('salt')->comment('盐');
            $table->string('password');
            $table->string('token',500)->comment('登录令牌');
            $table->integer('follow_num')->comment('关注数');
            $table->integer('fans_num')->comment('粉丝数');
            $table->integer('praise_num')->comment('获赞数');
            $table->string('avatar_url',500)->comment('头像地址');
            $table->string('vedio_url',500)->comment('视频地址');
            $table->dateTime('expire')->comment('验证码过期时间');
            $table->dateTime('time_expire')->comment('验证码未过期时间');
            $table->integer('sms_code')->comment('短信验证码');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
