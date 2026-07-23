<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getColumnType('reservations', 'date') === 'date'
            && in_array(Schema::getColumnType('reservations', 'time'), ['time', 'time without time zone'], true)) {
            return;
        }

        DB::table('reservations')->orderBy('id')->each(function ($reservation) {
            $date = \DateTimeImmutable::createFromFormat('d/m/Y', $reservation->date)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d', $reservation->date);
            $normalizedTime = str_ireplace(
                [' a. m.', ' p. m.', ' a.m.', ' p.m.'],
                [' AM', ' PM', ' AM', ' PM'],
                $reservation->time
            );
            $time = \DateTimeImmutable::createFromFormat('h:i A', $normalizedTime)
                ?: \DateTimeImmutable::createFromFormat('H:i:s', $normalizedTime)
                ?: \DateTimeImmutable::createFromFormat('H:i', $normalizedTime);

            if (!$date || !$time) {
                throw new \RuntimeException("Formato inválido en la reserva {$reservation->id}.");
            }

            DB::table('reservations')->where('id', $reservation->id)->update([
                'date' => $date->format('Y-m-d'),
                'time' => $time->format('H:i:s'),
            ]);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->date('date')->change();
            $table->time('time')->change();
            $table->enum('status', ['pending', 'approved', 'ready', 'cancelled', 'completed'])
                ->default('pending')
                ->change();
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        // La normalización a tipos fecha/hora es irreversible por diseño.
    }
};
