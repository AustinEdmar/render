<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();

            // FIX: user_id existia no model e no controller mas faltava na migration
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Valor recebido e troco (apenas para pagamento em dinheiro)
            $table->decimal('received', 10, 2)->nullable();
            $table->decimal('change', 10, 2)->nullable();

            $table->enum('status', [
                'pending',
                'paid',
                'refunded',
                'partial_refund',
            ])->default('pending');

            // Método de pagamento — confirmado nas facturas reais angolanas
            $table->enum('method_payment', [
                'cash',          // Numerário
                'card',          // Cartão (TPA)
                'QrCode',        // QR Code
                'BankTransfer',  // Transferência bancária
                'multicaixa',    // Multicaixa Express / KEVE
            ]);

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('AOA');

            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
