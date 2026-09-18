<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Agt\FeInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Submete um Invoice já criado localmente ao endpoint registarFactura da AGT.
 * Corre em fila (config('agt.queue')) para não bloquear o pedido HTTP de
 * OrderController::close() — a AGT pode demorar/estar indisponível, e isso
 * não deve impedir o POS de fechar a venda.
 */
class SubmitInvoiceToAgt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public Invoice $invoice)
    {
        $this->onQueue(config('agt.queue', 'agt'));
    }

    public function handle(FeInvoiceService $service): void
    {
        try {
            $submission = $service->submit($this->invoice);

            // CORRIGIDO: nada disparava o polling automático — a factura
            // ficava presa em 'pending' para sempre a menos que alguém
            // corresse pollStatus() manualmente. Agora, se a AGT aceitou o
            // registo (fe_status === 'pending', com requestID), agenda o
            // primeiro PollFeSubmissionStatus, que se auto-reagenda até
            // 'valid'/'invalid' ou esgotar as tentativas.
            if ($submission->fe_status === 'pending' && $submission->request_id) {
                PollFeSubmissionStatus::dispatch($submission->id)
                    ->onQueue(config('agt.queue', 'agt'))
                    ->delay(now()->addSeconds(config('agt.poll_backoff_seconds')));
            }
        } catch (Throwable $e) {
            Log::error('Falha ao submeter Invoice à AGT', [
                'invoice_id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'error' => $e->getMessage(),
            ]);

            throw $e; // deixa o mecanismo de retry da queue actuar
        }
    }
}