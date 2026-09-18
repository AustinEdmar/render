<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Bebidas',
                'image_path' => 'image/categori/bebidas.png',
            ],
            [
                'name' => 'Comidas',
                'image_path' => 'image/categori/comidas.png',
            ],
            [
                'name' => 'Sobremesas',
                'image_path' => 'image/categori/sobremesas.png',
            ],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->updateOrInsert(
                ['name' => $category['name']],
                [
                    'image_path' => $category['image_path'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
