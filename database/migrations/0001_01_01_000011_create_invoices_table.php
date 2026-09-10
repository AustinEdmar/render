<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Relações
            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete(); // Nunca apagar order com factura
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete(); // Utilizador que emitiu
            $table->foreignId('shift_id')
                ->constrained('shifts')
                ->restrictOnDelete();

            // Tipo de documento — SAF-T InvoiceType Angola
            // FT=Factura | FR=Factura-Recibo | ND=Nota de Débito
            // NC=Nota de Crédito | VD=Venda a Dinheiro | RC=Recibo
            $table->string('document_type', 5); // FT, FR, ND, NC, VD, RC

            // Numeração sequencial obrigatória e sem gaps — AGT
            $table->string('series', 20)->default('A');
            $table->unsignedInteger('sequence_number');

            // FIX: formato corrigido para padrão AGT Angola: "FR A/2026/00001"
            $table->string('invoice_number', 60)->unique();

            // Valores fiscais
            $table->decimal('taxable_amount', 12, 2);   // Base tributável (sem IVA)
            $table->decimal('tax_amount', 12, 2);        // Total de IVA
            $table->decimal('total_amount', 12, 2);      // Total com IVA
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('AOA');

            // Status — facturas NUNCA são apagadas fisicamente
            $table->enum('status', [
                'draft',     // Rascunho (não entregue)
                'issued',    // Emitida e entregue
                'cancelled', // Anulada — registo obrigatório AGT
                'credited',  // Anulada com nota de crédito correspondente
            ])->default('draft');

            // Auto-referência: nota de crédito que anulou esta factura
            $table->unsignedBigInteger('credit_note_id')->nullable();

            // Datas obrigatórias AGT
            $table->timestamp('issued_at');
            $table->timestamp('due_at')->nullable();

            // OBRIGATÓRIO por lei angolana:
            // "Os bens/serviços foram colocados à disposição do adquirente em [data]"
            $table->timestamp('delivered_at');

            // Segurança e certificação AGT
            // Hash encadeado RSA — em produção usar chave privada RSA-1024 da empresa
            $table->string('hash', 200)->nullable();
            $table->string('hash_control', 10)->nullable(); // Posições: "1;11;21;31"

            // QR Code impresso na factura (obrigatório AGT)
            $table->text('qr_code_data')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes(); // Soft delete — NUNCA apagar fisicamente

            // Índices para performance nas queries SAF-T (exportação mensal)
            $table->index(['document_type', 'series', 'sequence_number']);
            $table->index('issued_at');
            $table->index('status');
        });

        // FK auto-referenciada (nota de crédito → factura original)
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('credit_note_id')
                ->references('id')
                ->on('invoices')
                ->nullOnDelete();
        });

        // FK de refunds → invoices (adicionada aqui pois invoices já existe)
        Schema::table('refunds', function (Blueprint $table) {
            $table->foreign('credit_note_invoice_id')
                ->references('id')
                ->on('invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['credit_note_invoice_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['credit_note_id']);
        });

        Schema::dropIfExists('invoices');
    }
};
