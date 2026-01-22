<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CustomsCode;
use App\Models\Country;

class CustomsCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some countries to seed codes for
        $vgb = Country::where('code', 'VGB')->first();
        $usa = Country::where('code', 'USA')->first();
        $gbr = Country::where('code', 'GBR')->first();

        if (!$vgb || !$usa || !$gbr) {
            $this->command->warn('Countries not found. Please run CountrySeeder first.');
            return;
        }

        // Sample HS codes (common imports)
        $sampleCodes = [
            ['code' => '8471.30', 'description' => 'Portable automatic data processing machines, weighing not more than 10 kg', 'duty_rate' => 0.00],
            ['code' => '8517.12', 'description' => 'Telephones for cellular networks or for other wireless networks', 'duty_rate' => 0.00],
            ['code' => '6204.62', 'description' => 'Women\'s or girls\' trousers, bib and brace overalls, breeches and shorts of cotton', 'duty_rate' => 16.00],
            ['code' => '6109.10', 'description' => 'T-shirts, singlets and other vests of cotton, knitted or crocheted', 'duty_rate' => 16.50],
            ['code' => '9403.60', 'description' => 'Other wooden furniture', 'duty_rate' => 0.00],
            ['code' => '8528.72', 'description' => 'Reception apparatus for television, colour', 'duty_rate' => 5.00],
            ['code' => '8703.23', 'description' => 'Motor cars with spark-ignition engine of 1500-3000cc', 'duty_rate' => 2.50],
            ['code' => '0901.21', 'description' => 'Roasted coffee, not decaffeinated', 'duty_rate' => 0.00],
            ['code' => '1701.99', 'description' => 'Other cane or beet sugar', 'duty_rate' => 0.00],
            ['code' => '2204.21', 'description' => 'Wine in containers holding 2 litres or less', 'duty_rate' => 6.30],
        ];

        // Seed codes for each country
        foreach ([$vgb, $usa, $gbr] as $country) {
            foreach ($sampleCodes as $codeData) {
                CustomsCode::create([
                    'country_id' => $country->id,
                    'code' => $codeData['code'],
                    'description' => $codeData['description'],
                    'duty_rate' => $codeData['duty_rate'],
                    'hs_code_version' => '2022',
                ]);
            }
        }

        $this->command->info('Sample customs codes seeded for VGB, USA, and GBR.');
    }
}
