<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationBlock extends Model
{
    protected $fillable = ['service_area_id', 'dining_table_id', 'starts_at', 'ends_at', 'reason', 'active', 'created_by'];
    protected function casts(): array { return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'active' => 'boolean']; }
    public function area(): BelongsTo { return $this->belongsTo(ServiceArea::class, 'service_area_id'); }
    public function table(): BelongsTo { return $this->belongsTo(DiningTable::class, 'dining_table_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}

