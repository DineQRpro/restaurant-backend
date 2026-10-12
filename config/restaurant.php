<?php

return [
    /*
    | Cleaning allocations remain after checkout for this duration, then
    | occupancy treats them as released even if seating_cleaned_at is still NULL.
    */
    'cleaning_duration_seconds' => (int) env('RESTAURANT_CLEANING_DURATION_SECONDS', 300),

    /*
    | Thermal Print Bill header. Empty values are omitted from the receipt.
    | There is no Laravel source yet for logo or FSSAI.
    */
    'print' => [
        'name' => env('RESTAURANT_NAME', env('APP_NAME', 'Restaurant')),
        'address' => env('RESTAURANT_ADDRESS'),
        'phone' => env('RESTAURANT_PHONE'),
        'gstin' => env('RESTAURANT_GSTIN'),
    ],
];
