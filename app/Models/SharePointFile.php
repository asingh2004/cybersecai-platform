<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharePointFile extends Model
{
    protected $table = 'sharepoint_files';
    protected $primaryKey = 'file_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'file_id',
        'site_id',
        'drive_id',
        'drive_file_id',
        'parent_reference',
        'web_url',
        'download_url',
        'permissions_json',
    ];

    protected $casts = [
        'permissions_json' => 'array',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }
}