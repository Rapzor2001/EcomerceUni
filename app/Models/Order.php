<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['public_id', 'user_id', 'status', 'payment_status', 'fulfillment_status', 'payment_provider', 'provider_transaction_id', 'provider_reference', 'subtotal', 'shipping_amount', 'total', 'shipping_address', 'shipping_carrier', 'tracking_number', 'provider_payload', 'paid_at', 'shipped_at', 'delivered_at'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'shipping_amount' => 'decimal:2', 'total' => 'decimal:2', 'shipping_address' => 'array', 'provider_payload' => 'array', 'paid_at' => 'datetime', 'shipped_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
