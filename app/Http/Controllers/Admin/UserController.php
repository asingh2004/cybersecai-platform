<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    // List users (within admin's business)
    public function index()
    {
        $admin = auth('admin')->user();
        $users = User::where('business_id', $admin->business_id)->get();
        return view('admin.users.index', compact('users'));
    }

	public function approve($id)
	{
      $user = \App\Models\User::findOrFail($id);
      $user->status = 'Active';
      $user->save();

      // Send the approval email
      try {
          \Mail::send('sendmail.user_approved', [
              'name' => $user->first_name . ' ' . $user->last_name,
              // Add more vars if desired
          ], function ($message) use ($user) {
              $message->to($user->email, $user->first_name . ' ' . $user->last_name)
                  ->subject('Your account has been approved!');
          });
      } catch (\Throwable $e) {
          \Log::error('Failed to send user approval email: ' . $e->getMessage());
          // Optional: Show a visible warning to admin
          return redirect()->back()->with('warning', 'User approved but failed to send confirmation email.');
      }

      return redirect()->back()->with('success', 'User approved successfully and confirmation email sent!');
     }

    // Add user form
    public function create()
    {
        return view('admin.users.create');
    }

    // Store new user
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|max:191',
            'last_name'  => 'required|max:191',
            'email'      => 'required|email|max:191|unique:users,email',
            'password'   => 'required|min:6|confirmed',
        ]);

        $admin = auth('admin')->user();

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'business_id'=> $admin->business_id,
            'status'     => 'Active', // Instantly active!
        ]);

        // Assign default user role
        $userRoleId = \App\Models\Role::where('name', 'user')->first()->id ?? 2;
        $user->roles()->attach($userRoleId);

        // Optionally: send welcome email
        // Mail::to($user->email)->send(new \App\Mail\UserCreatedByAdmin($user));
        
        return redirect()->route('admin.users')->with('message', 'User successfully added!')->with('alert-class', 'alert-success');
    }
}