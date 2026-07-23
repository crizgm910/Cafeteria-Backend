<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('ingredient_id')->constrained('users')->nullOnDelete();
            $table->decimal('stock_before_transaction', 10, 2)->nullable()->after('quantity');
            $table->string('reason', 120)->nullable()->after('stock_after_transaction');
            $table->text('notes')->nullable()->after('reason');
            $table->index(['ingredient_id', 'created_at'], 'inventory_ingredient_created_idx');
            $table->index(['user_id', 'created_at'], 'inventory_user_created_idx');
        });

        DB::table('inventory_transactions')->orderBy('created_at')->orderBy('id')->get()->each(function ($row) {
            DB::table('inventory_transactions')->where('id', $row->id)->update([
                'stock_before_transaction' => (float) $row->stock_after_transaction - (float) $row->quantity,
                'reason' => 'Registro histórico previo a trazabilidad',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex('inventory_ingredient_created_idx');
            $table->dropIndex('inventory_user_created_idx');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['stock_before_transaction', 'reason', 'notes']);
        });
    }
};
