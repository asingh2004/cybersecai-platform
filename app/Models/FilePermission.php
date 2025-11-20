<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilePermission extends Model
{
    protected $table = 'file_permissions';

    protected $fillable = [
        'file_id',
        'role',
        'principal_type',
        'principal_display_name',
        'principal_email',
        'principal_id',
        'provider_permission_id',
        'provider_share_id',
        'source',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }
}