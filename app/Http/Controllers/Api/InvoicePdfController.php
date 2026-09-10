<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InvoicePdfController extends Controller
{
    // ═══════════════════════════════════════════════════════
    // 📥 DOWNLOAD PDF DA FACTURA
    // GET /api/invoices/{id}/pdf
    // ═══════════════════════════════════════════════════════
    public function download($id)
    {
        $invoice = $this->loadInvoice($id);
        $company = Company::first();

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'company' => $company,
            'legal_text' => $this->legalText($invoice),
            'footer_text' => $this->footerText($company),
        ])
        ->setPaper([0, 0, 226.77, 800], 'portrait') // Largura de talão 80mm em pontos
        ->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'DejaVu Sans',
        ]);

        $filename = str_replace(['/', ' '], ['-', '-'], $invoice->invoice_number) . '.pdf';

        return $pdf->download($filename);
    }

    // ═══════════════════════════════════════════════════════
    // 👁️ VISUALIZAR PDF NO BROWSER (sem download)
    // GET /api/invoices/{id}/pdf/view
    // ═══════════════════════════════════════════════════════
    public function view($id)
    {
        $invoice = $this->loadInvoice($id);
        $company = Company::first();

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice'     => $invoice,
            'company'     => $company,
            'legal_text'  => $this->legalText($invoice),
            'footer_text' => $this->footerText($company),
        ])
        ->setPaper([0, 0, 226.77, 800], 'portrait')
        ->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'DejaVu Sans',
        ]);

        $filename = str_replace(['/', ' '], ['-', '-'], $invoice->invoice_number) . '.pdf';

        return $pdf->stream($filename);
    }

    // ═══════════════════════════════════════════════════════
    // 📷 GERAR QR CODE DA FACTURA
    // GET /api/invoices/{id}/qrcode
    // Retorna imagem PNG do QR Code (para exibir no frontend
    // ou imprimir no talão físico)
    // ═══════════════════════════════════════════════════════
    public function qrCode($id)
    {
        $invoice = $this->loadInvoice($id);

        // Conteúdo do QR Code — formato baseado nas facturas reais angolanas
        // Inclui os campos obrigatórios para validação AGT
        $qrData = $invoice->qr_code_data ?? $this->buildQrData($invoice);

        // Gera PNG do QR Code (200x200px)
        $qrImage = QrCode::format('png')
            ->size(200)
            ->errorCorrection('M')
            ->generate($qrData);

        return response($qrImage, 200, [
            'Content-Type'        => 'image/png',
            'Content-Disposition' => 'inline; filename="qrcode-' . $invoice->id . '.png"',
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // 🔧 HELPERS PRIVADOS
    // ═══════════════════════════════════════════════════════

    /**
     * Carrega a factura com todas as relações necessárias para o PDF.
     */
    private function loadInvoice(int|string $id): Invoice
    {
        return Invoice::with([
            'customer',
            'user:id,name',
            'shift:id,terminal_id',
            'items',
            'taxSummaries',
            'order.payments',
        ])->findOrFail($id);
    }

    /**
     * Texto legal obrigatório AGT:
     * "Os bens/serviços foram colocados à disposição do adquirente em [data]"
     * Confirmado nas duas facturas reais analisadas.
     */
    private function legalText(Invoice $invoice): string
    {
        return sprintf(
            'Os bens/serviços foram colocados à disposição do adquirente/prestados em %s.',
            $invoice->delivered_at->format('d/m/Y')
        );
    }

    /**
     * Rodapé obrigatório AGT:
     * "Processado por programa validado N.XX/AGT/XXXX | Nome Software"
     * Confirmado nas duas facturas reais analisadas.
     */
    private function footerText(?Company $company): string
    {
        if (!$company) {
            return 'Processado por programa validado';
        }

        return sprintf(
            'Processado por programa validado %s | %s',
            $company->certificate_number ?? '',
            $company->certificate_issuer ?? ''
        );
    }

    /**
     * Constrói os dados do QR Code caso ainda não estejam
     * guardados na factura. Formato observado nas facturas reais:
     * campos separados por * com info fiscal essencial.
     *
     * Campos típicos para Angola (baseado nas facturas observadas):
     *   NIF_EMITENTE * NIF_CLIENTE * DATA * TOTAL * HASH_PARCIAL
     */
    private function buildQrData(Invoice $invoice): string
    {
        $company = Company::first();

        $fields = [
            'A:' . ($company?->nif ?? ''),                               // NIF emitente
            'B:' . ($invoice->customer?->tax_number ?? '90000000'),      // NIF cliente
            'C:' . $invoice->issued_at->format('Y-m-d'),                 // Data emissão
            'D:' . $invoice->document_type,                              // Tipo documento
            'E:' . ($invoice->status === 'cancelled' ? 'A' : 'N'),      // Estado (A=Anulado, N=Normal)
            'F:' . $invoice->issued_at->format('Ymd'),                   // Data fiscal
            'G:' . $invoice->invoice_number,                             // Nº factura
            'H:' . ($invoice->hash_control ?? ''),                       // Controlo hash
            'I1:AO',                                                     // País
            'I7:' . number_format($invoice->taxable_amount, 2, '.', ''),// Base tributável
            'I8:' . number_format($invoice->tax_amount, 2, '.', ''),    // Total IVA
            'N:' . number_format($invoice->total_amount, 2, '.', ''),   // Total documento
            'O:' . number_format($invoice->total_amount, 2, '.', ''),   // Total pago
        ];

        $qrData = implode('*', $fields);

        // Guarda para não recalcular da próxima vez
        $invoice->updateQuietly(['qr_code_data' => $qrData]);

        return $qrData;
    }
}
