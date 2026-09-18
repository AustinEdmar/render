<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone', 30)->nullable();

            // NIF / Contribuinte — SAF-T CustomerTaxID
            $table->string('tax_number', 20)->nullable()->unique();

            // Tipo de cliente — SAF-T AccountID
            $table->enum('customer_type', ['individual', 'company'])->default('individual');

            // Morada completa
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 3)->default('AO');

            // Consumidor Final genérico (NIF: 999999999 em Angola)
            $table->boolean('is_final_consumer')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed: Consumidor Final genérico — obrigatório para vendas sem NIF
        // NIF 999999999 é o número padrão AGT para "consumidor final" em Angola
        DB::table('customers')->insert([
            'name' => 'Consumidor Final',
            'tax_number' => '999999999',
            'customer_type' => 'individual',
            'country' => 'AO',
            'is_final_consumer' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
