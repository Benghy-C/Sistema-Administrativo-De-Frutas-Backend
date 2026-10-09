<?php

namespace App\Services;

class ShipmentNumber
{
    public static function format(int $id): string
    {
        return 'ENV-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
