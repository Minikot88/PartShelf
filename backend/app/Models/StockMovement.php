<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'movement_type', 'quantity_delta', 'reason', 'created_by_user_id', 'occurred_at', 'idempotency_key', 'request_fingerprint'])]
class StockMovement extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
