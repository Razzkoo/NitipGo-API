<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WithdrawRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'traveler_id',
        'payout_account_id',
        'amount',
        'fee',
        'withdraw_status',
        'note',
        'processed_by',
        'processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'processed_at' => 'datetime'
    ];

    public function traveler()
    {
        return $this->belongsTo(Traveler::class);
    }

    public function payoutAccount()
    {
        return $this->belongsTo(PayoutAccount::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}