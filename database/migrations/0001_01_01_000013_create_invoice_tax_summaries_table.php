<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Resumo fiscal por taxa de IVA por factura — rodapé obrigatório AGT
        // Exemplo real:
        //   TAXA 5%  | Incidência: 923.81 | Valor IVA: 46.19
        //   TAXA 14% | Incidência: 350.88 | Valor IVA: 49.12
        Schema::create('invoice_tax_summaries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('tax_rate_id')
                ->nullable()
                ->constrained('tax_rates')
                ->nullOnDelete();

            $table->string('tax_code', 10);           // NOR, RED, ISE, EXC
            $table->decimal('tax_rate', 5, 2);        // Ex: 14.00, 5.00, 0.00
            $table->decimal('taxable_amount', 12, 2); // Incidência (base tributável desta taxa)
            $table->decimal('tax_amount', 12, 2);     // Valor do IVA desta taxa
            $table->string('tax_exemption_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_tax_summaries');
    }
};
