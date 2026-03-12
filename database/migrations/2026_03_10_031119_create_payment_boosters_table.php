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

            $table->string('external_reference')->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->decimal('fee',12,2)->default(0);
            $table->decimal('total_paid',12,2)->default(0);
            $table->enum('payment_method',['transfer','ewallet','card']);
            $table->enum('payment_channel',['bca','bni','mandiri','ovo','dana','gopay','visa','mastercard'])->nullable();
            $table->string('transaction_code')->nullable();
            $table->enum('status',['pending','paid','failed','expired'])->default('pending');                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   
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
