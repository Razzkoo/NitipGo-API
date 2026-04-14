<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_processes', function (Blueprint $table) {
        $table->decimal('original_item_price', 12, 2)->nullable();
        $table->decimal('updated_item_price', 12, 2)->nullable();
        $table->decimal('updated_total_price', 12, 2)->nullable();
        $table->string('receipt_photo')->nullable();
        $table->text('price_notes')->nullable();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_processes', function (Blueprint $table) {
            $table->dropColumn([
                'original_item_price',
                'updated_item_price',
                'updated_total_price',
                'receipt_photo',
                'price_notes',
            ]);
        });
    }
};
