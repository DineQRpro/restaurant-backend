<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    public const ORDER = 'order';

    public const BILL = 'bill';

    public const VENDOR = 'vendor';

    public const GOODS_RECEIPT = 'goods_receipt';

    public const INVENTORY_CATEGORY = 'inventory_category';

    public const UNIT_OF_MEASURE = 'unit_of_measure';

    public const INVENTORY_ITEM = 'inventory_item';

    protected $fillable = [
        'name',
        'next_number',
    ];

    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
        ];
    }
}
