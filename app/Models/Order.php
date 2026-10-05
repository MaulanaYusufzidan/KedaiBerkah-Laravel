<?php

namespace App\Models;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    // Hanya data isian customer yang boleh di-mass-assign. Kode pesanan, token,
    // subtotal, ongkir, total, dan status ditentukan server (pakai forceFill di service).
    protected $fillable = [
        'customer_name', 'customer_phone', 'fulfillment_type',
        'address', 'delivery_area_id', 'notes',
    ];

    protected $hidden = ['tracking_token'];

    protected function casts(): array
    {
        return [
            'fulfillment_type' => FulfillmentType::class,
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function deliveryArea(): BelongsTo
    {
        return $this->belongsTo(DeliveryArea::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Pesanan yang dihitung sebagai omzet terealisasi: sudah ada pembayaran berstatus
     * "paid" dan pesanan tidak dibatalkan. Memakai EXISTS (bukan JOIN) agar total
     * pesanan tidak terhitung ganda ketika pembayaran/item lebih dari satu.
     */
    public function scopeRealized(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereHas('payments', fn (Builder $q) => $q->where('status', PaymentStatus::Paid->value));
    }

    /** Nama area saat pesanan dibuat (snapshot); pesanan lama jatuh ke nama area saat ini. */
    public function areaName(): ?string
    {
        return $this->delivery_area_name ?? $this->deliveryArea?->district;
    }

    public function isPickup(): bool
    {
        return $this->fulfillment_type === FulfillmentType::Pickup;
    }
}
