<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataSourceRef extends Model
{
    // EXPLICITLY specify the table name since it's not plural
    protected $table = 'data_source_ref';

    // Mass-assignment allowed fields
    protected $fillable = [
        'data_source_name',
        'description',
        'storage_type_config',
    ];

    // For Laravel >=5.7, <10 that doesn't auto-cast JSON columns, add:
    protected $casts = [
        'storage_type_config' => 'array',
      	'data_classification' => 'array',
    ];

    // (Optional) If you DO NOT use timestamps, add:
    // public $timestamps = false;
}