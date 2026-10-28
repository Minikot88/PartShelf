<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['zone', 'shelf', 'level', 'bin'])]
class StorageLocation extends Model
{
    protected $appends = ['path'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getPathAttribute(): string
    {
        return implode('-', [$this->zone, $this->shelf, $this->level, $this->bin]);
    }
}
