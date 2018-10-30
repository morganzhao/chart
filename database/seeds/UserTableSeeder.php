<?php

use Illuminate\Database\Seeder;
use App\User;
class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\User::class,50)->create();
        $user = User::find(1);
        $user->name = 'test';
        $user->email = 'test@qq.com';
        $user->password = bcrypt('123456');
        $user->save();
        //
    }
}
