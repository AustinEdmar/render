<?php

namespace Database\Seeders;

use App\Models\AccessLevel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // =========================
        // ACCESS LEVELS
        // =========================

        /* AccessLevel::create([
            'name' => 'Admin',
        ]);

        AccessLevel::create([
            'name' => 'Gerente',
        ]);

        AccessLevel::create([
            'name' => 'Atendente',
        ]); */

        // =========================
        // USERS
        // =========================

        User::create([
            'name' => 'Austin',
            'email' => 'austin@gmail.com',
            'password' => Hash::make('923.eddy'),

            // Admin
            'access_level_id' => 1,
        ]);

        User::factory()->create([
            'name' => 'Martins',
            'email' => 'martins@gmail.com',
            'password' => Hash::make('923.eddy'),

            // Gerente
            'access_level_id' => 2,
        ]);

        // =========================
        // OTHER SEEDERS
        // =========================

        $this->call([
                // TaxRatesSeeder::class,
            CategorySeeder::class,
            CompanySeeder::class,
            ProductSeeder::class

        ]);

        $this->command->info('Seed concluído.');
    }
}