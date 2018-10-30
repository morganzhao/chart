<?php

use Illuminate\Database\Seeder;
use App\Model\Blog;
class BlogTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        factory(App\Model\Blog::class,50)->create();
        $blog = Blog::find(1);
        $blog->title = 'test';
        $blog->content = 'this is a text';
        $blog->save();

    }
}
 
