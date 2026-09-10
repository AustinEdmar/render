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
        Schema::create('fe_series', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 2);       // FT, FR, NC, ND...
            $table->string('series_year', 4);
            $table->string('establishment_number', 200)->default('SEDE');
            $table->char('contingency_indicator', 1)->default('N'); // N ou C

            $table->string('series_code', 60)->nullable();     // devolvido pela AGT
            $table->unsignedBigInteger('authorized_quantity')->default(0);
            $table->string('first_document_no', 60)->nullable();
            $table->string('last_document_no', 60)->nullable();
            $table->unsignedBigInteger('used_count')->default(0);

            $table->enum('status', ['requested', 'active', 'exhausted', 'rejected'])
                ->default('requested');

            $table->string('request_submission_uuid')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            $table->timestamps();

            $table->unique(
                ['document_type', 'series_year', 'establishment_number', 'contingency_indicator'],
                'fe_series_unique_scope'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fe_series');
    }
};
