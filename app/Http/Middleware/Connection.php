<?php

namespace App\Http\Middleware;

use Closure;
use DB;
use Illuminate\Support\Facades\Config;
use  \Illuminate\Http\Request ;

class Connection
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
      
     

                 DB::purge('mysql2');
                 config(['database.connections.mysql2.host' => $request->session()->get('Host')]);
                 config(['database.connections.mysql2.port' => '3306']);
                 config(['database.connections.mysql2.database' => $request->session()->get('UNIV_DB_NAME')]);
                 config(['database.connections.mysql2.username' => $request->session()->get('UNIV_PORTAL_USER_NAME')]);
                 config(['database.connections.mysql2.password' => $request->session()->get('UNIV_PORTAL_USER_PASS')]);
      
        DB::reconnect('mysql2');
		
	
        return $next($request);
    }



}
