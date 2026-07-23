<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model {
    use HasUuids;
    protected $guarded = [];

    protected $hidden = ['tracking_token', 'request_fingerprint', 'idempotency_key'];

    protected function casts(): array {
        return [
            'tracking_token' => 'encrypted',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

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
