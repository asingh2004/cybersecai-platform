<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favourite extends Model
{
    public $timestamps = false;
    protected $fillable = ['property_id', 'user_id', 'status'];

    public function properties()
    {
        return $this->belongsTo(Properties::class, 'property_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
