<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMessages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->default(0);
            $table->string('title')->comment('标题');
            $table->integer('from')->comment('发消息人的id');
            $table->integer('to')->comment('收消息人的id');
            $table->string('video_url',500)->comment('视频地址');
            $table->string('img_url',500)->comment('图片地址');
            $table->text('content')->comment('留言内容');
            $table->tinyInteger('type')->comment('类型，1:聊天，2:系统消息')->default(1);
            $table->integer('praise_num')->comment('点赞数')->default(0);
            $table->integer('is_read')->comment('是否已读')->default(0);
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
        Schema::dropIfExists('messages');
    }
}
