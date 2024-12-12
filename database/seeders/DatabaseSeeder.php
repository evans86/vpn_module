<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            AdminUserSeeder::class,
            LocationsSeeder::class,
//            PackSeeder::class,
//            PanelSeeder::class,
//            SalesmanSeeder::class,
//            ServerSeeder::class,
//            PackSalesmanSeeder::class,
        ]);
    }
}
