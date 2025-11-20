<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileAIControl extends Model
{
    protected $table = 'file_ai_controls';

    protected $fillable = [
        'analysis_id',
        'control_text',
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(FileAIAnalysis::class, 'analysis_id');
    }
}