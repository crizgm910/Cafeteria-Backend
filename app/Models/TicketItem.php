<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TicketItem extends Model {
    use HasUuids;
    protected $guarded = [];

    public function ticket(): BelongsTo {
        return $this->belongsTo(Ticket::class);
    }

    public function product(): BelongsTo {
        return $this->belongsTo(Product::class);
    }

    public function kitchenStation(): BelongsTo {
        return $this->belongsTo(KitchenStation::class);
    }

    public function addOns(): BelongsToMany {
        return $this->belongsToMany(AddOn::class, 'ticket_item_add_ons')
                    ->withPivot('price_charged')
                    ->withTimestamps();
    }
}