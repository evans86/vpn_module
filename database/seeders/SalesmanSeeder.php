<?php

namespace Database\Seeders;

use App\Models\Salesman\Salesman;
use Illuminate\Database\Seeder;

class SalesmanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $salesmen = [
            [
                'telegram_id' => 123456789,
                'username' => 'seller1',
                'token' => 'token1',
                'status' => Salesman::ACTIVE,
                'bot_link' => 'https://t.me/vpn_bot?start=token1'
            ],
            [
                'telegram_id' => 987654321,
                'username' => 'seller2',
                'token' => 'token2',
                'status' => Salesman::INACTIVE,
                'bot_link' => 'https://t.me/vpn_bot?start=token2'
            ],
            [
                'telegram_id' => 555555555,
                'username' => 'seller3',
                'token' => 'token3',
                'status' => Salesman::ACTIVE,
                'bot_link' => 'https://t.me/vpn_bot?start=token3'
            ],
        ];

        foreach ($salesmen as $salesman) {
            Salesman::create($salesman);
        }
    }
}
