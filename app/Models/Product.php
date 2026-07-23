<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model {
    protected $guarded = [];

    protected function casts(): array {
        return [
            'active' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

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
        return $this->belongsToMany(AddOn::class, 'product_add_ons')->withPivot([
            'visible', 'selected_by_default', 'price_override', 'sort_order', 'override_recipe',
        ]);
    }

    public function getIsSellableAttribute(): bool {
        $hasIngredients = $this->relationLoaded('ingredients')
            ? $this->ingredients->isNotEmpty()
            : $this->ingredients()->exists();

        return $this->active && $this->category_id !== null && $hasIngredients;
    }
}
