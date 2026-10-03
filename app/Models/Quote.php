<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime', 'responded_at' => 'datetime', 'work_price' => 'decimal:2', 'delivery_price' => 'decimal:2', 'addons_total' => 'decimal:2', 'total' => 'decimal:2'];

    protected static function booted(): void
    {
        static::creating(function (self $quote) {
            $order = $quote->order()->first();
            abort_unless($order?->requires_quote && $order->status === 'pending_quote', 404);
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function notification()
    {
        return $this->hasOne(QuoteNotification::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return ['draft' => 'Borrador', 'sent' => 'Enviada', 'accepted' => 'Aceptada', 'rejected' => 'Rechazada', 'expired' => 'Vencida'][$this->status] ?? $this->status;
    }
}
