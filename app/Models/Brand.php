<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = [
        'key',
        'name',
        'primary_host',
        'secondary_hosts',
        'is_active',
    ];

    protected $casts = [
        'secondary_hosts' => 'array',
        'is_active' => 'boolean',
    ];

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
