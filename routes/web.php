<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\USSDController;
use App\Http\Controllers\PaymentCallbackController;



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

//Route::get('/', function () {
  //  return view('welcome');
//});

Route::post('/ussd', [USSDController::class, 'handleUSSDRequest']);
Route::post('/payment-callback', 'PaymentCallbackController@handlePaymentCallback');
Route::post('/payment-callback', [PaymentCallbackController::class, 'handlePaymentCallback']);
