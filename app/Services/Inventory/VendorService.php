<?php

namespace App\Services\Inventory;

use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VendorService
{
    public function __construct(private readonly VendorCodeAllocator $codes) {}

    /**
     * @param  array{search?: string, is_active?: bool, per_page?: int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Vendor::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('shop_name', 'like', '%'.$search.'%')
                    ->orWhere('poc_name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('name')->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Vendor
    {
        unset($data['code']);

        $vendor = new Vendor($data);
        $vendor->is_active = (bool) ($data['is_active'] ?? true);
        $vendor->code = $this->codes->next();
        $vendor->save();

        return $vendor->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Vendor $vendor, array $data): Vendor
    {
        unset($data['code']);

        $vendor->update($data);

        return $vendor->fresh();
    }

    public function updateStatus(Vendor $vendor, bool $isActive): Vendor
    {
        $vendor->update(['is_active' => $isActive]);

        return $vendor->fresh();
    }

    public function delete(Vendor $vendor): void
    {
        $vendor->delete();
    }
}
