<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_area_id',
        'dining_table_id',
        'name',
        'email',
        'phone',
        'date',
        'time',
        'starts_at',
        'ends_at',
        'guests',
        'status',
        'lock_version',
        'staff_notes',
        'assigned_by',
        'approved_at',
        'checked_in_at',
        'seated_at',
        'completed_at',
        'cancelled_at',
        'no_show_at',
        'idempotency_key',
        'request_fingerprint',
    ];

    protected $hidden = ['idempotency_key', 'request_fingerprint'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'checked_in_at' => 'immutable_datetime',
            'seated_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'no_show_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }

    public function area(): BelongsTo { return $this->belongsTo(ServiceArea::class, 'service_area_id'); }
    public function table(): BelongsTo { return $this->belongsTo(DiningTable::class, 'dining_table_id'); }
    public function assigner(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
}
