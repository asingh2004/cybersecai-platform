<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceEvent extends Model
{
    // Table name (optional if table name matches the plural snake_case of the model)
    protected $table = 'compliance_events';

    // The attributes that are mass assignable.
    protected $fillable = [
        'user_id',
        'company_id',
        'event_type',
        'standard_id',
        'data',
        'risk',
        'ai_decision_details',
        'notification_letter',
        'status'
    ];

    // The attributes that should be cast.
    protected $casts = [
        'data' => 'array',
        'risk' => 'array',
        'ai_decision_details' => 'array',
        'notification_letter' => 'string', // adjust if it's JSON or some other type
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // (Optional) Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
  
  	public function standard()
{
    return $this->belongsTo(ComplianceStandard::class, 'standard_id');
}
    
    // Add more relationships or helper functions as needed
}