<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use Illuminate\Http\Request;
use Tymon\JWTAuth\JWTAuth;
use App\Models\User;
use DB;
use App\Models\Main;
class AuthController extends Controller
{
    /**
     * Function to refresh
     */
    public function refresh(){

        return $this->respondWithToken( auth()->refresh(true, true));
    }
      /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        
        return response()->json(auth()->user());
    }


    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }
}