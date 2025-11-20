<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OneDriveFile extends Model
{
    protected $table = 'onedrive_files';
    protected $primaryKey = 'file_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'file_id',
        'drive_file_id',
        'owner_user_object_id',
        'owner_display_name',
        'owner_email',
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
