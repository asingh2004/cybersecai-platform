<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Validator;
use App\Http\Helpers\Common;
use App\Http\Controllers\EmailController;
use App\Models\User;
use App\Models\Roles;
use App\Models\Wallet;
use App\Models\Settings;

class CustomerController extends Controller
{
    protected $helper;

    public function __construct()
    {
        $this->helper = new Common;
    }

    // List: super admin sees all; others see only their business users (business_id from users table by matching admin email)
    public function index()
    {
        $ctx = $this->getAdminContext();

        $query = User::with('roles')
            ->select(['id', 'first_name', 'last_name', 'email', 'phone', 'status', 'business_id', 'ABN'])
            ->orderByDesc('created_at');

        if (!$ctx['isSuperAdmin']) {
            if (is_null($ctx['businessId'])) {
                $query->whereNull('business_id');
            } else {
                $query->where('business_id', $ctx['businessId']);
            }
        }

        $data['customers'] = $query->get();

        return view('admin.customers.view', $data);
    }

    /*public function sendAdminAddUserWelcomeEmail(User $user)
    {
        $token = app('auth.password.broker')->createToken($user);
        $url = url('password/reset/' . $token . '?email=' . urlencode($user->email));

        \Mail::send('sendmail.admin_add_user_welcome', [
            'user' => $user,
            'url' => $url,
        ], function ($message) use ($user) {
            $message->to($user->email, $user->first_name . ' ' . $user->last_name)
                ->subject('Welcome to ' . config('app.name') . '! Set your password to get started');
        });
    }*/
  
  public function sendAdminAddUserWelcomeEmail(\App\Models\User $user)
{
    $token = app('auth.password.broker')->createToken($user);
    $settingsArr = \App\Models\Settings::getAll()->pluck('value', 'name')->toArray();

    \Mail::send('sendmail.admin_add_user_welcome', [
        'settingsArr' => $settingsArr,
        'name'        => $user->first_name,
        'email'       => $user->email,
        'token'       => $token,
        // optional: you can also pass 'user' if you want the Blade to use it
        'user'        => $user,
    ], function ($message) use ($user) {
        $message->to($user->email, $user->first_name . ' ' . $user->last_name)
                ->subject('Welcome to ' . (config('app.name') ?? 'our platform') . '! Set your password to get started');
    });
}

    // Approve user; super admin can approve any; others restricted to their business_id.
    public function approveUser(Request $request, $id)
    {
        $ctx = $this->getAdminContext();

        $user = User::findOrFail($id);

        // Authorization: non-super admins can only approve within their business
        if (!$ctx['isSuperAdmin']) {
            $allowedBiz = $ctx['businessId'];
            if (!($user->business_id === $allowedBiz || (is_null($user->business_id) && is_null($allowedBiz)))) {
                abort(403, 'You are not authorized to approve this user.');
            }
        }

        // Approve user
        $user->status = 'Active';

        // Assign role if set
        if ($request->filled('role_id')) {
            $role = Roles::findOrFail($request->role_id);
            $user->roles()->sync([$role->id]);
        }

        // Business assignment if missing
        if (empty($user->business_id)) {
            if ($ctx['isSuperAdmin']) {
                $user->business_id = $this->generateUniqueBusinessId();
            } else {
                $user->business_id = $ctx['businessId']; // can be null
            }
        }

        $user->save();

        // Send dynamic welcome email after approval
        $settingsArr = Settings::getAll()->pluck('value', 'name')->toArray();
        \Mail::send('sendmail.user_approved', [
            'settingsArr' => $settingsArr,
            'name' => $user->first_name,
            'email' => $user->email
        ], function ($message) use ($user, $settingsArr) {
            $message->to($user->email, $user->first_name)
                ->subject('Your account is approved on ' . ($settingsArr['name'] ?? 'the platform'));
        });

        session()->flash('message', 'User approved and welcome email sent.');
        return back();
    }

    // Add user:
    // - Super admin: can add any user; business_id is auto-generated.
    // - Other admins: user inherits business_id from the admin's linked user record (by email).
    public function add(Request $request, EmailController $email_controller)
    {
        $ctx = $this->getAdminContext();

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

        // Business assignment
        if ($ctx['isSuperAdmin']) {
            $user->business_id = $this->generateUniqueBusinessId();
        } else {
            $user->business_id = $ctx['businessId']; // can be null
        }

        $user->save();
        $user->roles()->sync([$role->id]);

        $this->wallet($user->id);
        $this->sendAdminAddUserWelcomeEmail($user);
        $this->helper->one_time_message('success', 'Added Successfully, and new customer notified.');
        return redirect('admin/customers');
    }

    // Update user:
    // - Super admin can edit any user.
    // - Other admins can edit users only within their business_id.
    // - We do not change business_id here automatically.
    public function update(Request $request)
    {
        $ctx = $this->getAdminContext();

        $userId = $request->id ?? $request->customer_id;
        $user = User::with('roles')->findOrFail($userId);

        if (!$ctx['isSuperAdmin']) {
            $allowedBiz = $ctx['businessId'];
            if (!($user->business_id === $allowedBiz || (is_null($user->business_id) && is_null($allowedBiz)))) {
                abort(403, 'You are not authorized to edit this user.');
            }
        }

        $roles = Roles::pluck('display_name', 'id');
        if (!$request->isMethod('post')) {
            return view('admin.customers.edit', compact('user', 'roles'));
        }

        $rules = [
            'first_name' => 'required|max:255',
            'last_name'  => 'required|max:255',
            'email'      => 'required|max:255|email|unique:users,email,' . $user->id,
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

        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
        }

        // Do not change business_id here automatically
        $user->save();

        $user->roles()->sync([$role->id]);

        $this->helper->one_time_message('success', 'Updated Successfully');
        return redirect('admin/customers');
    }

    // Delete user:
    // - Super admin can delete any user.
    // - Other admins only within their business scope.
    public function delete(Request $request)
    {
        $ctx = $this->getAdminContext();

        $user = User::findOrFail($request->id);

        if (!$ctx['isSuperAdmin']) {
            $allowedBiz = $ctx['businessId'];
            if (!($user->business_id === $allowedBiz || (is_null($user->business_id) && is_null($allowedBiz)))) {
                abort(403, 'You are not authorized to delete this user.');
            }
        }

        $user->delete();
        $this->helper->one_time_message('success', 'Deleted Successfully');
        return redirect('admin/customers');
    }

    public function wallet($userId)
    {
        $wallet = new Wallet();
        $wallet->user_id = $userId;

        $defaultCurrency = Settings::where('name', 'default_currency')->first();
        $wallet->currency_id = (int) optional($defaultCurrency)->value ?: 1;

        $wallet->save();
    }

    // Helpers

    protected function generateUniqueBusinessId(): int
    {
        do {
            $uniqueBusinessId = random_int(100000, 999999);
        } while (User::where('business_id', $uniqueBusinessId)->exists());

        return $uniqueBusinessId;
    }

    // Build admin context from 'admin' table (auth guard 'admin') and role_admin mapping
    protected function getAdminContext(): array
    {
        $adminUser = Auth::guard('admin')->user();

        $adminId = $adminUser->id ?? null;
        $adminEmail = $adminUser->email ?? null;

        // Determine if super admin via role_admin.role_id = 3
        $isSuperAdmin = false;
        if ($adminId) {
            $isSuperAdmin = DB::table('role_admin')
                ->where('admin_id', $adminId)
                ->where('role_id', 3)
                ->exists();
        }

        // Get business_id from users table by matching admin email
        $businessId = null;
        if ($adminEmail) {
            $linked = User::where('email', $adminEmail)->select('business_id')->first();
            $businessId = $linked->business_id ?? null;
        }

        return [
            'adminId'     => $adminId,
            'adminEmail'  => $adminEmail,
            'isSuperAdmin'=> $isSuperAdmin,
            'businessId'  => $businessId,
        ];
    }
}