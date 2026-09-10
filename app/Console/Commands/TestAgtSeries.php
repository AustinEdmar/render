<?php

namespace App\Console\Commands;

use App\Services\Agt\FeSeriesService;
use Illuminate\Console\Command;

/**
 * php artisan agt:test-series FT
 *
 * Testa isoladamente o pedido de série à AGT (hml), sem precisar de um
 * Invoice real. Útil como PRIMEIRO teste de integração — se isto falhar,
 * o erro devolvido pela AGT (errorList) diz exactamente o que corrigir
 * antes de tentar registarFactura.
 */
class TestAgtSeries extends Command
{
    protected $signature = 'agt:test-series {documentType=FT}';
    protected $description = 'Testa solicitarSerie contra o ambiente de homologação da AGT';

    public function handle(FeSeriesService $service): int
    {
        $documentType = $this->argument('documentType');

        $this->info("A solicitar série de teste para documentType={$documentType}...");

        try {
            $documentNo = $service->nextDocumentNo($documentType);
            $this->info("✅ Sucesso! Próximo documentNo disponível: {$documentNo}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Falhou: ' . $e->getMessage());
            $this->line('Verifique storage/logs/laravel.log e a tabela fe_series (response_payload) para o errorList completo.');
            return self::FAILURE;
        }
    }
}