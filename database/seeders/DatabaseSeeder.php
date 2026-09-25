<?php

namespace Database\Seeders;

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
            ReferenceDataSeeder::class,
            AccessControlSeeder::class,
            PartnershipConfigSeeder::class,
            DnyProductSeeder::class,
            InitialAdministratorSeeder::class,
            InitialMemberSeeder::class,
            InitialWarehouseSeeder::class,
            InitialCompanyBankSeeder::class,
        ]);
    }
}
