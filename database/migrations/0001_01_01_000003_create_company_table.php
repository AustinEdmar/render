<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company', function (Blueprint $table) {
            $table->id();



            // Identificação fiscal
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('nif', 20)->unique();
            $table->string('cae', 10)->nullable();

            // Morada
            $table->string('address');
            $table->string('city');
            $table->string('province')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 3)->default('AO');

            // Contactos
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Identidade visual
            $table->string('logo_path')->nullable();

            // Certificação AGT
            $table->string('software_name')->nullable();
            $table->string('certificate_number', 50)->nullable();
            $table->string('certificate_issuer')->nullable();
            $table->string('software_version', 20)->nullable();

            // Moeda padrão
            $table->string('currency', 3)->default('AOA');

            // Regime de IVA: normal | exclusion | exempt
            $table->enum('vat_regime', ['normal', 'exclusion', 'exempt'])->default('normal');

            // Credenciais Basic Auth da AGT (guardar password com Crypt::encryptString)
            $table->string('agt_username')->nullable()->after('certificate_issuer');
            $table->text('agt_password_encrypted')->nullable()->after('agt_username');

            // Chave privada do CONTRIBUINTE (assina jwsDocumentSignature / jwsSignature)
            $table->text('agt_private_key_path')->nullable()->after('agt_password_encrypted');
            $table->text('agt_public_key_path')->nullable()->after('agt_private_key_path');

            // Chave privada do SOFTWARE/produtor (assina jwsSoftwareSignature)
            // pode ser a mesma do contribuinte em fase de testes, mas em produção
            // pertence ao produtor de software certificado.
            $table->text('agt_software_private_key_path')->nullable()->after('agt_public_key_path');

            $table->unsignedTinyInteger('agt_signature_version')->default(1)->after('agt_software_private_key_path');
            $table->enum('agt_env', ['hml', 'prod'])->default('hml')->after('agt_signature_version');


            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company');
    }
};
