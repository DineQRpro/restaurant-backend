<?php

namespace App\Services\DineType;

use App\Models\DineType;
use Illuminate\Support\Collection;

class DineTypeService
{
    /**
     * @return Collection<int, DineType>
     */
    public function active(): Collection
    {
        return DineType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
