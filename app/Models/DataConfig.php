<?php
// app/Models/DataConfig.php
namespace App\Models;

//use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class DataConfig extends Model
{
    //use HasFactory;
  
  protected $fillable = [
    'user_id', 'data_sources', 'regulations', 'data_classification', 'risk_types', 'api_config', 'pii_volume_thresholds', 'm365_config_json', 'pii_volume_category', 'status'
	];
    protected $casts = [
        'data_sources' => 'array',
        'regulations' => 'array',
        'metadata' => 'array',
        'risk_types' => 'array',
      	'pii_volume_thresholds' => 'array',
      	'm365_config_json' => 'array',
        'api_config' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}