<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only payment against an invoice (no updated_at).
 *
 * @property PaymentMethod $method
 * @property numeric-string $amount
 * @property numeric-string $balance_after
 */
class Payment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid', 'invoice_id', 'method', 'amount', 'balance_after',
        'reference', 'received_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
