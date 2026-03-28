<?php

namespace App\Models;

use App\Services\MediaStorage;
use Illuminate\Database\Eloquent\Model;

class LibraryImage extends Model
{
    protected $fillable = ['path', 'original_name'];

    public function getUrlAttribute(): string
    {
        return app(MediaStorage::class)->url($this->path);
    }
}
