<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsSlugRedirect extends Model
{
    protected $table = 'news_slug_redirects';

    protected $fillable = [
        'from_slug',
        'to_slug',
        'is_gone',
    ];

    protected $casts = [
        'is_gone' => 'boolean',
    ];
}
