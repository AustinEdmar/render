<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Identificação — SAF-T ProductCode + ProductDescription
            $table->string('name');
            $table->string('product_code')->nullable()->unique();
            $table->text('description')->nullable();

            // Preço base sem IVA (base tributável)
            $table->decimal('price', 12, 2);

            // Unidade de medida — SAF-T UnitOfMeasure
            $table->string('unit', 20)->default('UN');

            // IVA — FK para tax_rates (source of truth)
            // FIX: campo 'iva' (inteiro solto) foi REMOVIDO — usar tax_rate_id + relação
            $table->foreignId('tax_rate_id')
                ->nullable()
                ->constrained('tax_rates')
                ->nullOnDelete();

            // Motivo de isenção — obrigatório SAF-T quando taxa = 0
            $table->string('tax_exemption_reason')->nullable();

            // Stock
            $table->integer('stock')->default(0);
            $table->string('barcode')->nullable()->unique();

            // Classificação
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
