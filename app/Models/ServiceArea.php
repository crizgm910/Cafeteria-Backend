<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceArea extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'image_url', 'active', 'public_visible', 'reservable', 'sort_order'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'public_visible' => 'boolean', 'reservable' => 'boolean', 'sort_order' => 'integer'];
    }

    public function tables(): HasMany { return $this->hasMany(DiningTable::class); }
    public function schedules(): HasMany { return $this->hasMany(ReservationSchedule::class); }
    public function blocks(): HasMany { return $this->hasMany(ReservationBlock::class); }
    public function reservations(): HasMany { return $this->hasMany(Reservation::class); }
}

