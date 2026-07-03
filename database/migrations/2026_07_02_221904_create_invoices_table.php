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
        Schema::create('invoices', function (Blueprint $table) {
            
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('tickets')->restrictOnDelete();
            $table->string('rfc', 13);
            $table->string('legal_name', 255);
            $table->string('tax_regime', 10);
            $table->string('postal_code', 10);
            $table->string('cfdi_use', 10);
            $table->uuid('sat_uuid')->nullable()->unique();
            $table->enum('status', ['pending', 'stamped', 'cancelled', 'error'])->default('pending');
            $table->timestamp('stamped_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->timestamp('last_sync', 3)->useCurrent()->useCurrentOnUpdate();
        
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
