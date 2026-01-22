<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            [
                'code' => 'VGB',
                'name' => 'British Virgin Islands',
                'currency_code' => 'USD',
                'flag_emoji' => '🇻🇬',
                'is_active' => true,
            ],
            [
                'code' => 'USA',
                'name' => 'United States',
                'currency_code' => 'USD',
                'flag_emoji' => '🇺🇸',
                'is_active' => true,
            ],
            [
                'code' => 'GBR',
                'name' => 'United Kingdom',
                'currency_code' => 'GBP',
                'flag_emoji' => '🇬🇧',
                'is_active' => true,
            ],
            [
                'code' => 'CAN',
                'name' => 'Canada',
                'currency_code' => 'CAD',
                'flag_emoji' => '🇨🇦',
                'is_active' => true,
            ],
            [
                'code' => 'JAM',
                'name' => 'Jamaica',
                'currency_code' => 'JMD',
                'flag_emoji' => '🇯🇲',
                'is_active' => true,
            ],
            [
                'code' => 'TTO',
                'name' => 'Trinidad and Tobago',
                'currency_code' => 'TTD',
                'flag_emoji' => '🇹🇹',
                'is_active' => true,
            ],
            [
                'code' => 'BRB',
                'name' => 'Barbados',
                'currency_code' => 'BBD',
                'flag_emoji' => '🇧🇧',
                'is_active' => true,
            ],
            [
                'code' => 'AUS',
                'name' => 'Australia',
                'currency_code' => 'AUD',
                'flag_emoji' => '🇦🇺',
                'is_active' => true,
            ],
            [
                'code' => 'DEU',
                'name' => 'Germany',
                'currency_code' => 'EUR',
                'flag_emoji' => '🇩🇪',
                'is_active' => true,
            ],
            [
                'code' => 'FRA',
                'name' => 'France',
                'currency_code' => 'EUR',
                'flag_emoji' => '🇫🇷',
                'is_active' => true,
            ],
        ];

        foreach ($countries as $country) {
            Country::create($country);
        }
    }
}
