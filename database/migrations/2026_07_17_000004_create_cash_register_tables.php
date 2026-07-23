<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('open_user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->decimal('opening_amount', 12, 2);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestampsTz();
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('cash_register_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('ticket_id')->nullable()->constrained('tickets')->restrictOnDelete();
            $table->string('type', 30)->index();
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->timestampsTz();
            $table->unique(['ticket_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_register_sessions');
    }
};
