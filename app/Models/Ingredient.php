<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model {
    protected $guarded = [];

    public function inventoryTransactions(): HasMany {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function wastes(): HasMany {
        return $this->hasMany(Waste::class);
    }
}