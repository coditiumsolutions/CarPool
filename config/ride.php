<?php

return [
    // Platform commission taken from each payment (percent).
    'commission_percent' => (float) env('RIDE_COMMISSION_PERCENT', 10),

    'payment_methods' => ['Cash', 'Easypaisa', 'JazzCash', 'Bank Transfer', 'Card'],

    'per_page' => 15,
];
