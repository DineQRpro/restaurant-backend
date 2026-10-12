<?php

namespace App\Http\Requests\Admin\Discounts;

class StoreDiscountRequest extends DiscountFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->discountRules();
    }
}
