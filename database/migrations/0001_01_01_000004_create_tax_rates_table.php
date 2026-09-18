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
            // CORRIGIDO: tax_code sozinho já não é único — passa a existir
            // mais que uma linha com o mesmo código (ex: RED a 5% e a 7%),
            // distinguidas pela percentagem. Ver unique composto no fim.
            $table->string('tax_code', 10); // NOR, INT, RED, ISE, OUT (enum fixa da AGT)

            $table->string('description');
            $table->decimal('tax_percentage', 8, 2)->default(0);
            $table->string('country', 3)->default('AO');
            $table->boolean('is_active')->default(true);

            // Motivo de isenção — obrigatório no SAF-T quando taxa = 0
            $table->string('exemption_reason')->nullable();

            $table->timestamps();

            // CORRIGIDO: unicidade passa a ser por código + percentagem,
            // não só por código — permite RED/5% e RED/7% coexistirem.
            $table->unique(['tax_code', 'tax_percentage']);
        });

        // Seed com as taxas de IVA vigentes em Angola (Lei 17/19 — CIVA Angola)
        //
        // CORRIGIDO: tax_code só aceita os 5 valores da enumeração oficial
        // da AGT para taxType=IVA (confirmado na doc registarFactura):
        //   NOR, INT, RED, ISE, OUT
        // "RED5", "RED7" e "EXC" NÃO existem nessa enumeração — a AGT
        // rejeita com erro E18 ("combinação não permitida de campos").
        // A percentagem já vai no seu próprio campo (tax_percentage),
        // não deve ser colada ao código.
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
                // CORRIGIDO: era 'RED5'
                'tax_code' => 'RED',
                'description' => 'Taxa reduzida de IVA (5%)',
                'tax_percentage' => 5.00,
                'country' => 'AO',
                'is_active' => true,
                'exemption_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tax_type' => 'IVA',
                // CORRIGIDO: era 'RED7'
                'tax_code' => 'RED',
                'description' => 'Taxa reduzida de IVA (7%)',
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
                // TODO: texto livre, não é o que a AGT exige. O campo
                // taxExemptionCode do payload (Anexo 6.4 da AGT) tem de
                // ser um código curto do catálogo oficial — ainda por
                // confirmar. 'exemption_reason' aqui fica só para exibição
                // interna/impressão, não é enviado directamente à AGT.
                'exemption_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};