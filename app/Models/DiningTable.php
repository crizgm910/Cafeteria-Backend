<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningTable extends Model
{
    protected $fillable = ['service_area_id', 'code', 'name', 'min_capacity', 'max_capacity', 'status', 'active', 'reservable', 'sort_order', 'lock_version'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'reservable' => 'boolean', 'min_capacity' => 'integer', 'max_capacity' => 'integer', 'sort_order' => 'integer', 'lock_version' => 'integer'];
    }

    public function area(): BelongsTo { return $this->belongsTo(ServiceArea::class, 'service_area_id'); }
    public function reservations(): HasMany { return $this->hasMany(Reservation::class); }
    public function blocks(): HasMany { return $this->hasMany(ReservationBlock::class); }
}

