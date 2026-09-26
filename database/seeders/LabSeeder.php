<?php

namespace Database\Seeders;

use App\Models\InternetBundle;
use App\Models\InternetPackage;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneRouter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LabSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = $this->createOwner('Amadou Benjamain', 'amadoubenjamain@gmail.com', 'pass1234');

        $package = $this->createPackage('Omni Spot', 'High Speed Internet for all at unbeatable prices !', $owner, [
            [
                'number' => 1,
                'name' => 'Omni Day',
                'price' => 100.0,
                'short_code' => '24h',
                'description' => '...',
                'duration_hours' => 24,
                'limit_mbs' => 2000,
                'up_mbps' => 5,
                'down_mbps' => 5,
                'device_count' => 1,
            ],
            [
                'number' => 2,
                'name' => 'Omni Night',
                'price' => 100.0,
                'short_code' => 'nig',
                'description' => '...',
                'starts_at' => '00:00',
                'ends_at' => '06:00',
                'limit_mbs' => 5000,
                'up_mbps' => 5,
                'down_mbps' => 5,
                'device_count' => 1,
            ],
            [
                'number' => 3,
                'name' => 'Omni Week',
                'price' => 1000.0,
                'short_code' => '7d',
                'duration_hours' => 168,
                'limit_mbs' => 15000,
                'up_mbps' => 5,
                'down_mbps' => 5,
                'device_count' => 1,
            ],
            [
                'number' => 4,
                'name' => 'Omni Week +',
                'price' => 1500.0,
                'short_code' => '7dp',
                'duration_hours' => 168,
                'limit_mbs' => null,
                'up_mbps' => 5,
                'down_mbps' => 7,
                'device_count' => 2,
            ],
            [
                'number' => 5,
                'name' => 'Omni Month',
                'price' => 5000.0,
                'short_code' => '30d',
                'duration_hours' => 720,
                'limit_mbs' => null,
                'up_mbps' => 10,
                'down_mbps' => 10,
                'device_count' => 1,
            ],
            [
                'number' => 6,
                'name' => 'Omni Month XL',
                'price' => 15000.0,
                'short_code' => '30d',
                'duration_hours' => 720,
                'limit_mbs' => null,
                'up_mbps' => 10,
                'down_mbps' => 10,
                'device_count' => 2,
            ],
            [
                'number' => 7,
                'name' => 'Omni Month XXL',
                'price' => 50000.0,
                'short_code' => '30d',
                'duration_hours' => 720,
                'limit_mbs' => null,
                'up_mbps' => 10,
                'down_mbps' => 10,
                'device_count' => null,
            ],
        ]);

        $zone = $this->createZone('Home', '699858745', 'Bla bla bla...', $owner, [
            [
                'name' => 'Main Router',
                'net_address' => '10.50.0.1',
                'mac_address' => '00:11:22:33:44:55',
                'description' => 'Main Home Router.',
            ]
        ]);

        // Attach relationship (syncWithoutDetaching prevents duplicate entries on re-run)
        $zone->packages()->syncWithoutDetaching([$package->id]);
    }

    private function createOwner(string $name, string $email, string $password): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'owner',
            ]
        );
    }

    private function createPackage(string $name, string $description, User $owner, array $bundles): InternetPackage
    {
        $package = InternetPackage::updateOrCreate(
            ['name' => $name, 'owner_id' => $owner->id],
            ['description' => $description]
        );

        foreach ($bundles as $bundle) {
            $package->bundles()->updateOrCreate(
                ['name' => $bundle['name'], 'package_id' => $package->id],
                $bundle
            );
        }

        return $package;
    }

    private function createZone(string $name, string $phone, string $description, User $owner, array $routers): Zone
    {
        $zone = Zone::updateOrCreate(
            ['name' => $name, 'owner_id' => $owner->id],
            [
                'phone' => $phone,
                'description' => $description,
            ]
        );

        foreach ($routers as $router) {
            $zone->routers()->updateOrCreate(
                ['name' => $router['name']],
                ['net_address' => $router['net_address'], 'mac_address' => $router['mac_address'], 'description' => $router['description']]
            );
        }

        return $zone;
    }
}
