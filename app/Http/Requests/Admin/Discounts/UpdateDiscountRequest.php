<?php

namespace App\Http\Requests\Admin\Discounts;

use App\Models\Discount;

class UpdateDiscountRequest extends DiscountFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Discount $discount */
        $discount = $this->route('discount');

        return $this->discountRules($discount->id);
    }
}
