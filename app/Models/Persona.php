<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    use HasFactory;

    // The table associated with the model
    protected $table = 'personas';

    // The attributes that are mass assignable
    protected $fillable = ['value', 'description'];

    // This will prevent Laravel from expecting timestamp columns
    public $timestamps = false;

    // Specify the primary key if it's not 'id'
    protected $primaryKey = 'id';

    // Define the relationship with the User model
    public function users()
    {
        return $this->belongsToMany(User::class, 'persona_user', 'persona_id', 'user_id');
    }
}