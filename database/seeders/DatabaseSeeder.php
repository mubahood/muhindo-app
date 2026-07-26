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
            RbacSeeder::class,           // role + permission matrix
            AdminUserSeeder::class,      // owner (super_admin) account
            DistrictSeeder::class,       // Uganda districts (reference data)
            PortfolioContentSeeder::class, // real bio/portfolio content
        ]);
    }
}
