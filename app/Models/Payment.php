<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model {
    use HasUuids;
    protected $guarded = [];

    public function ticket(): BelongsTo {
        return $this->belongsTo(Ticket::class);
    }
}