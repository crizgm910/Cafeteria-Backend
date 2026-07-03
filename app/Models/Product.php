<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model {
    protected $guarded = [];

    public function category(): BelongsTo {
        return $this->belongsTo(Category::class);
    }

    public function kitchenStation(): BelongsTo {
        return $this->belongsTo(KitchenStation::class);
    }

    public function ingredients(): BelongsToMany {
        return $this->belongsToMany(Ingredient::class, 'product_recipes')
                    ->withPivot('quantity_required');
    }

    public function addOns(): BelongsToMany {
        return $this->belongsToMany(AddOn::class, 'product_add_ons');
    }
}