<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount_received', 12, 2)->nullable()->after('amount');
            $table->decimal('change_amount', 12, 2)->nullable()->after('amount_received');
            $table->foreignId('confirmed_by')->nullable()->after('paid_at')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['amount_received', 'change_amount']);
        });
    }
};
