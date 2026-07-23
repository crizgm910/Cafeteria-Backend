<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->char('request_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn(['idempotency_key', 'request_fingerprint']));
    }
};
