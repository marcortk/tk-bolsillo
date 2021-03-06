<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of the routes that are handled
| by your application. Just tell Laravel the URIs it should respond
| to using a Closure or controller method. Build something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/my-account/{id}', 'AccountController@show');
Route::get('/my-accounts', ['as' => 'my_accounts', 'uses' => 'AccountController@index']);
Route::get('/my-accounts/add', ['as' => 'my_accounts.add', 'uses' => 'AccountController@create']);
Route::post('/my-accounts/add', ['as' => 'my_accounts.store', 'uses' => 'AccountController@store']);
$router->post('/transactions', ['as' => 'transactions.add', 'uses' => 'TransactionController@add']);
