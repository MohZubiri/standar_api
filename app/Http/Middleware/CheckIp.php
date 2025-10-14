<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckIp
{
    public function handle(Request $request, Closure $next): Response
    {
        // TODO: Restrict to allowed IPs if needed. Currently allows all.
        return $next($request);
    }
}


