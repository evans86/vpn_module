<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationsSeeder extends Seeder
{
    public function run()
    {
        $data[] = [
            'id' => 1,
            'code' => 'NL',
            'emoji' => ':nl:'
            ];
        $data[] = [
            'id' => 2,
            'code' => 'RU',
            'emoji' => ':ru:'
        ];
        DB::table('location')->insert($data);
    }
}
