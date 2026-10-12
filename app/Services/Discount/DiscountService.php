<?php

namespace App\Services\Discount;

use App\Enums\DiscountAppliesTo;
use App\Enums\DiscountType;
use App\Exceptions\BusinessConflictException;
use App\Models\Discount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DiscountService
{
    /**
     * @param  array{
     *     search?: string,
     *     is_active?: bool,
     *     type?: string,
     *     applies_to?: string
     * }  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Discount::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', '%'.$search.'%');
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['applies_to'])) {
            $query->where('applies_to', $filters['applies_to']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Discount
    {
        $data['is_active'] ??= true;
        $data['min_level'] ??= 0;
        $data['max_level'] ??= 0;
        $data['value'] ??= 0;

        return Discount::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Discount $discount, array $data): Discount
    {
        $discount->update($data);

        return $discount->fresh();
    }

    public function updateStatus(Discount $discount, bool $isActive): Discount
    {
        $discount->update(['is_active' => $isActive]);

        return $discount->fresh();
    }

    public function delete(Discount $discount): void
    {
        if ($discount->bills()->exists()) {
            throw new BusinessConflictException(
                'This discount cannot be deleted because it is used on historical bills. Deactivate it instead.'
            );
        }

        $discount->delete();
    }

    /**
     * Active order-level discounts for View Bill selection.
     *
     * Legacy loaded every tbl_discounts row. Laravel filters inactive and product-level
     * rows so a product discount cannot be applied to the whole bill. Dates are not
     * filtered because Core PHP getAllDiscounts() did not.
     *
     * @return Collection<int, Discount>
     */
    public function orderLevelOptions(): Collection
    {
        return Discount::query()
            ->active()
            ->where('applies_to', DiscountAppliesTo::Order)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<DiscountType>
     */
    public function types(): array
    {
        return DiscountType::cases();
    }

    /**
     * @return list<DiscountAppliesTo>
     */
    public function appliesToOptions(): array
    {
        return DiscountAppliesTo::cases();
    }
}
