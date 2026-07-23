<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model {
    protected $guarded = [];

    protected function casts(): array {
        return [
            'active' => 'boolean',
        ];
    }
    
    public function products(): HasMany {
        return $this->hasMany(Product::class);
    }

    public function addOns(): BelongsToMany {
        return $this->belongsToMany(AddOn::class, 'category_add_ons')->withPivot([
            'visible', 'selected_by_default', 'price_override', 'sort_order', 'override_recipe',
        ])->withTimestamps();
    }
}
