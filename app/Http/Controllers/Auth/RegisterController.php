<?php

namespace App\Http\Controllers\Auth;

/*use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;*/
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class RegisterController extends Controller
{

  	public function showRegistrationForm()
    {
        return view('auth.signup');
    }

  public function register(Request $request)
{
    // validate + user creation omitted for brevity...
     \Log::info('[DEBUG] RegisterController@register called for ' . $request->email);

    $user = User::create([
        'first_name' => $request->first_name,
        'last_name'  => $request->last_name,
        'email'      => $request->email,
        'password'   => Hash::make($request->password),
        'status'     => 'Inactive',
    ]);

    $role = Role::where('name', 'user')->first();
    if ($role) {
        $user->roles()->attach($role->id);
    }

    // Send verification email to the user (always!)
    try {
        app(\App\Http\Controllers\EmailController::class)->welcome_email($user);
    } catch (\Throwable $e) {
        \Log::error('Verification email failed: ' . $e->getMessage());
    }

    // Send admin notification
    try {
        app(\App\Http\Controllers\EmailController::class)->saas_admin_new_user($user);
    } catch (\Throwable $e) {
        \Log::error('SaaS admin approval email failed: ' . $e->getMessage());
    }

    return redirect('/login')->with([
        'message' => 'Registration submitted. Please check your email for a verification link, and await admin approval.',
        'alert-class' => 'alert-info',
    ]);
}
  
    /*protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware('guest');
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);
    }

    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
    }*/
  
  
  
}
