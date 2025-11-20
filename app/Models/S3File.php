<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class S3File extends Model
{
    protected $table = 's3_files';
    protected $primaryKey = 'file_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'file_id',
        'bucket',
        's3_key',
        'full_path',
        'last_modified',
    ];

    protected $casts = [
        'last_modified' => 'datetime',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }
}