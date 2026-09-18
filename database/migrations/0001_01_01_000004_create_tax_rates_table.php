<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();

            // SAF-T Angola
            $table->string('tax_type', 10)->default('IVA'); // IVA, IS, NS
            $table->string('tax_code', 10)->unique();       // NOR, RED, ISE, EXC, OUT

            $table->string('description');
            $table->decimal('tax_percentage', 8, 2)->default(0);
            $table->string('country', 3)->default('AO');
            $table->boolean('is_active')->default(true);

            // Motivo de isenção — obrigatório no SAF-T quando taxa = 0
            $table->string('exemption_reason')->nullable();

            $table->timestamps();
        });

        // Seed com as taxas de IVA vigentes em Angola (Lei 17/19 — CIVA Angola)
        // NOR  = taxa normal 14%
        // RED  = taxa reduzida (produtos alimentares essenciais, medicamentos)
        // ISE  = isento (artigo 12.º do CIVA)
        // EXC  = regime de exclusão (artigo 2.º do CIVA — pequenos contribuintes)
        // OUT  = outra taxa / casos especiais
        DB::table('tax_rates')->insert([
            [
                'tax_type' => 'IVA',
                'tax_code' => 'NOR',
                'description' => 'Taxa normal de IVA',
                'tax_percentage' => 14.00,
                'country' => 'AO',
                'is_active' => true,
                'exemption_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tax_type' => 'IVA',
                'tax_code' => 'RED5',
                'description' => 'Taxa reduzida de IVA',
                'tax_percentage' => 5.00,
                'country' => 'AO',
                'is_active' => true,
                'exemption_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'tax_type' => 'IVA',
                'tax_code' => 'RED7',
                'description' => 'Taxa reduzida de IVA',
                'tax_percentage' => 7.00,
                'country' => 'AO',
                'is_active' => true,
                'exemption_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tax_type' => 'IVA',
                'tax_code' => 'ISE',
                'description' => 'Isento de IVA',
                'tax_percentage' => 0.00,
                'country' => 'AO',
                'is_active' => true,
                'exemption_reason' => 'Artigo 12.º do CIVA',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tax_type' => 'IVA',
                'tax_code' => 'EXC',
                'description' => 'Regime de exclusão de IVA',
                'tax_percentage' => 0.00,
                'country' => 'AO',
                'is_active' => true,
                'exemption_reason' => 'Artigo 2.º do CIVA — Regime de Exclusão',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tax_type' => 'IVA',
                'tax_code' => 'OUT',
                'description' => 'Outra taxa / caso especial',
                'tax_percentage' => 0.00,
                'country' => 'AO',
                'is_active' => true,
                'exemption_reason' => 'Taxa especial — verificar legislação aplicável',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
