<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('public_visible')->default(true);
            $table->boolean('reservable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps(3);
            $table->index(['active', 'public_visible', 'reservable', 'sort_order'], 'service_areas_public_idx');
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_area_id')->constrained('service_areas')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 120)->nullable();
            $table->unsignedSmallInteger('min_capacity')->default(1);
            $table->unsignedSmallInteger('max_capacity');
            $table->string('status', 20)->default('available');
            $table->boolean('active')->default(true);
            $table->boolean('reservable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(3);
            $table->unique(['service_area_id', 'code']);
            $table->index(['service_area_id', 'active', 'reservable', 'status'], 'dining_tables_availability_idx');
        });

        Schema::create('reservation_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_area_id')->nullable()->constrained('service_areas')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->unsignedSmallInteger('slot_interval_minutes')->default(30);
            $table->unsignedSmallInteger('reservation_duration_minutes')->default(90);
            $table->unsignedSmallInteger('cleanup_buffer_minutes')->default(15);
            $table->boolean('active')->default(true);
            $table->timestamps(3);
            $table->unique(['service_area_id', 'day_of_week']);
            $table->index(['day_of_week', 'active']);
        });

        Schema::create('reservation_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_area_id')->nullable()->constrained('service_areas')->cascadeOnDelete();
            $table->foreignId('dining_table_id')->nullable()->constrained('dining_tables')->cascadeOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason', 500);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(3);
            $table->index(['service_area_id', 'starts_at', 'ends_at'], 'reservation_blocks_area_time_idx');
            $table->index(['dining_table_id', 'starts_at', 'ends_at'], 'reservation_blocks_table_time_idx');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('service_area_id')->nullable()->after('id')->constrained('service_areas')->restrictOnDelete();
            $table->foreignId('dining_table_id')->nullable()->after('service_area_id')->constrained('dining_tables')->restrictOnDelete();
            $table->string('phone', 30)->nullable()->after('email');
            $table->timestampTz('starts_at')->nullable()->after('time');
            $table->timestampTz('ends_at')->nullable()->after('starts_at');
            $table->unsignedBigInteger('lock_version')->default(0)->after('status');
            $table->text('staff_notes')->nullable()->after('lock_version');
            $table->foreignId('assigned_by')->nullable()->after('staff_notes')->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable()->after('assigned_by');
            $table->timestampTz('checked_in_at')->nullable()->after('approved_at');
            $table->timestampTz('seated_at')->nullable()->after('checked_in_at');
            $table->timestampTz('completed_at')->nullable()->after('seated_at');
            $table->timestampTz('cancelled_at')->nullable()->after('completed_at');
            $table->timestampTz('no_show_at')->nullable()->after('cancelled_at');
            $table->index(['dining_table_id', 'starts_at', 'ends_at'], 'reservations_table_time_idx');
            $table->index(['service_area_id', 'date', 'status'], 'reservations_area_date_status_idx');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table dining_tables add constraint dining_tables_capacity_check check (min_capacity >= 1 and max_capacity >= min_capacity)");
            DB::statement("alter table dining_tables add constraint dining_tables_status_check check (status in ('available','reserved','occupied','cleaning','blocked'))");
            DB::statement('alter table reservation_schedules add constraint reservation_schedules_day_check check (day_of_week between 0 and 6)');
            DB::statement('alter table reservation_schedules add constraint reservation_schedules_time_check check (closes_at > opens_at)');
            DB::statement('alter table reservation_blocks add constraint reservation_blocks_target_check check (service_area_id is not null or dining_table_id is not null)');
            DB::statement('alter table reservation_blocks add constraint reservation_blocks_time_check check (ends_at > starts_at)');
        }

        $now = now();
        DB::table('reservation_schedules')->insert(array_map(fn (int $day) => [
            'service_area_id' => null,
            'day_of_week' => $day,
            'opens_at' => '07:00:00',
            'closes_at' => '22:00:00',
            'slot_interval_minutes' => 30,
            'reservation_duration_minutes' => 90,
            'cleanup_buffer_minutes' => 15,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], range(0, 6)));
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['service_area_id']);
            $table->dropForeign(['dining_table_id']);
            $table->dropForeign(['assigned_by']);
            $table->dropColumn([
                'service_area_id', 'dining_table_id', 'phone', 'starts_at', 'ends_at', 'lock_version',
                'staff_notes', 'assigned_by', 'approved_at', 'checked_in_at', 'seated_at', 'completed_at',
                'cancelled_at', 'no_show_at',
            ]);
        });
        Schema::dropIfExists('reservation_blocks');
        Schema::dropIfExists('reservation_schedules');
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('service_areas');
    }
};

