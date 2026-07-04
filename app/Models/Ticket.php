<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model {
    use HasUuids;
    protected $guarded = [];

    public function items(): HasMany {
        return $this->hasMany(TicketItem::class);
    }

    public function payments(): HasMany {
        return $this->hasMany(Payment::class);
    }

    public function invoice(): HasOne {
        return $this->hasOne(Invoice::class);
    }

    public function activities(): HasMany {
        return $this->hasMany(TicketActivity::class);
    }
}