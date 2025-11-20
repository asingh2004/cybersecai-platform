<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FileAIAnalysis extends Model
{
    protected $table = 'file_ai_analyses';

    protected $fillable = [
        'file_id',
        'auditor_agent_view',
        'likely_data_subject_area',
        'data_classification',
        'overall_risk_rating',
        'hacker_interest',
        'auditor_proposed_action',
        'raw_json',
    ];

    protected $casts = [
        'raw_json' => 'array',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(FileAIFinding::class, 'analysis_id');
    }

    public function controls(): HasMany
    {
        return $this->hasMany(FileAIControl::class, 'analysis_id');
    }
}