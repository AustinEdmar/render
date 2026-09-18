<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Copia a imagem de public/image/categories para o disco 'public'
        | (storage/app/public/category_images), mantendo o mesmo padrão
        | usado para produtos (product_images).
        |--------------------------------------------------------------------------
        */
        $image = function (string $filename): ?string {
            $sourcePath = public_path('image/categories/' . $filename);

            if (!file_exists($sourcePath)) {
                $this->command->warn("Imagem não encontrada: {$sourcePath}");
                return null;
            }

            $targetRelativePath = 'category_images/' . $filename;

            if (!Storage::disk('public')->exists($targetRelativePath)) {
                Storage::disk('public')->put(
                    $targetRelativePath,
                    file_get_contents($sourcePath)
                );
            }

            return $targetRelativePath;
        };

        $categories = [
            [
                'name' => 'Bebidas',
                'image_path' => $image('bebidas.png'),
            ],
            [
                'name' => 'Comidas',
                'image_path' => $image('comidas.png'),
            ],
            [
                'name' => 'Sobremesas',
                'image_path' => $image('sobremesas.png'),
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

        $this->command->info('Categorias criadas/atualizadas com sucesso.');
    }
}