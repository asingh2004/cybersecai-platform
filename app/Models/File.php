<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class File extends Model
{
    use SoftDeletes;

    protected $table = 'files';

    protected $fillable = [
        'user_id',
        'business_id',
        'storage_type',
        'source_file_id',
        'file_name',
        'file_type',
        'file_extension',
        'size_bytes',
        'last_modified',
        'full_path',
        'web_url',
        'download_url',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'last_modified' => 'datetime',
        // 'deleted_at' => 'datetime', // optional; SoftDeletes handles this automatically
    ];

    public function s3(): HasOne
    {
        return $this->hasOne(S3File::class, 'file_id');
    }

    public function smb(): HasOne
    {
        return $this->hasOne(SMBFile::class, 'file_id');
    }

    public function onedrive(): HasOne
    {
        return $this->hasOne(OneDriveFile::class, 'file_id');
    }

    public function sharepoint(): HasOne
    {
        return $this->hasOne(SharePointFile::class, 'file_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(FilePermission::class, 'file_id');
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(FileAIAnalysis::class, 'file_id');
    }

    public function latestAiAnalysis(): HasOne
    {
        return $this->hasOne(FileAIAnalysis::class, 'file_id')->latestOfMany();
    }
}