<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryArea extends Model
{
    use HasFactory;

    protected $fillable = ['district', 'delivery_fee', 'is_active'];

    protected function casts(): array
    {
        return [
            'delivery_fee' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Hanya area aktif yang boleh dipilih untuk pesanan baru. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Untuk dipakai di dalam transaksi pembuatan pesanan: mengambil area aktif dan mengunci barisnya,
     * sehingga penghapusan/penonaktifan bersamaan menunggu transaksi pesanan selesai.
     * Area nonaktif atau tidak ada -> ModelNotFoundException.
     */
    public static function lockActive(int $id): self
    {
        return static::query()->active()->whereKey($id)->lockForUpdate()->firstOrFail();
    }
}
