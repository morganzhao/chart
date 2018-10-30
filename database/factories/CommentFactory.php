<?php

use Faker\Generator as Faker;

$factory->define(App\Comment::class, function (Faker $faker) {
    return [
        'content'=>$faker->text,
        'blog_id'=>1,
        'user_id'=>1
        //
    ];
});
