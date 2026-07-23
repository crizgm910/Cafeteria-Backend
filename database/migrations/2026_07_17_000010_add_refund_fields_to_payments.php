<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('refund_reference')->nullable()->after('transaction_reference');
            $table->timestampTz('refunded_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['refund_reference', 'refunded_at']));
    }
};
