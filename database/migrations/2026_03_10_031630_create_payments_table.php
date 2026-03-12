<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->enum('payment_method',['transfer_bank','e_wallet'])->default('transfer_bank');
            $table->string('payment_channel')->nullable();
            $table->decimal('amount',12,2);
            $table->decimal('fee',12,2)->default(0);
            $table->decimal('total_paid',12,2)->default(0);
            $table->enum('payment_status',['pending','paid','failed','expired','refunded'])->default('pending');
            $table->string('payment_reference')->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->unique('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
