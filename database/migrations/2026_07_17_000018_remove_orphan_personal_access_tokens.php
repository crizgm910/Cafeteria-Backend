<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('users')
                ->whereColumn('users.id', 'personal_access_tokens.tokenable_id'))
            ->delete();
    }

    public function down(): void
    {
        // Los tokens sin propietario no son recuperables ni deben restaurarse.
    }
};
