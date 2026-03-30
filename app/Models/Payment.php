<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'traveler_id',
        'payment_method',
        'payment_channel',
        'account_number',
        'account_holder',
        'amount',
        'unique_code',
        'total_paid',
        'fee',
        'payment_status',
        'proof_image',
        'payment_reference',
        'reject_reason',
        'paid_at',
        'expired_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}