<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_settlements', function (Blueprint $table) {
            $table->id();

            // Recibo (RC/RG) que liquida a factura
            $table->foreignId('receipt_invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            // Factura (FT/FR/TV) que está a ser liquidada
            $table->foreignId('settled_invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            $table->decimal('amount_paid', 12, 2);

            $table->timestamps();

            // Evita duplicar a mesma liquidação recibo->factura
            $table->unique(['receipt_invoice_id', 'settled_invoice_id']);

            $table->index('receipt_invoice_id');
            $table->index('settled_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_settlements');
    }
};
