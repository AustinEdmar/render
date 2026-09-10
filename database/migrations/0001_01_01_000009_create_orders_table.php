<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            $table->enum('status', [
                'open',
                'closed',
                'refunded',
                'partial_refund',
                'canceled',
            ])->default('open');

            // Totais financeiros
            $table->decimal('subtotal', 12, 2)->default(0); // sem IVA
            $table->decimal('iva', 12, 2)->default(0);      // total de IVA
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);    // com IVA

            // FIX: campos removidos daqui — pertencem à tabela invoices, não à orders
            // invoice_series e invoice_number eram redundantes e causavam inconsistência

            // Flag: indica se já foi gerada factura para esta order
            $table->boolean('invoice_generated')->default(false);

            $table->text('notes')->nullable();

            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
