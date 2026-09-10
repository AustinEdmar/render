<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->foreignId('order_item_id')
                ->nullable()
                ->constrained('order_items')
                ->nullOnDelete();

            // Snapshots obrigatórios SAF-T — imutáveis após emissão
            $table->string('description');              // Nome do produto/serviço
            $table->string('product_code')->nullable(); // SAF-T ProductCode
            $table->string('unit', 20)->default('UN'); // SAF-T UnitOfMeasure

            // Quantidades e preços
            $table->decimal('quantity', 10, 3);         // 3 casas para KG, L, etc.
            $table->decimal('unit_price', 12, 2);       // Preço unitário SEM IVA

            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);

            // IVA por linha — SAF-T exige TaxType, TaxCode, TaxAmount, TaxBase por linha
            $table->unsignedSmallInteger('tax_rate');            // Ex: 14, 5, 0
            $table->string('tax_code', 10)->default('NOR');     // NOR, RED, ISE, EXC, OUT
            $table->string('tax_exemption_reason')->nullable(); // Obrigatório se taxa = 0

            $table->decimal('net_amount', 12, 2);   // Valor sem IVA (base tributável da linha)
            $table->decimal('tax_amount', 12, 2);   // Valor do IVA da linha
            $table->decimal('gross_amount', 12, 2); // Valor com IVA

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
