<?php
namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class ComplianceStandard extends Model
{
    protected $table = 'compliance_standards';
  
  
  	protected $fillable = [
        'standard', 'jurisdiction', 'compliance_fields', 'detailed_jurisdiction_notes', 'fields'
      
    ];
    protected $casts = [
       // 'compliance_fields' => 'array',
    ];
}