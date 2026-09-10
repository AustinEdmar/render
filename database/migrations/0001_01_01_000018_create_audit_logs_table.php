<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Registo de auditoria — obrigatório para certificação AGT
        // Regista todas as operações críticas: emissão, anulação, alterações
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Acção realizada
            $table->string('action', 30); // created|updated|cancelled|issued|deleted_attempt

            // Modelo afectado
            $table->string('model_type', 50); // Invoice|Order|Product|Customer|etc.
            $table->unsignedBigInteger('model_id');

            // Valores antes e depois (rastreabilidade completa)
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Contexto da operação
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('performed_at')->useCurrent();
            $table->timestamps();

            // Índices para consultas de auditoria
            $table->index(['model_type', 'model_id']);
            $table->index('performed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
