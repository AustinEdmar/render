<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('initial_amount', 10, 2);
            $table->decimal('expected_cash_amount', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable();
            $table->decimal('gross_sales', 10, 2)->default(0);
            $table->decimal('refund_total', 10, 2)->default(0);
            $table->decimal('net_sales', 10, 2)->default(0);
            $table->decimal('final_cash_amount', 10, 2)->nullable();

            $table->enum('status', ['open', 'closed'])->default('open');

            // Identifica o terminal/caixa física — aparece na série da factura
            // Ex: "CAIXA1", "A", "13.M42L"
            $table->string('terminal_id', 20)->default('A');

            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
