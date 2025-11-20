<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AgenticAiLog extends Model
{
    protected $guarded = [];
    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
    ];
}