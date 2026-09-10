<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // Snapshots obrigatórios AGT — o produto pode mudar; a venda não
            $table->string('product_name');            // SAF-T Description
            $table->string('product_code')->nullable(); // SAF-T ProductCode
            $table->string('unit', 20)->default('UN');  // SAF-T UnitOfMeasure

            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);       // Preço unitário sem IVA

            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);

            // IVA — snapshot completo no momento da venda (SAF-T por linha)
            // FIX: iva_rate agora é smallInteger para suportar valores como 14, 5, 0
            $table->unsignedSmallInteger('iva_rate')->default(0); // Ex: 14, 5, 0
            $table->string('tax_code', 10)->default('NOR');       // NOR, RED, ISE, EXC, OUT
            $table->string('tax_exemption_reason')->nullable();   // Obrigatório quando taxa = 0

            $table->decimal('iva_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);          // Sem IVA
            $table->decimal('total_with_iva', 12, 2);    // Com IVA

            $table->enum('status', ['active', 'cancelled', 'refunded'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
