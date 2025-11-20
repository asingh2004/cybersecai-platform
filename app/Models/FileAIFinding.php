<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FileAIFinding extends Model
{
    protected $table = 'file_ai_findings';

    protected $fillable = [
        'analysis_id',
        'standard',
        'jurisdiction',
        'risk',
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(FileAIAnalysis::class, 'analysis_id');
    }

    public function detectedFields(): HasMany
    {
        return $this->hasMany(FileAIFindingDetectedField::class, 'finding_id');
    }
}