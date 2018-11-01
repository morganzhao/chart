<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//  return $request->user();
//});
Route::post('/gam/index', 'GamController@index')->name('index');


//Route::post('/gam/{action}',function(App\Http\Controllers\Api\GamController $index,$action){
//  return $index->$action();
//});

Route::post('/gam/register','GamController@register')->name('register');

Route::any('/gam/syncContacts','GamController@syncContacts')->name('syncContacts');

Route::any('/gam/contactList','GamController@contactList')->name('contactList');


Route::any('/gam/login','GamController@login')->name('login');

Route::any('/gam/send','GamController@send')->name('send');

Route::any('/gam/updateUserInfo','GamController@updateUserInfo')->name('updateUserInfo');

Route::any('/gam/resetPassword','GamController@resetPassword')->name('resetPassword');

Route::any('/gam/focus','GamController@focus')->name('focus');


Route::any('/gam/styleList','GamController@styleList')->name('styleList');


Route::any('/gam/addTag','GamController@addTag')->name('addTag');
