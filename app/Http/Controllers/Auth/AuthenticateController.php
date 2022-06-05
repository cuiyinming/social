<?php

namespace App\Http\Controllers\Auth;

use Tymon\JWTAuth\Exceptions\JWTException;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use JWTAuth;

class AuthenticateController extends Controller
{

    public function refreshToken()
    {
        try {
            $old_token = JWTAuth::getToken();
            $token = JWTAuth::refresh($old_token);
            // JWTAuth::invalidate($old_token);
            return $this->jsonExit(200, 'OK', compact('token'));
        } catch (TokenExpiredException $e) {
            return $this->jsonExit(201, $e->getMessage());
        } catch (JWTException $e) {
            return $this->jsonExit(202, $e->getMessage());
        }
    }

}
