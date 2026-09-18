<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxRatesSeeder extends Seeder
{
    public function run(): void
    {
        $taxRates = [

            [
                'tax_type' => 'IVA',
                'tax_code' => 'NOR',
                'description' => 'Taxa Normal',
                'tax_percentage' => 14.00,
                'exemption_reason' => null,
            ],

            [
                'tax_type' => 'IVA',
                'tax_code' => 'RED7',
                'description' => 'Taxa Reduzida 7%',
                'tax_percentage' => 7.00,
                'exemption_reason' => null,
            ],

            [
                'tax_type' => 'IVA',
                'tax_code' => 'RED5',
                'description' => 'Taxa Reduzida 5%',
                'tax_percentage' => 5.00,
                'exemption_reason' => null,
            ],

            [
                'tax_type' => 'IVA',
                'tax_code' => 'ISE',
                'description' => 'Isento',
                'tax_percentage' => 0.00,
                'exemption_reason' => 'Artigo 12.º do CIVA',
            ],

            [
                'tax_type' => 'IVA',
                'tax_code' => 'EXC',
                'description' => 'Regime de Exclusão',
                'tax_percentage' => 0.00,
                'exemption_reason' => 'Regime especial de exclusão',
            ],

            [
                'tax_type' => 'IVA',
                'tax_code' => 'OUT',
                'description' => 'Outros casos',
                'tax_percentage' => 0.00,
                'exemption_reason' => 'Outro enquadramento legal',
            ],
        ];

        foreach ($taxRates as $tax) {
            DB::table('tax_rates')->updateOrInsert(
                ['tax_code' => $tax['tax_code']],
                [
                    'tax_type' => $tax['tax_type'],
                    'description' => $tax['description'],
                    'tax_percentage' => $tax['tax_percentage'],
                    'exemption_reason' => $tax['exemption_reason'],
                    'country' => 'AO',
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}