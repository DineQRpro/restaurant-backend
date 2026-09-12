<?php

return [
    /*
    | GST follows the existing restaurant tax model already used by the waiter app:
    | CGST 2.5% + SGST 2.5% (combined 5%). Sourced from restaurant.json / orderTotals.
    | Override with BILLING_CGST_PERCENT / BILLING_SGST_PERCENT if the restaurant changes.
    */
    'cgst_percent' => (float) env('BILLING_CGST_PERCENT', 2.5),
    'sgst_percent' => (float) env('BILLING_SGST_PERCENT', 2.5),
];
