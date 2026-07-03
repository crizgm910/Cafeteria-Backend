<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Waste extends Model {
    use HasUuids;
    protected $guarded = [];

    public function ingredient(): BelongsTo {
        return $this->belongsTo(Ingredient::class);
    }
}