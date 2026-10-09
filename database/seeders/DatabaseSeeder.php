<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            BjmpRegionalSeeder::class,
            FacilityScheduleSeeder::class,
        ]);

        User::firstOrCreate(
            ['email' => 'admin@bjmp.gov.ph'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'role' => User::ROLE_SUPER_ADMIN,
            ]
        );

        $mabalacatFacility = Facility::where('name', 'Mabalacat City Jail')->first();

        if ($mabalacatFacility) {
            User::firstOrCreate(
                ['email' => 'mabalacat@bjmp.gov.ph'],
                [
                    'name' => 'Mabalacat Facility Admin',
                    'password' => bcrypt('password'),
                    'role' => User::ROLE_FACILITY_ADMIN,
                    'facility_id' => $mabalacatFacility->id,
                ]
            );
        }
    }
}
