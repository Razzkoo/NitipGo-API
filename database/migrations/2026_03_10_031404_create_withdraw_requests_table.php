<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
         Schema::create('withdraw_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traveler_id')
                ->constrained('travelers')
                ->cascadeOnDelete();
            $table->foreignId('payout_account_id')
                ->constrained('payout_accounts')
                ->cascadeOnDelete();
            $table->decimal('amount',12,2);
            $table->decimal('fee',12,2)->default(0);
            $table->enum('withdraw_status',['pending','approved','paid','rejected'])->default('pending');
            $table->text('note')->nullable();
            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraw_requests');
    }
};
