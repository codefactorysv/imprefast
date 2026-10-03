<?php

namespace App\Models;

use App\Services\OrderStatusService;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'code',
        'service_id',
        'customer_name',
        'phone',
        'email',
        'status',
        'payment_method',
        'payment_status',
        'pages',
        'copies',
        'paper_size',
        'double_sided',
        'orientation',
        'cover',
        'stapled',
        'details',
        'instructions',
        'requested_at',
        'urgent',
        'work_subtotal',
        'delivery_fee',
        'total',
        'verified_paid',
        'department',
        'municipality',
        'neighborhood',
        'street',
        'house_number',
        'address_reference',
        'latitude',
        'longitude',
        'distance_km',
        'duration_minutes',
        'route_provider',
        'route_calculated_at',
        'base_fee_snapshot',
        'price_per_km_snapshot',
        'minimum_fee_snapshot',
        'pickup_factor_snapshot',
        'urgent_surcharge_snapshot',
        'public_token',
        'internal_notes',
        'requires_quote', 'requires_deposit', 'service_configuration_snapshot', 'topic', 'education_level', 'output_format', 'images_required',
        'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'route_calculated_at' => 'datetime',
        'urgent' => 'boolean',
        'double_sided' => 'boolean',
        'cover' => 'boolean',
        'stapled' => 'boolean',
        'work_subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'verified_paid' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'base_fee_snapshot' => 'decimal:2',
        'price_per_km_snapshot' => 'decimal:2',
        'minimum_fee_snapshot' => 'decimal:2',
        'pickup_factor_snapshot' => 'decimal:2',
        'urgent_surcharge_snapshot' => 'decimal:2',
        'requires_quote' => 'boolean', 'requires_deposit' => 'boolean', 'service_configuration_snapshot' => 'array', 'images_required' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function files()
    {
        return $this->hasMany(OrderFile::class);
    }

    public function imageComposition()
    {
        return $this->hasOne(OrderImageComposition::class);
    }

    public function histories()
    {
        return $this->hasMany(StatusHistory::class);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function quoteResponses()
    {
        return $this->hasMany(QuoteResponse::class);
    }

    public function locationChanges()
    {
        return $this->hasMany(OrderLocationChange::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function adminNotifications() { return $this->hasMany(AdminNotification::class); }
    public function reviewer() { return $this->belongsTo(\App\Models\User::class, 'reviewed_by'); }

    public function getBalanceAttribute(): string
    {
        return number_format(max(0, (float) $this->total - (float) $this->verified_paid), 2, '.', '');
    }

    public function getStatusLabelAttribute(): string
    {
        return OrderStatusService::LABELS[$this->status] ?? $this->status;
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return ['pending' => 'Pendiente', 'pending_verification' => 'Pendiente de verificación', 'partial' => 'Pago parcial', 'paid' => 'Pagado', 'rejected' => 'Rechazado', 'refunded' => 'Reembolsado'][$this->payment_status] ?? $this->payment_status;
    }
}
