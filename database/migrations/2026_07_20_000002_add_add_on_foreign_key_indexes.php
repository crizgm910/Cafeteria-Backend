<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('add_on_recipes', function (Blueprint $table) {
            $table->index('ingredient_id');
        });
        Schema::table('category_add_ons', function (Blueprint $table) {
            $table->index('add_on_id');
        });
        Schema::table('category_add_on_recipes', function (Blueprint $table) {
            $table->index('add_on_id');
            $table->index('ingredient_id');
        });
        Schema::table('product_add_on_recipes', function (Blueprint $table) {
            $table->index('add_on_id');
            $table->index('ingredient_id');
        });
        Schema::table('ticket_item_add_on_consumptions', function (Blueprint $table) {
            $table->index('ticket_item_id');
            $table->index('add_on_id');
            $table->index('ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_item_add_on_consumptions', function (Blueprint $table) {
            $table->dropIndex(['ticket_item_id']);
            $table->dropIndex(['add_on_id']);
            $table->dropIndex(['ingredient_id']);
        });
        Schema::table('product_add_on_recipes', function (Blueprint $table) {
            $table->dropIndex(['add_on_id']);
            $table->dropIndex(['ingredient_id']);
        });
        Schema::table('category_add_on_recipes', function (Blueprint $table) {
            $table->dropIndex(['add_on_id']);
            $table->dropIndex(['ingredient_id']);
        });
        Schema::table('category_add_ons', function (Blueprint $table) {
            $table->dropIndex(['add_on_id']);
        });
        Schema::table('add_on_recipes', function (Blueprint $table) {
            $table->dropIndex(['ingredient_id']);
        });
    }
};
