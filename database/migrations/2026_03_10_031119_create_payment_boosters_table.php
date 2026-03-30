<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_boosters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('traveler_id')
                ->constrained('travelers')
                ->cascadeOnDelete();

            $table->foreignId('booster_id')
                ->constrained('boosters')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->integer('unique_code')->nullable();
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('fee', 12, 2)->default(0);
            $table->enum('payment_method', ['transfer_bank', 'e_wallet'])->default('transfer_bank');
            $table->string('payment_channel')->nullable();   
            $table->string('account_number')->nullable();  
            $table->string('account_holder')->nullable(); 
            $table->string('proof_image')->nullable();     
            $table->string('payment_reference')->unique();
            $table->text('reject_reason')->nullable();
            $table->enum('status', ['pending', 'paid', 'rejected', 'expired'])->default('pending');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_boosters');
    }
};