<?php
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Services\Biller\BillerClient;
use Smalot\PdfParser\Parser as PdfParser;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
$router->get('/showlogs/{date}', 'GenralController@showLogs');

$router->get('/run-process-pending', function () {
    $exitCode = Illuminate\Support\Facades\Artisan::call('payments:process-pending');
    return 'Command executed with exit code: ' . $exitCode;
});
$router->get('artisan',function(){

	//$exitCode = Illuminate\Support\Facades\Artisan::call('config:clear');
    $exitCode = Illuminate\Support\Facades\Artisan::call('cache:clear');
    $exitCode = Illuminate\Support\Facades\Artisan::call('view:clear');
    $exitCode = Illuminate\Support\Facades\Artisan::call('route:clear');
    $exitCode = Illuminate\Support\Facades\Artisan::call('clear-compiled');

	return 'done';

});
$router->get('vvvv','API\PostController@testing');
$router->get('/', function () use ($router) {
    return $router->app->version();
});
$router->get('/TIME', function ()  {
	return DB::connection('mysql1')->select('select CURRENT_TIME();');

});
$router->get('/TIMEs', function ()  {
    return  date('Y-m-d H:i:s');
});



$router->group(['prefix' => 'api'], function () use ($router)
{
    $router->get('sumSarInvoice', 'API\ReciveController@culculated');
    $router->post('login', 'AuthController@beforelogin');

  $router->post('register', 'AuthController@register');
  //  $router->get('check_blance', 'API\PostController@check_blance');
});
$router->group(['middleware' => 'auth','prefix' => 'api'], function ($router) {
$router->group(['middleware' => 'check_ip'], function ($router) {



   $router->POST('checkOut', 'API\CheckingController@getCheckOut');
   $router->post('refresh', 'AuthController@refresh');
     $router->post('verification', 'API\PostController@invoiceVerification');

      $router->POST('get_invoice', 'API\PostController@showinvoice');
      $router->POST('get_invoice_details', 'API\PostController@show_invoice_details');
      $router->POST('pay_invoice', 'API\PostController@store');
	  $router->POST('totalInvoice', 'API\ReciveController@totalDyileInvoice');
	  $router->POST('reciveInvoices', 'API\ReciveController@reciveInvoices');
  //  $router->POST('get_paid_invoices', 'API\PostController@show_index');

    $router->POST('cancel_invoice',function(){
        return response()->json('Not Support');
    });

  //  $router->POST('get_invoices', 'API\PostController@showinvoices');

    $router->post('logout', 'AuthController@logout');

   // $router->get('me', 'AuthController@me');

});
});
