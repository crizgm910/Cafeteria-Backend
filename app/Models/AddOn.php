<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AddOn extends Model {
    protected $guarded = [];

    public function ingredient(): BelongsTo {
        return $this->belongsTo(Ingredient::class);
    }

    public function products(): BelongsToMany {
        return $this->belongsToMany(Product::class, 'product_add_ons');
    }
}