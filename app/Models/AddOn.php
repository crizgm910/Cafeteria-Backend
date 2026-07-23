<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddOn extends Model {
    protected $guarded = [];

    protected function casts(): array {
        return [
            'active' => 'boolean',
            'public_visible' => 'boolean',
            'price_adjustment' => 'decimal:2',
            'quantity_required' => 'decimal:2',
        ];
    }

    public function ingredient(): BelongsTo {
        return $this->belongsTo(Ingredient::class);
    }

    public function products(): BelongsToMany {
        return $this->belongsToMany(Product::class, 'product_add_ons')->withPivot([
            'visible', 'selected_by_default', 'price_override', 'sort_order', 'override_recipe',
        ]);
    }

    public function categories(): BelongsToMany {
        return $this->belongsToMany(Category::class, 'category_add_ons');
    }

    public function ticketItems(): BelongsToMany {
        return $this->belongsToMany(TicketItem::class, 'ticket_item_add_ons');
    }

    public function recipeItems(): HasMany {
        return $this->hasMany(AddOnRecipe::class);
    }
}
