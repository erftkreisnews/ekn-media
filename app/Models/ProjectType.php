<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectType extends Model
{
    protected $fillable = [
        'key',
        'name',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
