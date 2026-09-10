<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('company')->insert([
            'name' => 'DEM DIGITAL - COMÉRCIO E SERVIÇOS',
            'trade_name' => 'Deem Digital',
            'nif' => '5000537039',
            'cae' => '62010',

            'address' => 'Rua Amilcar Cabral nº 252',
            'city' => 'Luanda',
            'province' => 'Luanda',
            'postal_code' => '0000',
            'country' => 'AO',

            'phone' => '+244900000000',
            'email' => 'suporte@deem.ao',
            'website' => 'https://deem.ao',

            'logo_path' => null,

            'software_name' => 'PX',
            'certificate_number' => 'AGT-2026-0001',
            'certificate_issuer' => 'AGT',
            'software_version' => '1.0.0',

            'currency' => 'AOA',
            'vat_regime' => 'normal',

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
