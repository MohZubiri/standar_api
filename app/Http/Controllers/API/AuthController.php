<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    public function api_signup(Request $request)
    {
        return response()->json(['status' => 'success']);
    }
}


