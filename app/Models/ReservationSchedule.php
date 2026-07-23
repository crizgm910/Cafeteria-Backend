<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationSchedule extends Model
{
    protected $fillable = ['service_area_id', 'day_of_week', 'opens_at', 'closes_at', 'slot_interval_minutes', 'reservation_duration_minutes', 'cleanup_buffer_minutes', 'active'];
    protected function casts(): array { return ['day_of_week' => 'integer', 'slot_interval_minutes' => 'integer', 'reservation_duration_minutes' => 'integer', 'cleanup_buffer_minutes' => 'integer', 'active' => 'boolean']; }
    public function area(): BelongsTo { return $this->belongsTo(ServiceArea::class, 'service_area_id'); }
}

