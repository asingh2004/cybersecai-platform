<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SMBFile extends Model
{
    protected $table = 'smb_files';
    protected $primaryKey = 'file_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'file_id',
        'server',
        'share',
        'file_path',
        'full_path',
        'created',
        'last_modified',
        'acls',
    ];

    protected $casts = [
        'created' => 'datetime',
        'last_modified' => 'datetime',
        'acls' => 'array',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }
}