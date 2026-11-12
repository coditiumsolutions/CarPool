<?php

namespace App\Support;

class Currency
{
    /** Format an amount as Pakistani Rupees, e.g. "Rs. 1,250". */
    public static function format(float|int|string|null $amount): string
    {
        return config('currency.symbol').' '.number_format((float) $amount, config('currency.decimals'));
    }
}
