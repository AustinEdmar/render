<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeSubmission;
use App\Models\Invoice;
use App\Services\Agt\FeInvoiceService;
use Illuminate\Http\JsonResponse;

class FeInvoiceController extends Controller
{
    public function __construct(private FeInvoiceService $feInvoiceService)
    {
    }

    // ═══════════════════════════════════════════════════════
    // 📤 SUBMETER FACTURA JÁ EMITIDA À AGT
    // POST /api/fe/invoices/{invoice}/submit
    // Chame isto logo a seguir a OrderController::close() ter
    // criado o Invoice, ou de um job em fila (recomendado).
    // ═══════════════════════════════════════════════════════
    public function submit(Invoice $invoice): JsonResponse
    {
        if ($invoice->fe_status && $invoice->fe_status !== 'not_sent') {
            return response()->json([
                'message' => 'Esta factura já foi submetida à AGT.',
                'fe_status' => $invoice->fe_status,
            ], 409);
        }

        try {
            $submission = $this->feInvoiceService->submit($invoice);

            return response()->json([
                'success' => true,
                'request_id' => $submission->request_id,
                'fe_status' => $submission->fe_status,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 🔄 CONSULTAR ESTADO NA AGT (polling manual)
    // GET /api/fe/invoices/{invoice}/status
    // ═══════════════════════════════════════════════════════
    public function status(Invoice $invoice): JsonResponse
    {
        $submission = FeSubmission::where('invoice_id', $invoice->id)
            ->latest()
            ->firstOrFail();

        if ($submission->fe_status === 'pending') {
            $this->feInvoiceService->pollStatus($submission);
            $submission->refresh();
        }

        return response()->json([
            'fe_status' => $submission->fe_status,
            'request_id' => $submission->request_id,
            'error_list' => $submission->error_list,
        ]);
    }
}