<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileAIFindingDetectedField extends Model
{
    protected $table = 'file_ai_finding_detected_fields';

    protected $fillable = [
        'finding_id',
        'field_name',
    ];

    public function finding(): BelongsTo
    {
        return $this->belongsTo(FileAIFinding::class, 'finding_id');
    }
}