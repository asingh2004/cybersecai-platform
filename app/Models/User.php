<?php

/**
 * User Model
 *
 * User Model manages User operation.
 *
 * @category   User
 * @package    OzzieAccom
 * @author     Telkin Corp
 * @copyright  Telkin
 * @license
 * @version    2.7
 * @link       Ozzieaccom.com
 * @since      Version 1.3
 * @deprecated None
 */

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Http\Helpers\Common;
use App\Models\UserDetails;
use App\Models\Roles;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'subscription_status', 'business_id', 'status', 'alternate_phone_number', 'alternate_email',

    ];

    protected $hidden = [
        'password', 'remember_token', 'sub_activation_date', 'sub_renewal_date', 'subscription_paid', 'sub_payment_method', 'point_of_contact', 'alternate_phone_number', 'alternate_email', 'balance', 'referral_code', 'created_at', 'updated_at', 
    ];

    protected $appends = ['profile_src'];

    public function users_verification()
    {
        return $this->hasOne('App\Models\UsersVerification', 'user_id', 'id');
    }

  	// Relationship to roles (if using pivot table `role_user`)
    public function roles()
    {
        return $this->belongsToMany(Roles::class, 'role_user', 'user_id', 'role_id');
    }
  


    public function isApproved()
    {
        return $this->status === 'Active';
    }
  
  	public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->timestamp('email_verified_at')->nullable();
    });
}
  
    public function payouts()
    {
        return $this->hasMany('App\Models\Payouts', 'user_id', 'id');
    }

    public function accounts()
    {
        return $this->hasMany('App\Models\Account', 'user_id', 'id');
    }

    public function bookings()
    {
        return $this->hasMany('App\Models\Bookings', 'user_id', 'id');
    }

    public function notifications()
    {
        return $this->hasMany('App\Models\Notification', 'user_id', 'id');
    }

    public function reports()
    {
        return $this->hasMany('App\Models\Report', 'user_id', 'id');
    }


    public function user_details()
    {
        return $this->hasMany('App\Models\UserDetail', 'user_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany('App\Models\Payment', 'user_id', 'id');
    }

    public function withdraw()
    {
        return $this->hasMany('App\Models\Withdraw', 'user_id', 'id');
    }

    public function properties()
    {
        return $this->hasMany('App\Models\Properties', 'host_id', 'id');
    }

    public function reviews()
    {
        return $this->hasMany('App\Models\Reviews', 'sender_id', 'id');
    }

    public function getProfileSrcAttribute()
    {
        if ($this->attributes['profile_image'] == '') {
            $src = url('public/images/default-profile.png');
        } else {
            $src = url('public/images/profile/'.$this->attributes['id'].'/'.$this->attributes['profile_image']);
        }

        return $src;
    }
  
  	public function personas()
    {
        return $this->belongsToMany(Persona::class, 'persona_user', 'user_id', 'persona_id');
    }


    public function details_key_value()
    {
        $details = UserDetails::where('user_id', $this->attributes['id'])->pluck('value', 'field');
        return $details;
    }

    public function getAccountSinceAttribute()
    {
        $since = date('F Y', strtotime($this->attributes['created_at']));
        return $since;
    }

    public function getFullNameAttribute()
    {
        $full_name = ucfirst($this->attributes['first_name']).' '.ucfirst($this->attributes['last_name']);
        return $full_name;
    }
  

	public function dataConfigs()
	{
    	return $this->hasMany(DataConfig::class);
	}
}
