<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table order_processes
        Schema::create('order_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->decimal('original_item_price', 12, 2)->nullable();
            $table->decimal('updated_item_price', 12, 2)->nullable();
            $table->decimal('updated_total_price', 12, 2)->nullable();
            $table->string('receipt_photo')->nullable();
            $table->text('price_notes')->nullable(); 
            $table->enum('status', ['price_updated', 'waiting_payment', 'paid'])->default('price_updated');
            $table->timestamps();
        });

        // Add column in transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payment_proof')->nullable()->after('image');
            $table->timestamp('paid_at')->nullable()->after('payment_proof');
            $table->boolean('price_confirmed')->default(false)->after('paid_at'); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_processes');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['payment_proof', 'paid_at', 'price_confirmed']);
        });
    }
};