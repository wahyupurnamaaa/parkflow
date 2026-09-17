<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class ParkingFee
{
    public static function calculate(string $entry, array $rate, ?CarbonImmutable $exit = null): array
    {
        $seconds = max(0, CarbonImmutable::parse($entry)->diffInSeconds($exit ?? CarbonImmutable::now(), false));
        $hours = max(1, (int) ceil($seconds / 3600));
        $days = intdiv($hours, 24);
        $remaining = $hours % 24;
        $dayFee = min($rate['daily_max'], $rate['first_hour'] + 23 * $rate['additional_hour']);
        $subtotal = $days * $dayFee + ($remaining ? min($rate['daily_max'], $rate['first_hour'] + ($remaining - 1) * $rate['additional_hour']) : 0);

        return ['duration_minutes' => (int) ceil($seconds / 60), 'billable_hours' => $hours, 'subtotal' => $subtotal];
    }
}
