<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable(['sku', 'name', 'part_number', 'base_unit', 'category_id', 'storage_location_id', 'min_stock_quantity', 'image_path', 'tracking_mode', 'availability_status', 'notes', 'sku_is_generated', 'archived_at', 'reorder_needed', 'reorder_note', 'status_updated_at', 'status_updated_by', 'stock_version'])]
class Product extends Model
{
    protected $appends = ['image_url', 'internal_code'];

    protected function casts(): array
    {
        return [
            'min_stock_quantity' => 'decimal:3',
            'sku_is_generated' => 'boolean',
            'reorder_needed' => 'boolean',
            'stock_version' => 'integer',
            'archived_at' => 'immutable_datetime',
            'status_updated_at' => 'immutable_datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    public function fitments(): HasMany
    {
        return $this->hasMany(ProductFitment::class)->orderBy('make')->orderBy('model')->orderBy('year');
    }

    public function inventoryBalance(): HasOne
    {
        return $this->hasOne(InventoryBalance::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return config('partshelf.image_disk') === 'r2' ? '/api/products/'.$this->id.'/image' : Storage::disk(config('partshelf.image_disk'))->url($this->image_path);
    }

    public function getInternalCodeAttribute(): string
    {
        return 'PS-'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }
}
