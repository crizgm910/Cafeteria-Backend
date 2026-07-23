<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('customer_phone', 30)->nullable()->after('customer_name');
            $table->string('customer_email')->nullable()->after('customer_phone');
            $table->string('idempotency_key', 100)->nullable()->unique()->after('source');
            $table->char('request_fingerprint', 64)->nullable()->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'customer_phone',
                'customer_email',
                'idempotency_key',
                'request_fingerprint',
            ]);
        });
    }
};
