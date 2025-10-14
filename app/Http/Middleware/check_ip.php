<?php

namespace App\Http\Middleware;

use Closure;
use DB;
use Config;
use Illuminate\Http\Request as Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;


class check_ip extends Controller
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        $mail_ip = '';
        if (auth()->user()->bank_id == 1)
		{   
			Log::info("API-TEST-DATABASE" . "  Mailbanck Ip (" . Request()->ip() . ")");
            $mail_ip = ['82.114.179.226'];
		}
		 if (auth()->user()->bank_id == 6)
		{   
			Log::info("API-TEST-DATABASE" . "  Mailbanck Ip (" . Request()->ip() . ")");
            $mail_ip = ['195.94.15.96','82.114.179.226'];
			
		}
        else if (auth()->user()->bank_id == 2) {
            Log::info("API-TEST-DATABASE" . "  cackBank Ip (" . Request()->ip() . ")");
			             $mail_ip=['82.114.168.62','195.94.12.62','195.94.12.61','82.114.168.61'];
        }
		 else if (auth()->user()->bank_id == 3) {
            Log::info("API-TEST-DATABASE" . "  cacher Ip (" . Request()->ip() . ")");
			             $mail_ip=['159.89.9.158'];
        }
        $ip = Request()->ip();

        if (true) {
       // if (in_array($ip, $mail_ip)) {
            Log::info("API-IP-IN -ARRAY" . "  Bank (".auth()->user()->bank_id.") Ip (" . Request()->ip() . ")");
            return $next($request);
        } else {
            $response = ['success' => false];

            $response['data'] = 'Use an Auth Address';
            $response['message'] = '440';

            return response()->json($response, '404');
        }
    }
}
