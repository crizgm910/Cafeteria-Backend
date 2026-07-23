<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const VALUES = "'stripe','mercado_pago','conekta','codi','cash','card_terminal','pay_at_pickup'";

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payments MODIFY gateway_provider ENUM('.self::VALUES.') NOT NULL');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_gateway_provider_check');
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_gateway_provider_check CHECK (gateway_provider IN ('.self::VALUES.'))');
        }
    }

    public function down(): void
    {
        DB::table('payments')->where('gateway_provider', 'pay_at_pickup')->update(['gateway_provider' => 'cash']);
    }
};
