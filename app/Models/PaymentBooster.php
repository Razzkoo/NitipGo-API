<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentBooster extends Model
{
    use HasFactory;

    protected $fillable = [
        'traveler_id',
        'booster_id',
        'amount',
        'unique_code',
        'fee',
        'payment_method',
        'payment_channel',
        'account_number',
        'account_holder',
        'proof_image',
        'payment_reference',
        'reject_reason',
        'status',
        'confirmed_by',
        'paid_at',
        'expired_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime'
    ];

    public function traveler()
    {
        return $this->belongsTo(Traveler::class);
    }

    public function booster()
    {
        return $this->belongsTo(Booster::class);
    }

    public function travelerBoosters()
    {
        return $this->hasMany(TravelerBooster::class);
    }
}