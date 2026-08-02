<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Proof that a person owns a product, and the door to its file. */
class ProductLicense extends Model
{
    protected $fillable = ['user_id', 'product_id', 'invoice_id', 'download_count', 'granted_at'];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
