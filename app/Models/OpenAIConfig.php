<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenAIConfig extends Model
{
    // Specify the table name
    protected $table = 'open_ai_config'; // Table name

    // Specify the primary key if it is not 'id'
    protected $primaryKey = 'id'; // Primary key

    // If you do not want the timestamps (created_at, updated_at) to be managed by Eloquent, set this to false
    public $timestamps = true; // true by default; set to false if you don't want timestamps

    // Define the fillable properties for mass assignment
    protected $fillable = [
        'api_key',
        'ai_model_id',
        'ai_template_type_id',
        'prompt',
        'user_id',
        'assistant_id',
        'company_id',
        'name',
        'instructions',
        'status',
        // 'persona_id',  // Removed since there is no longer a relation
    ];

    // Optionally, you can define hidden properties if needed (to hide certain attributes from arrays)
    protected $hidden = [
        // 'api_key', // Uncomment to hide api_key when converting to arrays or JSON
    ];

    // Define relationships
    public function aiTemplateType()
    {
        return $this->belongsTo(AITemplateType::class, 'ai_template_type_id');
    }

    public function aiModel()
    {
        return $this->belongsTo(AIModel::class, 'ai_model_id');
    }

    // Add relationship with User model
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id'); // Assuming the User model exists in App\Models
    }

    // Removed the relationship with Persona model since it no longer exists
    // public function persona()  // This method can be deleted
    // {
    //     return $this->belongsTo(Persona::class, 'persona_id'); // Removed due to no relation
    // }

    // Additional methods for your model can also be added here (if needed)
}