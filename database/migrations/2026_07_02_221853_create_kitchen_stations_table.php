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
        Schema::create('kitchen_stations', function (Blueprint $table) {
            
            $table->id();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->integer('target_prep_time_seconds')->default(300);
            $table->timestamps();
            $table->timestamp('last_sync', 3)->useCurrent()->useCurrentOnUpdate();
        
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_stations');
    }
};
