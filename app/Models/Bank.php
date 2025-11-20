<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Bank extends Model
{
    protected $fillable = [
        'id',
        'account_name',
        'iban',
        'swift_code',
        'bank_name',
        'branch_name',
        'branch_city',
        'routing_no',
        'branch_address',
        'country',
        'status',
        'logo',
        'description'
    ];

    public static function getAll()
    {
        $data = Cache::get(config('cache.prefix') . '.banks');
        if (empty($data)) {
            $data = parent::all();
            Cache::forever(config('cache.prefix') . '.banks', $data);
        }
        return $data;
    }

    public function getLogoAttribute() {
        $logo = $this->attributes['logo'];
        if($logo) {
            return url('/public/images/bank/'. $this->attributes['logo']);
        }
        return url('/public/images/default-bank.jpg');
    }

    public function getLogoNameAttribute() {
        return $this->attributes['logo'];
    }
}
