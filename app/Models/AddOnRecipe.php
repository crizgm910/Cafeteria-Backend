<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddOnRecipe extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity_required' => 'decimal:2'];
    }

    public function addOn(): BelongsTo { return $this->belongsTo(AddOn::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
}
