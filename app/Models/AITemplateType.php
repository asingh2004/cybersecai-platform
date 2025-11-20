<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AITemplateType extends Model
{
    //use HasFactory;

    // Specify the table name if it differs from the pluralized version of the model name
    protected $table = 'ai_template_types'; // Table name

    // Specify the primary key if it is not 'id'
    protected $primaryKey = 'id'; // Primary key

    // If you do not want the timestamps (created_at, updated_at) to be managed by Eloquent, set this to false
    public $timestamps = true; // true by default; set to false if you don't want timestamps

    // Define the fillable properties for mass assignment
    protected $fillable = [
        'name',
        'description',
        'api_endpoint',
    ];

    // You can define hidden properties if needed (to hide certain attributes from arrays)
    protected $hidden = [
        // Example: 'api_endpoint', // Uncomment to hide api_endpoint when converting to arrays or JSON
    ];

    // Additional methods for your model can also be added here as needed
}