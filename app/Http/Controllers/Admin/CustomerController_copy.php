<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Validator;
use Illuminate\Support\Str;
use App\Http\Helpers\Common;
use App\Http\Controllers\EmailController;
use App\Models\{
    User,
    Roles, // pivot model for roles
    UsersVerification,
    Wallet,
    Properties,
    Settings,
    Accounts,
    Bookings,
    Country,
};
use Session;

class CustomerController extends Controller
{
    protected $helper;
    public function __construct()
    {
        $this->helper = new Common;
    }

    /*public function index()
    {
        $data['customers'] = User::with('roles')->select([
            'id', 'first_name', 'last_name', 'email', 'phone', 'status', 'business_id', 'ABN'
        ])->orderByDesc('created_at')->get();
        return view('admin.customers.view', $data);
    }*/
  
    public function index()
    {
        $bizId = Auth::user()->business_id;

        $query = User::with('roles')
            ->select(['id','first_name','last_name','email','phone','status','business_id','ABN'])
            ->orderByDesc('created_at');

        // Only show users with the same business_id as the current user
        if (is_null($bizId)) {
            $query->whereNull('business_id');
        } else {
            $query->where('business_id', $bizId);
        }

        $data['customers'] = $query->get();

        return view('admin.customers.view', $data);
    }

  	public function sendAdminAddUserWelcomeEmail(\App\Models\User $user)
    {
        $token = app('auth.password.broker')->createToken($user);
        $url = url('password/reset/' . $token . '?email=' . urlencode($user->email));

        \Mail::send('sendmail.admin_add_user_welcome', [
            'user' => $user,
            'url' => $url,
        ], function ($message) use ($user) {
            $message->to($user->email, $user->first_name.' '.$user->last_name)
                    ->subject('Welcome to ' . config('app.name') . '! Set your password to get started');
        });
    }
  


    public function approveUser(Request $request, $id)
    {
        $user = \App\Models\User::findOrFail($id);

        // Approve user
        $user->status = 'Active';
        $user->save();

        // Assign role if set
        if ($request->filled('role_id')) {
            $role = \App\Models\Roles::findOrFail($request->role_id);
            $user->roles()->sync([$role->id]);
            if (strtolower($role->name) === 'admin' && empty($user->business_id)) {
                do {
                    $uniqueBusinessId = random_int(100000, 999999);
                } while (\App\Models\User::where('business_id', $uniqueBusinessId)->exists());
                $user->business_id = $uniqueBusinessId;
                $user->save();
            }
        }

        // Send dynamic welcome email after approval
        $settingsArr = \App\Models\Settings::getAll()->pluck('value', 'name')->toArray();
        \Mail::send('sendmail.user_approved', [
            'settingsArr' => $settingsArr,
            'name' => $user->first_name,
            'email' => $user->email
        ], function($message) use ($user, $settingsArr) {
            $message->to($user->email, $user->first_name)
                ->subject('Your account is approved on ' . ($settingsArr['name'] ?? 'the platform'));
        });

        session()->flash('message', 'User approved and welcome email sent.');
        return back();
    }
  
   /*public function add(Request $request, EmailController $email_controller)
    {
        $roles = Roles::pluck('display_name', 'id');
        if (!$request->isMethod('post')) {
            return view('admin.customers.add', compact('roles'));
        }

        $rules = [
            'first_name' => 'required|max:255',
            'last_name'  => 'required|max:255',
            'email'      => 'required|max:255|email|unique:users',
            'password'   => 'required|min:6',
            'role_id'    => 'required|exists:roles,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) return back()->withErrors($validator)->withInput();

        $role = Roles::findOrFail((int)$request->role_id);

        $user = new User;
        $user->first_name = strip_tags($request->first_name);
        $user->last_name = strip_tags($request->last_name);
        $user->email = $request->email;
        $user->password = \Hash::make($request->password);
        $user->status = "Active";
        $user->phone = $request->phone;
        $user->ABN = $request->ABN;
        $user->email_verified_at = now();

        // Business logic for admin
        if (strtolower($role->name) === 'admin') {
            do {
                $uniqueBusinessId = random_int(100000, 999999);
            } while (User::where('business_id', $uniqueBusinessId)->exists());
            $user->business_id = $uniqueBusinessId;
        } else {
            // For users, admin supplying business_id (can be left null for root/orphan users)
            $user->business_id = $request->business_id;
        }

        $user->save();
        $user->roles()->sync([$role->id]);

        // Standard welcome & extras
        $this->wallet($user->id);
        $this->sendAdminAddUserWelcomeEmail($user);
        $this->helper->one_time_message('success', 'Added Successfully, and new customer notified.');
        return redirect('admin/customers');
    }*/
  
      public function add(Request $request, EmailController $email_controller)
    {
    $roles = Roles::pluck('display_name', 'id');

    if (!$request->isMethod('post')) {
        return view('admin.customers.add', compact('roles'));
    }

    $rules = [
        'first_name' => 'required|max:255',
        'last_name'  => 'required|max:255',
        'email'      => 'required|max:255|email|unique:users',
        'password'   => 'required|min:6',
        'role_id'    => 'required|exists:roles,id',
    ];
    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) return back()->withErrors($validator)->withInput();

    $role = Roles::findOrFail((int)$request->role_id);

    $user = new User;
    $user->first_name = strip_tags($request->first_name);
    $user->last_name = strip_tags($request->last_name);
    $user->email = $request->email;
    $user->password = \Hash::make($request->password);
    $user->status = "Active";
    $user->phone = $request->phone;
    $user->ABN = $request->ABN;
    $user->email_verified_at = now();

    // Always inherit the creator's business_id (irrespective of role)
    $user->business_id = Auth::user()->business_id; // can be null

    $user->save();
    $user->roles()->sync([$role->id]);

    $this->wallet($user->id);
    $this->sendAdminAddUserWelcomeEmail($user);
    $this->helper->one_time_message('success', 'Added Successfully, and new customer notified.');
    return redirect('admin/customers');
    }

    public function update(Request $request)
    {
        $userId = $request->id ?? $request->customer_id;
        $user = User::with('roles')->findOrFail($userId);
        $roles = Roles::pluck('display_name', 'id');
        if (!$request->isMethod('post')) {
            return view('admin.customers.edit', compact('user', 'roles'));
        }
        $rules = [
            'first_name' => 'required|max:255',
            'last_name'  => 'required|max:255',
            'email'      => 'required|max:255|email|unique:users,email,'.$user->id,
            'role_id'    => 'required|exists:roles,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        $role = Roles::findOrFail((int)$request->role_id);

        $user->first_name = strip_tags($request->first_name);
        $user->last_name = strip_tags($request->last_name);
        $user->email = $request->email;
        $user->status = $request->status;
        $user->ABN = $request->ABN;
        $user->phone = $request->phone;

        if ($request->filled('password')) $user->password = bcrypt($request->password);

        // Change business_id for admins only
        if (strtolower($role->name) === 'admin' && empty($user->business_id)) {
            do {
                $uniqueBusinessId = random_int(100000, 999999);
            } while (User::where('business_id', $uniqueBusinessId)->exists());
            $user->business_id = $uniqueBusinessId;
        }
        // If changing to 'user', **retain business_id**—do nothing.

        $user->save();
        $user->roles()->sync([$role->id]);
        $this->helper->one_time_message('success', 'Updated Successfully');
        return redirect('admin/customers');
    }

    public function delete(Request $request)
    {
        $user = User::find($request->id);
        if ($user) $user->delete();
        $this->helper->one_time_message('success', 'Deleted Successfully');
        return redirect('admin/customers');
    }

    public function wallet($userId)
    {
        $wallet = new Wallet();
        $wallet->user_id = $userId;
        $wallet->currency_id = (int)Settings::where('name', 'default_currency')->first()->value;
        $wallet->save();
    }
}