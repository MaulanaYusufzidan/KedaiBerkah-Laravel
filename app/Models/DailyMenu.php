<?php

namespace App\Models;

use App\Enums\DailyMenuStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyMenu extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'menu_date', 'price', 'stock',
        'available_from', 'available_until', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'menu_date' => 'date',
            'price' => 'integer',
            'stock' => 'integer',
            'status' => DailyMenuStatus::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
