<?php

namespace App\Jobs;

use App\Models\FeSubmission;
use App\Services\Agt\FeInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Despache este job logo após submeter uma factura (fila 'agt'), com
 * ->delay(now()->addSeconds(config('agt.poll_backoff_seconds')))
 * Ele repete-se sozinho até a AGT devolver 'valid' ou 'invalid', ou até
 * atingir o limite de tentativas (config('agt.poll_max_attempts')).
 */
class PollFeSubmissionStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $submissionId)
    {
    }

    public function handle(FeInvoiceService $service): void
    {
        $submission = FeSubmission::find($this->submissionId);

        if (!$submission || $submission->fe_status !== 'pending') {
            return; // já resolvido ou removido
        }

        if ($submission->poll_attempts >= config('agt.poll_max_attempts')) {
            $submission->update(['fe_status' => 'invalid', 'error_list' => ['E99: limite de polling excedido']]);
            $submission->invoice->update(['fe_status' => 'invalid']);
            return;
        }

        $service->pollStatus($submission);
        $submission->refresh();

        if ($submission->fe_status === 'pending') {
            self::dispatch($this->submissionId)
                ->onQueue('agt')
                ->delay(now()->addSeconds(config('agt.poll_backoff_seconds')));
        }
    }
}