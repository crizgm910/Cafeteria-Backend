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
        Schema::create('tickets', function (Blueprint $table) {
            
            $table->uuid('id')->primary();
            $table->string('ticket_number', 20)->unique();
            $table->enum('status', ['pending', 'paid', 'preparing', 'ready', 'delivered', 'cancelled'])->default('pending');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->enum('source', ['public_web', 'admin_panel'])->default('public_web');
            $table->timestamps(3);
            $table->timestamp('last_sync', 3)->useCurrent()->useCurrentOnUpdate();
        
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
