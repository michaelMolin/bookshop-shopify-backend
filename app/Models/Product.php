<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;


#[Translatable ('name','slug','book_data')]
class Product extends Model
{
    use HasTranslations;

    protected $guarded = ['id'];
    protected $casts = [
        'tags' => 'array',
        'data' => 'array',
        'price' => 'decimal:2',
        'inventory_quantity' => 'integer',
        'synced_at' => 'datetime',
    ];

    // RELATIONSHIP

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    // SCOPES

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ACCESSOR

    protected function availability(): Attribute
    {
        return Attribute::make(
            get: fn () => match (true) {
                $this->inventory_quantity <= 0 => 'orderable',
                $this->inventory_quantity <= 3 => 'low_stock',
                default => 'available',
            }
        );
    }
}
