<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\SaftExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SaftController extends Controller
{
    public function __construct(private SaftExportService $saft)
    {
    }

    // ═══════════════════════════════════════════════════════
    // 📥 DOWNLOAD DO FICHEIRO SAF-T XML
    // GET /api/saft/export?year=2026&month=6
    // month é opcional — sem ele exporta o ano completo
    // ═══════════════════════════════════════════════════════
    public function export(Request $request): Response|JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $year = (int) $request->year;
        $month = (int) $request->input('month', 0);

        // FIX: cancelled excluído — SAF-T AO não inclui documentos anulados
        // nos totais; apenas issued e credited entram no ficheiro
        $query = Invoice::whereYear('issued_at', $year)
            ->whereIn('status', ['issued', 'credited']);

        if ($month > 0) {
            $query->whereMonth('issued_at', $month);
        }

        if (!$query->exists()) {
            return response()->json([
                'message' => 'Não existem facturas para o período seleccionado.',
            ], 404);
        }

        try {
            $xml = $this->saft->generate($year, $month);

            // Nome do ficheiro conforme convenção AGT:
            // SAFT-AO_NIF_YYYY_MM.xml  ou  SAFT-AO_NIF_YYYY.xml
            $company = Company::first();
            $nif = $company?->nif ?? '000000000';
            $suffix = $month > 0
                ? sprintf('%04d_%02d', $year, $month)
                : (string) $year;

            $filename = "SAFT-AO_{$nif}_{$suffix}.xml";

            // FIX: AuditLog não recebe model não-persistido —
            // se não houver empresa registada, usa array simples de contexto
            if ($company) {
                AuditLog::record(
                    action: 'saft_exported',
                    model: $company,
                    oldValues: [],
                    newValues: ['year' => $year, 'month' => $month, 'file' => $filename]
                );
            }

            return response($xml, 200, [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao gerar SAF-T.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 📊 VALIDAÇÃO PRÉVIA — resume o que será exportado
    // Útil para verificar antes de submeter à AGT
    // GET /api/saft/preview?year=2026&month=6
    // ═══════════════════════════════════════════════════════
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $year = (int) $request->year;
        $month = (int) $request->input('month', 0);

        $query = Invoice::whereYear('issued_at', $year)
            ->whereIn('status', ['issued', 'credited']);

        if ($month > 0) {
            $query->whereMonth('issued_at', $month);
        }

        // FIX: aspas simples em strings SQL — compatível com MySQL e SQLite
        $summary = (clone $query)
            ->selectRaw("
                COUNT(*)                                                                    as total_documents,
                SUM(CASE WHEN status = 'issued'   THEN 1 ELSE 0 END)                      as issued,
                SUM(CASE WHEN status = 'credited' THEN 1 ELSE 0 END)                      as credited,
                SUM(CASE WHEN document_type NOT IN ('NC','ND') THEN total_amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN document_type NOT IN ('NC','ND') THEN tax_amount   ELSE 0 END) as total_iva,
                SUM(CASE WHEN document_type IN ('NC')          THEN ABS(total_amount) ELSE 0 END) as total_credit_notes
            ")->first();

        $byType = (clone $query)
            ->selectRaw("document_type, COUNT(*) as count, SUM(total_amount) as total")
            ->groupBy('document_type')
            ->get();

        // Verifica integridade da sequência e cadeia de hash
        $hashBreaks = $this->checkSequenceIntegrity($year, $month);

        return response()->json([
            'period' => $month > 0 ? sprintf('%04d-%02d', $year, $month) : (string) $year,
            'summary' => $summary,
            'by_type' => $byType,
            'hash_breaks' => $hashBreaks,
            'ready' => count($hashBreaks) === 0,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // Verifica sequência e facturas sem hash
    // Nota: não reconstrói o hash criptográfico (RSA) —
    // essa validação é feita pela AGT com a chave pública
    // do certificado. Aqui verificamos apenas gaps e nulls.
    // ═══════════════════════════════════════════════════════
    private function checkSequenceIntegrity(int $year, int $month): array
    {
        $breaks = [];

        $types = Invoice::whereYear('issued_at', $year)
            ->when($month > 0, fn($q) => $q->whereMonth('issued_at', $month))
            ->whereIn('status', ['issued', 'credited'])
            ->distinct()
            ->pluck('document_type');

        foreach ($types as $type) {
            $invoices = Invoice::where('document_type', $type)
                ->whereYear('issued_at', $year)
                ->when($month > 0, fn($q) => $q->whereMonth('issued_at', $month))
                ->whereIn('status', ['issued', 'credited'])
                ->orderBy('sequence_number')
                ->get(['id', 'invoice_number', 'sequence_number', 'hash']);

            // Verifica gaps na numeração
            for ($i = 1; $i < $invoices->count(); $i++) {
                $expected = $invoices[$i - 1]->sequence_number + 1;
                $actual = $invoices[$i]->sequence_number;

                if ($actual !== $expected) {
                    $breaks[] = [
                        'type' => $type,
                        'issue' => "Gap na numeração entre #{$invoices[$i - 1]->sequence_number} e #{$actual}",
                        'invoice' => $invoices[$i]->invoice_number,
                    ];
                }
            }

            // Verifica facturas sem hash
            foreach ($invoices->whereNull('hash') as $inv) {
                $breaks[] = [
                    'type' => $type,
                    'issue' => 'Factura sem hash',
                    'invoice' => $inv->invoice_number,
                ];
            }
        }

        return $breaks;
    }
}







