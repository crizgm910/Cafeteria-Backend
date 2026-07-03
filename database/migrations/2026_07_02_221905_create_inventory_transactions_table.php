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
        Schema::create('inventory_transactions', function (Blueprint $table) {
            
            $table->uuid('id')->primary();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->enum('transaction_type', ['sale', 'restock', 'waste', 'adjustment']);
            $table->uuid('reference_id')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('stock_after_transaction', 10, 2);
            $table->timestamps(3);
            $table->timestamp('last_sync', 3)->useCurrent()->useCurrentOnUpdate();
        
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
