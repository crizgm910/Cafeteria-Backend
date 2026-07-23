<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAddOnRecipe extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['quantity_required' => 'decimal:2']; }
}
