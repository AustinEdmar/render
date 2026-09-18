<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;

/**
 * QR Code AGT Angola
 *
 * Formato oficial confirmado nas facturas angolanas certificadas:
 * Campo:Valor separado por asterisco (*)
 *
 * Campos obrigatórios (por ordem):
 *  A — NIF do emitente
 *  B — NIF do adquirente
 *  C — País do adquirente
 *  D — Tipo de documento (FT, FR, NC, etc.)
 *  E — Estado do documento (N=Normal, A=Anulado)
 *  F — Data do documento (YYYYMMDD)
 *  G — Número único do documento
 *  H — ATCUD (hash_control)
 *  I1 — Código da taxa (NOR, RED, ISE, EXC)
 *  I2 — Base tributável por taxa
 *  I3 — Valor do IVA por taxa
 *  N  — Total do IVA
 *  O  — Total com IVA
 *  Q  — 4 caracteres do hash para impressão
 *  R  — Número do certificado AGT 
 */
class QrCodeService
{
    private Company $company;

    public function __construct()
    {
        $this->company = Company::firstOrFail();
    }

    // ═══════════════════════════════════════════════════════
    // 🔳 GERA O CONTEÚDO DO QR CODE E GUARDA NA FACTURA
    // ═══════════════════════════════════════════════════════
    public function generate(Invoice $invoice): string
    {
        $invoice->loadMissing(['customer', 'taxSummaries']);

        $fields = [];

        // A — NIF do emitente (empresa)
        $fields[] = 'A:' . $this->company->nif;

        // B — NIF do adquirente (cliente ou 999999999 para consumidor final)
        $fields[] = 'B:' . ($invoice->customer?->tax_number ?? '999999999');

        // C — País do adquirente
        $fields[] = 'C:' . ($invoice->customer?->country ?? 'AO');

        // D — Tipo de documento
        $fields[] = 'D:' . $invoice->document_type;

        // E — Estado: N=Normal, A=Anulado/Creditado
        $fields[] = 'E:' . (in_array($invoice->status, ['cancelled', 'credited']) ? 'A' : 'N');

        // F — Data do documento
        $fields[] = 'F:' . $invoice->issued_at->format('Ymd');

        // G — Número único do documento
        $fields[] = 'G:' . $invoice->invoice_number;

        // H — ATCUD (hash_control da AGT)
        $fields[] = 'H:' . ($invoice->hash_control ?? '0');

        // I1..I8 — Bases e IVA por taxa (uma linha por taxa)
        $taxIndex = 1;
        foreach ($invoice->taxSummaries as $summary) {
            $fields[] = 'I' . $taxIndex . ':' . $summary->tax_code;
            $taxIndex++;
            $fields[] = 'I' . $taxIndex . ':' . number_format((float) $summary->taxable_amount, 2, '.', '');
            $taxIndex++;
            $fields[] = 'I' . $taxIndex . ':' . number_format((float) $summary->tax_amount, 2, '.', '');
            $taxIndex++;
        }

        // N — Total do IVA
        $fields[] = 'N:' . number_format((float) $invoice->tax_amount, 2, '.', '');

        // O — Total com IVA (gross)
        $fields[] = 'O:' . number_format((float) $invoice->total_amount, 2, '.', '');

        // Q — Primeiros 4 caracteres do hash (para validação visual)
        $fields[] = 'Q:' . substr($invoice->hash ?? '', 0, 4);

        // R — Número do certificado AGT
        $fields[] = 'R:' . ($this->company->certificate_number ?? '');

        $qrContent = implode('*', $fields);

        // Persiste no campo qr_code_data da factura
        $invoice->updateQuietly(['qr_code_data' => $qrContent]);

        return $qrContent;
    }

    // ═══════════════════════════════════════════════════════
    // 🖼️ GERA A IMAGEM PNG DO QR CODE (base64)
    // Requer: composer require endroid/qr-code
    // ═══════════════════════════════════════════════════════
    public function generateImage(Invoice $invoice): string
    {
        $content = $invoice->qr_code_data ?? $this->generate($invoice);

        // Usa endroid/qr-code se disponível
        if (class_exists(\Endroid\QrCode\QrCode::class)) {
            $qrCode = \Endroid\QrCode\QrCode::create($content)
                ->setSize(200)
                ->setMargin(5);

            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCode);

            return base64_encode($result->getString());
        }

        // Fallback: URL para API pública de QR code (apenas para desenvolvimento)
        // Em produção usar sempre a biblioteca local
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($content);
    }
}