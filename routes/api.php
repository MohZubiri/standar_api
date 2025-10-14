<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Services\Biller\BillerClient;
use Smalot\PdfParser\Parser as PdfParser;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
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

Route::get('artisan',function(){
	$exitCode = Artisan::call('config:clear');
    $exitCode = Artisan::call('cache:clear');
    $exitCode = Artisan::call('view:clear');
    $exitCode = Artisan::call('route:clear');
    $exitCode = Artisan::call('clear-compiled');

	return 'done';

});
Route::get('/showlogs/{date}', 'GenralController@showLogs');



Route::group(['middleware' => ['check_ip']], function () {

//Route::post('login', 'API\ConnectionController@login');

Route::get('check_blance', 'API\PostController@check_blance');


Route::group(['middleware' => ['connection']], function () {
    Route::middleware('auth:api')->group(function () {
	// E-SADAD Gateway → Biller REST APIs
// Paths: /{Biller_Code}/{Service_Type}/...
Route::prefix('biller')
    ->middleware(['verify.gateway.signature', 'throttle:gateway'])
    ->group(function () {
        Route::post('{billerCode}/{serviceType}/Biller_Bill_Presentment', [\App\Http\Controllers\BillerGatewayController::class, 'presentment']);
        Route::post('{billerCode}/{serviceType}/Biller_Bill_Payment', [\App\Http\Controllers\BillerGatewayController::class, 'payment']);
        Route::post('{billerCode}/{serviceType}/Biller_Bill_Payment_Notification', [\App\Http\Controllers\BillerGatewayController::class, 'paymentNotification']);
    });

	Route::POST('dialy_check', 'API\ReciveController@totalDyileInvoice');
	Route::POST('send_dialy_invoices', 'API\ReciveController@reciveInvoices');

	//Route::POST('paid_fees', 'API\PostController@fees_payment');

    Route::POST('get_invoice', 'API\PostController@showinvoice');
    Route::POST('get_invoice_details', 'API\PostController@show_invoice_details');
    Route::POST('pay_invoice', 'API\PostController@store');
    Route::POST('get_paid_invoices', 'API\PostController@show_index');




});

});
});
