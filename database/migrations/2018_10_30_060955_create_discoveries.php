<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDiscoveries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discoveries', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->comment('用户id');
            $table->integer('owner_id')->comment('归属人id');
            $table->string('title',500)->comment('标题');
            $table->string('remark',500)->comment('描述');
            $table->string('video_url',500)->comment('视频地址');
            $table->string('img_url',500)->comment('图片地址');
            $table->tinyInteger('vedio_type')->comment('视频类型：例：专题，视频枚举类型');
            $table->tinyInteger('resource_type')->comment('播放类型：1:视频；2:图片');
            $table->integer('click_num')->comment('点击/播放次数');
            $table->tinyInteger('type')->comment('type：1:好友，2:推荐');
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
        Schema::dropIfExists('discoveries');
    }
}
