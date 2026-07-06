<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

#[Translatable ('title','slug','excerpt','body')]
class Article extends Model
{
    use HasTranslations;

    protected $guarded = ['id'];
    protected $casts = [];


    // SCOPES
    public function scopeActive($query)
    {
        return $query->where('status', 'published');
    }

}
