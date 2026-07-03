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
        Schema::create('wastes', function (Blueprint $table) {
            
            $table->uuid('id')->primary();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->enum('reason', ['expired', 'spilled', 'kitchen_error', 'other']);
            $table->text('notes')->nullable();
            $table->integer('reported_by_user_id')->nullable();
            $table->timestamps(3);
            $table->timestamp('last_sync', 3)->useCurrent()->useCurrentOnUpdate();
        
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wastes');
    }
};
