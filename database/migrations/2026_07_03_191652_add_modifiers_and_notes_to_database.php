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
        Schema::table('ticket_items', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('kds_completed_at');
        });

        Schema::create('add_ons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('price_adjustment', 10, 2)->default(0);
            $table->foreignId('ingredient_id')->nullable()->constrained('ingredients')->nullOnDelete();
            $table->decimal('quantity_required', 10, 2)->default(0);
            $table->timestamps();
            $table->timestamp('last_sync', 3)->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('product_add_ons', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained('add_ons')->cascadeOnDelete();
            $table->primary(['product_id', 'add_on_id']);
        });

        Schema::create('ticket_item_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('ticket_item_id')->constrained('ticket_items')->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained('add_ons')->cascadeOnDelete();
            $table->decimal('price_charged', 10, 2)->default(0);
            $table->timestamps();
            $table->timestamp('last_sync', 3)->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_item_add_ons');
        Schema::dropIfExists('product_add_ons');
        Schema::dropIfExists('add_ons');

        Schema::table('ticket_items', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
