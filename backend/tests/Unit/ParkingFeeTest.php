<?php

namespace Tests\Unit;

use App\Services\ParkingFee;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ParkingFeeTest extends TestCase
{
    public function test_fee_rounds_up_and_caps_each_24_hour_block(): void
    {
        $rate = ['first_hour' => 5000, 'additional_hour' => 3000, 'daily_max' => 50000];
        $start = CarbonImmutable::parse('2026-09-17 08:00:00');
        foreach ([0 => 5000, 3600 => 5000, 3601 => 8000, 86400 => 50000, 86401 => 55000, 172800 => 100000] as $seconds => $expected) {
            $this->assertSame($expected, ParkingFee::calculate($start->toDateTimeString(), $rate, $start->addSeconds($seconds))['subtotal']);
        }
    }

    public function test_no_negative_duration_and_zero_tariff_supported(): void
    {
        $r = ParkingFee::calculate('2026-09-17 09:00:00', ['first_hour' => 0, 'additional_hour' => 0, 'daily_max' => 1], CarbonImmutable::parse('2026-09-17 08:00:00'));
        $this->assertSame(0, $r['duration_minutes']);
        $this->assertSame(0, $r['subtotal']);
    }

    public function test_daily_max_does_not_increase_a_cheaper_daily_tariff(): void
    {
        $r = ParkingFee::calculate('2026-09-17 08:00:00', ['first_hour' => 2000, 'additional_hour' => 1000, 'daily_max' => 50000], CarbonImmutable::parse('2026-09-18 08:00:00'));
        $this->assertSame(25000, $r['subtotal']);
    }
}
