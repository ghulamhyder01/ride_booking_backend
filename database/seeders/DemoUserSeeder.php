<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // Demo rider
        $riderUser = User::updateOrCreate(
            ['email' => 'rider@example.com'],
            [
                'name' => 'Demo Rider',
                'password' => Hash::make('Rider@12345'),
                'role' => 'rider',
            ]
        );

        Rider::updateOrCreate(
            ['user_id' => $riderUser->id],
            ['phone' => '03000000001']
        );

        // Demo drivers around Lahore
        $drivers = [
            ['name' => 'Driver One', 'email' => 'driver1@example.com', 'phone' => '03000000011', 'category' => 'economy', 'lat' => 31.5204, 'lng' => 74.3587],
            ['name' => 'Driver Two', 'email' => 'driver2@example.com', 'phone' => '03000000012', 'category' => 'comfort', 'lat' => 31.5300, 'lng' => 74.3500],
            ['name' => 'Driver Three', 'email' => 'driver3@example.com', 'phone' => '03000000013', 'category' => 'economy', 'lat' => 31.5100, 'lng' => 74.3600],
            ['name' => 'Driver Four', 'email' => 'driver4@example.com', 'phone' => '03000000014', 'category' => 'comfort', 'lat' => 31.5400, 'lng' => 74.3700],
            ['name' => 'Driver Five', 'email' => 'driver5@example.com', 'phone' => '03000000015', 'category' => 'economy', 'lat' => 31.5000, 'lng' => 74.3400],
            ['name' => 'Driver Six', 'email' => 'driver6@example.com', 'phone' => '03000000016', 'category' => 'comfort', 'lat' => 31.5250, 'lng' => 74.3800],
            ['name' => 'Driver Seven', 'email' => 'driver7@example.com', 'phone' => '03000000017', 'category' => 'economy', 'lat' => 31.5150, 'lng' => 74.3450],
            ['name' => 'Driver Eight', 'email' => 'driver8@example.com', 'phone' => '03000000018', 'category' => 'comfort', 'lat' => 31.5350, 'lng' => 74.3650],
            ['name' => 'Driver Nine', 'email' => 'driver9@example.com', 'phone' => '03000000019', 'category' => 'economy', 'lat' => 31.5050, 'lng' => 74.3550],
            ['name' => 'Driver Ten', 'email' => 'driver10@example.com', 'phone' => '03000000020', 'category' => 'comfort', 'lat' => 31.5450, 'lng' => 74.3450],
        ];

        foreach ($drivers as $driverData) {
            $user = User::updateOrCreate(
                ['email' => $driverData['email']],
                [
                    'name' => $driverData['name'],
                    'password' => Hash::make('Driver@12345'),
                    'role' => 'driver',
                ]
            );

            Driver::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'phone' => $driverData['phone'],
                    'vehicle_category' => $driverData['category'],
                    'availability' => 'available',
                    'latitude' => $driverData['lat'],
                    'longitude' => $driverData['lng'],
                ]
            );
        }
    }
}