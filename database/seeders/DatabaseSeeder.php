<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    /**
     * Seed the application's database.
     * 
     * NOTE: CustomsCodeSeeder is intentionally NOT included here.
     * Classification data should NEVER be seeded - use admin import tools instead.
     */
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            SubscriptionPlanSeeder::class,
            // ⚠️ CustomsCodeSeeder::class - REMOVED: Classification data should not be seeded
            AdminSeeder::class,
        ]);
    }
}
