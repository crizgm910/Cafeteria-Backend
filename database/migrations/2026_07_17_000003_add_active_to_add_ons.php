<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('add_ons', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('quantity_required');
        });
    }

    public function down(): void
    {
        Schema::table('add_ons', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
