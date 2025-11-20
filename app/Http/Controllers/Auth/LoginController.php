<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    use AuthenticatesUsers;
    protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);
    }
  
  	protected function authenticated(Request $request, $user)
{
    if ($user->status !== 'Active' || is_null($user->email_verified_at)) {
        Auth::logout();
        return redirect('/login')->with('message', 'Your account is pending admin approval or your email is not verified.')->with('alert-class', 'alert-danger');
    }
    // Else normal
}
}


