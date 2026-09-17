<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') && ! env('DEMO_SEED', false)) {
            throw new \RuntimeException('Demo seed dinonaktifkan di production.');
        }
        foreach (['admin' => 'Parking Admin', 'officer' => 'Petugas Parkir'] as $role => $name) {
            if (! User::where('email', "$role@parkflow.test")->exists()) {
                $u = new User;
                $u->name = $name;
                $u->email = "$role@parkflow.test";
                $u->password = Hash::make('ParkFlow123!');
                $u->role = $role;
                $u->save();
            }
        }
        if (DB::table('parking_locations')->exists()) {
            return;
        }
        $now = now();
        $location = DB::table('parking_locations')->insertGetId(['name' => 'ParkFlow Central', 'address' => 'Jakarta, Indonesia', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([['Basement 1', 'CAR', 'B1', 24], ['Basement 2', 'CAR', 'B2', 18], ['Area Motor', 'MOTORCYCLE', 'M', 30], ['Outdoor', 'TRUCK', 'O', 8]] as [$name,$type,$prefix,$count]) {
            $area = DB::table('parking_areas')->insertGetId(['location_id' => $location, 'name' => $name, 'vehicle_type' => $type, 'created_at' => $now, 'updated_at' => $now]);
            for ($i = 1; $i <= $count; $i++) {
                DB::table('parking_slots')->insert(['area_id' => $area, 'code' => $prefix.'-'.str_pad($i, 3, '0', STR_PAD_LEFT), 'status' => 'AVAILABLE', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        foreach (['CAR' => [5000, 3000, 50000], 'MOTORCYCLE' => [2000, 1000, 15000], 'TRUCK' => [10000, 5000, 100000], 'BUS' => [15000, 5000, 120000], 'OTHER' => [5000, 3000, 50000]] as $type => $r) {
            DB::table('parking_rates')->insert(['vehicle_type' => $type, 'first_hour' => $r[0], 'additional_hour' => $r[1], 'daily_max' => $r[2], 'lost_penalty' => 25000, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}
