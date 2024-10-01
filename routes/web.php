<?php

use App\Http\Controllers\Module\ServerController;
use App\Http\Controllers\Module\PanelController;
use App\Http\Controllers\Module\TestController;
use Illuminate\Support\Facades\Route;

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
//
//Route::group(['namespace' => 'Module', 'prefix' => 'module'], function () {
//    Route::get('test', 'TestController@index')->name('module.test.index');
//});

//Route::get('/test', [TestController::class, 'index'])->name('test');
//Route::get('/panel', [TestController::class, 'panel'])->name('panel');

Route::get('/server/index', [ServerController::class, 'index'])->name('module.server.index');
Route::get('/server/create', [ServerController::class, 'create'])->name('module.server.create');


Route::get('/panel/index', [PanelController::class, 'index'])->name('module.panel.index');
Route::get('/panel/create', [PanelController::class, 'create'])->name('module.panel.create');

//Route::get('/panel', [ServerController::class, 'panel'])->name('panel');

//Route::get('/', function () {
//    return view('welcome');
//});
