<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $bebidasId = DB::table('categories')->where('name', 'Bebidas')->value('id');
        $comidasId = DB::table('categories')->where('name', 'Comidas')->value('id');
        $sobremesasId = DB::table('categories')->where('name', 'Sobremesas')->value('id');

        // TAXAS CORRETAS (ATUALIZADO)
        $taxNOR = DB::table('tax_rates')
            ->where('tax_code', 'NOR')
            ->value('id');

        $taxRED7 = DB::table('tax_rates')
            ->where('tax_code', 'RED7')
            ->value('id');

        $taxRED5 = DB::table('tax_rates')
            ->where('tax_code', 'RED5')
            ->value('id');

        $taxISE = DB::table('tax_rates')
            ->where('tax_code', 'ISE')
            ->value('id');

        $taxEXC = DB::table('tax_rates')
            ->where('tax_code', 'EXC')
            ->value('id');

        $products = [

            [
                'name' => 'Coca-Cola 350ml',
                'product_code' => 'BEB0001',
                'description' => 'Refrigerante Coca-Cola 350ml',
                'price' => 500.00,
                'unit' => 'UN',

                'tax_rate_id' => $taxNOR,
                'tax_exemption_reason' => null,
                'stock' => 50,
                'barcode' => '789100000001',
                'category_id' => $bebidasId,
                'image_path' => 'image/product/coca.png',
                'is_active' => true,
            ],

            [
                'name' => 'Sumo Natural',
                'product_code' => 'BEB0002',
                'description' => 'Sumo natural de fruta',
                'price' => 800.00,
                'unit' => 'UN',
                'tax_rate_id' => $taxRED7,
                'tax_exemption_reason' => null,
                'stock' => 30,
                'barcode' => '789100000002',
                'category_id' => $bebidasId,
                'image_path' => 'image/product/sumo.jpg',
                'is_active' => true,
            ],

            [
                'name' => 'Hambúrguer Completo',
                'product_code' => 'COM0001',
                'description' => 'Hambúrguer com queijo e batata frita',
                'price' => 2500.00,
                'unit' => 'UN',

                'tax_rate_id' => $taxNOR,
                'tax_exemption_reason' => null,
                'stock' => 20,
                'barcode' => '789100000003',
                'category_id' => $comidasId,
                'image_path' => 'image/product/hamburguer.png',
                'is_active' => true,
            ],

            [
                'name' => 'Pizza Familiar',
                'product_code' => 'COM0002',
                'description' => 'Pizza grande com 8 fatias',
                'price' => 7000.00,
                'unit' => 'UN',

                'tax_rate_id' => $taxEXC,
                'tax_exemption_reason' => null,
                'stock' => 15,
                'barcode' => '789100000004',
                'category_id' => $comidasId,
                'image_path' => 'image/product/pizza.png',
                'is_active' => true,
            ],

            [
                'name' => 'Gelado Baunilha',
                'product_code' => 'SOB0001',
                'description' => 'Gelado sabor baunilha',
                'price' => 1200.00,
                'unit' => 'UN',
                'tax_rate_id' => $taxRED5,
                'tax_exemption_reason' => null,
                'stock' => 25,
                'barcode' => '789100000005',
                'category_id' => $sobremesasId,
                'image_path' => 'image/product/gelado.jpeg',
                'is_active' => true,
            ],

            [
                'name' => 'Água Potável Social',
                'product_code' => 'BEB0003',
                'description' => 'Produto isento de IVA',
                'price' => 200.00,
                'unit' => 'UN',
                'tax_rate_id' => $taxISE,
                'tax_exemption_reason' => 'Artigo 12.º do CIVA',
                'stock' => 100,
                'barcode' => '789100000006',
                'category_id' => $bebidasId,
                'image_path' => 'image/product/agua.png',
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {

            DB::table('products')->updateOrInsert(
                ['product_code' => $product['product_code']],
                [
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'unit' => $product['unit'],
                    'tax_rate_id' => $product['tax_rate_id'],

                    'tax_exemption_reason' => $product['tax_exemption_reason'],
                    'stock' => $product['stock'],
                    'barcode' => $product['barcode'],
                    'category_id' => $product['category_id'],
                    'image_path' => $product['image_path'],
                    'is_active' => $product['is_active'],

                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}