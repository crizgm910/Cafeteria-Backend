<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('add_ons', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->boolean('public_visible')->default(true)->after('active');
            $table->integer('sort_order')->default(0)->after('public_visible');
        });

        Schema::table('product_add_ons', function (Blueprint $table) {
            $table->boolean('visible')->nullable();
            $table->boolean('selected_by_default')->nullable();
            $table->decimal('price_override', 10, 2)->nullable();
            $table->integer('sort_order')->nullable();
            $table->boolean('override_recipe')->default(false);
        });

        Schema::table('ticket_item_add_ons', function (Blueprint $table) {
            $table->string('name_snapshot', 100)->nullable()->after('add_on_id');
        });

        Schema::create('add_on_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('add_on_id')->constrained('add_ons')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->decimal('quantity_required', 10, 2);
            $table->timestamps();
            $table->unique(['add_on_id', 'ingredient_id']);
        });

        Schema::create('category_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained('add_ons')->cascadeOnDelete();
            $table->boolean('visible')->default(true);
            $table->boolean('selected_by_default')->default(false);
            $table->decimal('price_override', 10, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('override_recipe')->default(false);
            $table->timestamps();
            $table->unique(['category_id', 'add_on_id']);
        });

        Schema::create('category_add_on_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained('add_ons')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->decimal('quantity_required', 10, 2);
            $table->timestamps();
            $table->unique(['category_id', 'add_on_id', 'ingredient_id'], 'category_add_on_recipe_unique');
        });

        Schema::create('product_add_on_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained('add_ons')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->decimal('quantity_required', 10, 2);
            $table->timestamps();
            $table->unique(['product_id', 'add_on_id', 'ingredient_id'], 'product_add_on_recipe_unique');
        });

        Schema::create('ticket_item_add_on_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('ticket_item_id')->constrained('ticket_items')->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained('add_ons')->restrictOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->decimal('quantity_consumed', 10, 2);
            $table->timestamps();
        });

        DB::table('add_ons')->whereNotNull('ingredient_id')->where('quantity_required', '>', 0)
            ->orderBy('id')->each(function ($addOn): void {
                DB::table('add_on_recipes')->insertOrIgnore([
                    'add_on_id' => $addOn->id,
                    'ingredient_id' => $addOn->ingredient_id,
                    'quantity_required' => $addOn->quantity_required,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('product_add_ons')->update(['visible' => true]);

        if (DB::getDriverName() === 'pgsql') {
            foreach (['add_on_recipes', 'category_add_ons', 'category_add_on_recipes', 'product_add_on_recipes', 'ticket_item_add_on_consumptions'] as $table) {
                DB::statement("ALTER TABLE public.{$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("REVOKE ALL ON TABLE public.{$table} FROM anon, authenticated, service_role");
            }
            DB::statement('REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM anon, authenticated, service_role');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_item_add_on_consumptions');
        Schema::dropIfExists('product_add_on_recipes');
        Schema::dropIfExists('category_add_on_recipes');
        Schema::dropIfExists('category_add_ons');
        Schema::dropIfExists('add_on_recipes');
        Schema::table('ticket_item_add_ons', fn (Blueprint $table) => $table->dropColumn('name_snapshot'));
        Schema::table('product_add_ons', fn (Blueprint $table) => $table->dropColumn(['visible', 'selected_by_default', 'price_override', 'sort_order', 'override_recipe']));
        Schema::table('add_ons', fn (Blueprint $table) => $table->dropColumn(['description', 'public_visible', 'sort_order']));
    }
};
