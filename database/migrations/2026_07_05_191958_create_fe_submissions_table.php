<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fe_submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            $table->string('submission_uuid')->unique();
            $table->string('request_id')->nullable()->index(); // devolvido pela AGT

            $table->string('endpoint', 60)->default('registarFactura');
            $table->json('request_payload');
            $table->json('response_payload')->nullable();

            // Estado do lado da AGT: pending | valid | invalid | error
            $table->string('fe_status', 20)->default('pending');
            $table->unsignedTinyInteger('poll_attempts')->default(0);
            $table->timestamp('last_polled_at')->nullable();

            $table->json('error_list')->nullable();

            $table->timestamps();
        });

        // Campos na invoice para reflectir o estado final da AGT
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('fe_status', 20)->default('not_sent')->after('status');
            // not_sent | pending | valid | invalid
            $table->string('fe_request_id')->nullable()->after('fe_status');
            $table->string('fe_series_code', 60)->nullable()->after('fe_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['fe_status', 'fe_request_id', 'fe_series_code']);
        });
        Schema::dropIfExists('fe_submissions');
    }
};
