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
        'external_reference',
        'amount',
        'fee',
        'total_paid',
        'payment_method',
        'payment_channel',
        'transaction_code',
        'status',
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