<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class VideoResources extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('video_resources', function (Blueprint $table) {
            $table->increments('id');
            $table->string('url')->comment('视频地址')->nullable;
            $table->string('json')->comment('视频属性：演员姓名等信息')->nullable;
            $table->text('words')->comment('关键字')->nullable;
            $table->string('file_name')->comment('文件名')->nullable;
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
        Schema::dropIfExists('video_resources');
    }
}
