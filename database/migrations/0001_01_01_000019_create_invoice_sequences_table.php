<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Controlo de sequência por tipo de documento e série
        // CRÍTICO: garante numeração sem gaps (obrigatório AGT)
        // Usar SELECT ... FOR UPDATE ao incrementar para evitar race conditions
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 5); // FT, FR, NC, ND, VD, RC
            $table->string('series', 20);        // A, B, CAIXA1, 13.M42L…
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            // Uma sequência única por tipo + série + ano
            $table->unique(['document_type', 'series', 'year']);
        });

        // Seed com as sequências base para o ano corrente
        $year = now()->year;
        DB::table('invoice_sequences')->insert([
            ['document_type' => 'FR', 'series' => 'A', 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['document_type' => 'FT', 'series' => 'A', 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['document_type' => 'NC', 'series' => 'A', 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['document_type' => 'ND', 'series' => 'A', 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['document_type' => 'VD', 'series' => 'A', 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['document_type' => 'RC', 'series' => 'A', 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
