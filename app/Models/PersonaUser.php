<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PersonaUser extends Pivot
{
    // Specify the table name (optional if follows Laravel conventions)
    protected $table = 'persona_user';

    // If timestamps are not being used, you would set this to false
    public $timestamps = false;

    // Specify the primary key if it's not the default (optional)
    protected $primaryKey = ['user_id', 'persona_id'];

    // Specify the attributes that are mass assignable (if needed)
    protected $fillable = [
        'user_id',
        'persona_id',
    ];

    // Optionally, define relationship methods here if needed
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }
}