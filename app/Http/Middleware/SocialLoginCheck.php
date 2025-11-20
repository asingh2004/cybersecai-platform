<?php

namespace App\Http\Middleware;

use App\Http\Helpers\SocialLogin;
use Closure;
use Illuminate\Support\Facades\Redirect;

class SocialLoginCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $social)
    {
        if(SocialLogin::checkSocial($social)) {
            return $next($request);
        }
        return Redirect::to('login');
    }
}
