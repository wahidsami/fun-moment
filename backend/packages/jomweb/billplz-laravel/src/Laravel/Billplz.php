<?php

namespace Billplz\Laravel;

use Billplz\Bill;

class Billplz
{
    public static function bill(): Bill
    {
        return new Bill();
    }
}
