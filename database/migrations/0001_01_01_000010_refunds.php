<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            // Nota de crédito gerada para este reembolso (FK auto-referenciada à invoices)
            // Definida em invoice migration para respeitar ordem de criação
            $table->unsignedBigInteger('credit_note_invoice_id')->nullable();

            $table->decimal('amount', 10, 2);
            $table->text('reason')->nullable();
            $table->enum('type', ['full', 'partial']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
