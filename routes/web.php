<?php

use App\Http\Controllers\Module\ServerController;
use App\Http\Controllers\Module\PanelController;
use App\Http\Controllers\Module\TestController;
use App\Http\Controllers\Module\SalesmanController;
use App\Http\Controllers\Module\PackController;
use App\Http\Controllers\Module\PackSalesmanController;
use App\Http\Controllers\Module\KeyActivateController;
use App\Http\Controllers\Module\BotController;
use App\Services\Telegram\ModuleBot\FatherBotController;
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

Route::get('/salesman/index', [SalesmanController::class, 'index'])->name('module.salesman.index');

Route::get('/pack/index', [PackController::class, 'index'])->name('module.pack.index');

Route::get('/pack-salesman/index', [PackSalesmanController::class, 'index'])->name('module.pack-salesman.index');

Route::get('/key-activate/index', [KeyActivateController::class, 'index'])->name('module.key-activate.index');

Route::get('/bot/index', [BotController::class, 'index'])->name('module.bot.index');
Route::post('/bot/update', [BotController::class, 'update'])->name('module.bot.update');

//Route::get('/father-bot/init', [FatherBotController::class, 'init'])->name('father-init');

Route::get('/test/salesman', [TestController::class, 'salesman'])->name('module.test.salesman');
Route::get('/test/pack', [TestController::class, 'pack'])->name('module.test.pack');
Route::get('/test/pack-salesman', [TestController::class, 'packSalesman'])->name('module.test.pack-salesman');
Route::get('/test/key-activate', [TestController::class, 'keyActivate'])->name('module.test.key-activate');
Route::get('/test/key-user', [TestController::class, 'keyUser'])->name('module.test.key-user');

//Route::get('/panel', [ServerController::class, 'panel'])->name('panel');

//Route::get('/', function () {
//    return view('welcome');
//});
