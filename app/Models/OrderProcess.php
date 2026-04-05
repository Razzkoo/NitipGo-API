<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProcess extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'original_item_price',
        'updated_item_price',
        'updated_total_price',
        'receipt_photo',
        'price_notes',
        'status',
    ];

    protected $casts = [
        'original_item_price' => 'decimal:2',
        'updated_item_price'  => 'decimal:2',
        'updated_total_price' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}