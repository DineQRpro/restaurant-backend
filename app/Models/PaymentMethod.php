<?php

namespace App\Models;

use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    public const SLUG_CASH = 'cash';

    public const SLUG_UPI = 'upi';

    public const SLUG_CARD = 'card';

    public const SLUG_SPLIT = 'split';

    public const SLUG_NON_CHARGEABLE = 'non-chargeable';

    public const SLUG_CREDIT = 'credit';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<PaymentMethod>  $query
     * @return Builder<PaymentMethod>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class);
    }

    public function isSplit(): bool
    {
        return $this->slug === self::SLUG_SPLIT;
    }

    public function requiresReference(): bool
    {
        return in_array($this->slug, [self::SLUG_UPI, self::SLUG_CARD], true);
    }

    public function isSplittable(): bool
    {
        return ! in_array($this->slug, [self::SLUG_SPLIT, self::SLUG_NON_CHARGEABLE], true);
    }

    public function displayLabel(): string
    {
        return match ($this->slug) {
            self::SLUG_CASH => '💵 Cash',
            self::SLUG_UPI => '📱 UPI',
            self::SLUG_CARD => '💳 Card',
            self::SLUG_SPLIT => '🔀 Split',
            default => $this->name,
        };
    }
}
