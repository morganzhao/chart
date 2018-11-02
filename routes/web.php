<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//Route::get('/', function () {
//  return view('welcome');
//});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');



Route::get('/blog/upload','BlogController@upload')->name('blog.upload');


Route::get('/blog/{action}', function(App\Http\Controllers\BlogController $index, $action){
    return $index->$action();
});


Route::resource('blog', 'BlogController');
