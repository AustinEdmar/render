<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->enum('type', [
                'sale',       // Venda
                'refund',     // Devolução
                'purchase',   // Compra/entrada
                'adjustment', // Ajuste manual
                'loss',       // Quebra/perda
            ]);

            // Positivo = entrada | Negativo = saída
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');

            // FIX: colunas para morphTo() — o model tinha morphTo() mas
            // os campos não existiam na migration.
            // Permite referenciar OrderItem ou RefundItem como origem do movimento.
            $table->nullableMorphs('reference'); // reference_type + reference_id

            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
