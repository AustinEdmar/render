<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * php artisan agt:ping
 *
 * Testa o cliente HTTP do próprio Laravel (Guzzle) contra a AGT,
 * sem passar por Signature/Series/Invoice — isola se o problema é
 * mesmo o Http facade / Guzzle, ou se é a lógica de negócio.
 */
class AgtPing extends Command
{
    protected $signature = 'agt:ping';
    protected $description = 'Testa conectividade crua via Http facade do Laravel até a AGT';

    public function handle(): int
    {
        $url = config('agt.endpoints.' . config('agt.env')) . '/solicitarSerie';
        $this->info("A testar: {$url}");
        $this->info('Timeout configurado: ' . config('agt.timeout') . 's');

        $start = microtime(true);

        try {
            $response = Http::timeout(config('agt.timeout'))
                ->acceptJson()
                ->post($url, ['ping' => true]);

            $elapsed = round(microtime(true) - $start, 2);

            $this->info("✅ Respondeu em {$elapsed}s — HTTP {$response->status()}");
            $this->line('Body: ' . $response->body());

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $elapsed = round(microtime(true) - $start, 2);
            $this->error("❌ Falhou depois de {$elapsed}s: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
