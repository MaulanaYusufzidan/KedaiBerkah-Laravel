<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use App\Support\ImageStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, HasUniqueSlug;

    protected $fillable = ['category_id', 'name', 'slug', 'description', 'image', 'base_price', 'is_active'];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function dailyMenus(): HasMany
    {
        return $this->hasMany(DailyMenu::class);
    }

    /** URL gambar produk, atau placeholder lokal jika belum ada gambar. */
    public function imageUrl(): string
    {
        return ImageStorage::url($this->image) ?? asset('images/placeholder-produk.svg');
    }
}
