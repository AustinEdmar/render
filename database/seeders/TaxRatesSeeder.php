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
                'tax_code' => 'RED',
                'description' => 'Taxa Reduzida 7%',
                'tax_percentage' => 7.00,
                'exemption_reason' => null,
            ],

            [
                'tax_type' => 'IVA',
                'tax_code' => 'RED',
                'description' => 'Taxa Reduzida 5%',
                'tax_percentage' => 5.00,
                'exemption_reason' => null,
            ],

            [
                'tax_type' => 'IVA',
                'tax_code' => 'ISE',
                'description' => 'Isento',
                'tax_percentage' => 0.00,
                // TODO: substituir por um código válido do Anexo 6.4 da AGT
                // (catálogo de motivos de isenção). "Artigo 12.º do CIVA" é
                // texto livre — a AGT exige um taxExemptionCode de 3 letras
                // do catálogo oficial, não uma citação da lei. Ver secção 4
                // desta resposta sobre o FeInvoiceService.

                'exemption_reason' => null,
            ],




        ];

        foreach ($taxRates as $tax) {
            DB::table('tax_rates')->updateOrInsert(
                [
                    'tax_code' => $tax['tax_code'],
                    'tax_percentage' => $tax['tax_percentage'],
                ],
                [
                    'tax_type' => $tax['tax_type'],
                    'description' => $tax['description'],
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