<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->enum('type', [
                'inflow',  // Reforço de caixa
                'outflow', // Sangria de caixa
            ]);

            $table->decimal('amount', 10, 2);

            // FIX: currency existia na migration original e no controller
            // mas estava em falta no $fillable do model — agora corrigido em ambos
            $table->string('currency', 3)->default('AOA');

            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
