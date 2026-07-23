<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model {
    use HasUuids;
    protected $guarded = [];
    protected $hidden = ['collection_idempotency_key', 'collection_request_fingerprint'];

    protected function casts(): array {
        return [
            'amount' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo {
        return $this->belongsTo(Ticket::class);
    }
}
