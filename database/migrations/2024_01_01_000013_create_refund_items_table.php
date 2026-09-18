<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FIX: esta tabela estava em falta — era criada pelo controller (RefundItem::create)
// mas não tinha migration nem model correspondente.
// Regista o detalhe dos itens reembolsados em reembolsos parciais.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('refund_id')->constrained('refunds')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            // Snapshot do item no momento do reembolso
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);

            // IVA snapshot
            $table->unsignedSmallInteger('iva_rate')->default(0);
            $table->decimal('iva_amount', 12, 2)->default(0);

            $table->decimal('subtotal', 12, 2);       // Sem IVA
            $table->decimal('total_with_iva', 12, 2); // Com IVA

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_items');
    }
};
