<?php

namespace App\Services;

use App\Models\Order;

class OrderCodeGenerator
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function generate(): string
    {
        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            $code = 'PED-'.$suffix;
        } while (Order::where('code', $code)->exists());
        return $code;
    }
}
