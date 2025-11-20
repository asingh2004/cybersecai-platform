<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetadataKey extends Model
{
    protected $table = 'metadata_keys';
    protected $fillable = ['key', 'description'];

    public function fileMetadata()
    {
        return $this->hasMany(FileMetadata::class, 'metadata_key_id');
    }
}