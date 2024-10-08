<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\USSDController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\USSDReferenceController; 
use App\Http\Controllers\Peer2PeerController;
use App\Http\Controllers\P2PCallbackController;




/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider, and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/ussd/{merchant_id}', [USSDController::class, 'handleUSSDRequest']);
Route::post('/payment-callback', [PaymentCallbackController::class, 'handlePaymentCallback']);
Route::post('/handle-reference/{merchant_id}', [USSDReferenceController::class, 'handleReferenceRequest']); 
Route::post('/p2p/{merchant_id}', [Peer2PeerController::class, 'handleP2PRequest']);
Route::post('/peer2peer-callback', [P2PCallbackController::class, 'handleP2PCallback']);

