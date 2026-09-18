<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;

/**
 * Geração do PDF da factura certificada AGT Angola
 *
 * Requer: composer require barryvdh/laravel-dompdf
 * O layout respeita os campos obrigatórios das facturas angolanas:
 *   — Cabeçalho com dados da empresa e certificado AGT
 *   — Dados do cliente com NIF
 *   — Tabela de linhas com IVA por linha
 *   — Rodapé com resumo fiscal por taxa
 *   — QR Code e hash_control visíveis
 *   — Menção ao software certificador
 */
class InvoicePdfService
{
    private Company $company;
    private QrCodeService $qrService;

    public function __construct(QrCodeService $qrService)
    {
        $this->qrService = $qrService;
        $this->company = Company::firstOrFail();
    }

    // ═══════════════════════════════════════════════════════
    // 📄 GERA O PDF E DEVOLVE O CONTEÚDO BINÁRIO
    // ═══════════════════════════════════════════════════════
    public function generate(Invoice $invoice): string
    {
        $invoice->loadMissing([
            'customer',
            'user:id,name',
            'items',
            'taxSummaries',
            'order.payments',
            'creditNote:id,invoice_number',
        ]);

        // Gera QR code se ainda não existir
        if (!$invoice->qr_code_data) {
            $this->qrService->generate($invoice);
            $invoice->refresh();
        }

        $qrImage = $this->qrService->generateImage($invoice);
        $html = $this->buildHtml($invoice, $qrImage);

        // Usa DomPDF (barryvdh/laravel-dompdf)
        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    // ═══════════════════════════════════════════════════════
    // 🖥️ CONSTRÓI O HTML DA FACTURA
    // ═══════════════════════════════════════════════════════
    private function buildHtml(Invoice $invoice, string $qrImage): string
    {
        $c = $this->company;
        $payment = $invoice->order?->payments?->first();

        $methodLabel = match ($payment?->method_payment) {
            'cash' => 'Numerário',
            'card' => 'Cartão (TPA)',
            'multicaixa' => 'Multicaixa Express',
            'QrCode' => 'QR Code',
            'BankTransfer' => 'Transferência Bancária',
            default => '—',
        };

        $isCredit = $invoice->document_type === 'NC';
        $docLabel = $this->documentLabel($invoice->document_type);
        $statusNote = match ($invoice->status) {
            'cancelled', 'credited' => '<div style="color:red;font-weight:bold;text-align:center;font-size:20px;border:2px solid red;padding:5px;margin:10px 0;">ANULADO</div>',
            default => '',
        };

        // ── Linhas de itens ────────────────────────────────
        $itemRows = '';
        foreach ($invoice->items as $item) {
            $itemRows .= sprintf('
                <tr>
                    <td>%s</td>
                    <td style="text-align:center">%s</td>
                    <td style="text-align:right">%s</td>
                    <td style="text-align:center">%s%%</td>
                    <td style="text-align:right">%s</td>
                    <td style="text-align:right">%s</td>
                </tr>',
                e($item->description) . ($item->product_code ? ' <small>(' . e($item->product_code) . ')</small>' : ''),
                number_format((float) $item->quantity, 2, ',', '.'),
                number_format((float) $item->unit_price, 2, ',', '.'),
                (int) $item->tax_rate,
                number_format((float) $item->net_amount, 2, ',', '.'),
                number_format((float) $item->gross_amount, 2, ',', '.')
            );
        }

        // ── Resumo fiscal (rodapé obrigatório AGT) ─────────
        $taxRows = '';
        foreach ($invoice->taxSummaries as $summary) {
            $taxRows .= sprintf('
                <tr>
                    <td>%s (%s%%)</td>
                    <td style="text-align:right">%s %s</td>
                    <td style="text-align:right">%s %s</td>
                </tr>',
                e($summary->tax_code),
                number_format((float) $summary->tax_rate, 0),
                number_format((float) $summary->taxable_amount, 2, ',', '.'),
                $invoice->currency,
                number_format((float) $summary->tax_amount, 2, ',', '.'),
                $invoice->currency
            );
        }

        ob_start(); ?>
        <!DOCTYPE html>
        <html lang="pt">

        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: DejaVu Sans, Arial, sans-serif;
                    font-size: 9pt;
                    color: #222;
                    margin: 0;
                    padding: 20px;
                }

                h1 {
                    font-size: 13pt;
                    margin: 0;
                }

                h2 {
                    font-size: 10pt;
                    margin: 4px 0;
                }

                .header {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 10px;
                    border-bottom: 2px solid #333;
                    padding-bottom: 8px;
                }

                .company {
                    width: 55%;
                }

                .doc-info {
                    width: 42%;
                    text-align: right;
                }

                .doc-type {
                    font-size: 16pt;
                    font-weight: bold;
                    color: #1a1a2e;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 8px 0;
                }

                th {
                    background: #1a1a2e;
                    color: white;
                    padding: 5px;
                    font-size: 8pt;
                }

                td {
                    padding: 4px 5px;
                    border-bottom: 1px solid #eee;
                    font-size: 8pt;
                }

                .totals td {
                    border: none;
                    font-weight: bold;
                }

                .tax-table {
                    background: #f9f9f9;
                }

                .footer {
                    margin-top: 15px;
                    border-top: 1px solid #ccc;
                    padding-top: 8px;
                    font-size: 7.5pt;
                    color: #555;
                }

                .qr-section {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                    margin-top: 10px;
                }

                .cert-note {
                    font-size: 7pt;
                    color: #666;
                    max-width: 65%;
                }

                .hash-box {
                    font-family: monospace;
                    font-size: 7pt;
                    background: #f0f0f0;
                    padding: 4px;
                    border: 1px solid #ccc;
                    word-break: break-all;
                }
            </style>
        </head>

        <body>

            <!-- ── CABEÇALHO ──────────────────────────────────── -->
            <div class="header">
                <div class="company">
                    <?php if ($c->logo_path): ?>
                        <img src="<?= public_path('storage/' . $c->logo_path) ?>" height="50" style="margin-bottom:5px"><br>
                    <?php endif; ?>
                    <h1>
                        <?= e($c->name) ?>
                    </h1>
                    <?php if ($c->trade_name): ?>
                        <div>
                            <?= e($c->trade_name) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <?= e($c->address) ?>,
                        <?= e($c->city) ?>
                        <?= $c->province ? ', ' . e($c->province) : '' ?>
                    </div>
                    <div>NIF:
                        <?= e($c->nif) ?>
                    </div>
                    <?php if ($c->phone): ?>
                        <div>Tel:
                            <?= e($c->phone) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($c->email): ?>
                        <div>
                            <?= e($c->email) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="doc-info">
                    <div class="doc-type">
                        <?= e($docLabel) ?>
                    </div>
                    <div><strong>
                            <?= e($invoice->invoice_number) ?>
                        </strong></div>
                    <div>Data:
                        <?= $invoice->issued_at->format('d/m/Y H:i') ?>
                    </div>
                    <div>Entrega:
                        <?= $invoice->delivered_at->format('d/m/Y') ?>
                    </div>
                    <div>Operador:
                        <?= e($invoice->user?->name ?? '—') ?>
                    </div>
                    <?php if ($invoice->creditNote): ?>
                        <div style="color:red">Anulada por:
                            <?= e($invoice->creditNote->invoice_number) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?= $statusNote ?>

            <?php if ($isCredit && $invoice->creditNote): ?>
                <div style="background:#fff3cd;padding:6px;margin-bottom:8px;font-size:8pt;">
                    ⚠️ Esta Nota de Crédito anula a factura <strong>
                        <?= e($invoice->creditNote->invoice_number) ?>
                    </strong>
                </div>
            <?php endif; ?>

            <!-- ── DADOS DO CLIENTE ───────────────────────────── -->
            <table style="margin-bottom:8px;">
                <tr>
                    <th colspan="2" style="text-align:left">DADOS DO CLIENTE / ADQUIRENTE</th>
                </tr>
                <tr>
                    <td width="50%"><strong>Nome:</strong>
                        <?= e($invoice->customer?->name ?? 'Consumidor Final') ?>
                    </td>
                    <td><strong>NIF:</strong>
                        <?= e($invoice->customer?->tax_number ?? '999999999') ?>
                    </td>
                </tr>
                <?php if ($invoice->customer?->address): ?>
                    <tr>
                        <td colspan="2"><strong>Morada:</strong>
                            <?= e($invoice->customer->address) ?>
                            <?= $invoice->customer->city ? ', ' . e($invoice->customer->city) : '' ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>

            <!-- ── LINHAS DE ARTIGOS ──────────────────────────── -->
            <table>
                <thead>
                    <tr>
                        <th style="text-align:left;width:40%">Descrição</th>
                        <th style="text-align:center;width:8%">Qtd</th>
                        <th style="text-align:right;width:12%">P. Unit.</th>
                        <th style="text-align:center;width:8%">IVA%</th>
                        <th style="text-align:right;width:14%">Incidência</th>
                        <th style="text-align:right;width:14%">Total c/IVA</th>
                    </tr>
                </thead>
                <tbody>
                    <?= $itemRows ?>
                </tbody>
            </table>

            <!-- ── TOTAIS ─────────────────────────────────────── -->
            <table class="totals" style="margin-top:4px;width:50%;margin-left:50%;">
                <tr>
                    <td>Subtotal (s/ IVA):</td>
                    <td style="text-align:right">
                        <?= number_format((float) $invoice->taxable_amount, 2, ',', '.') ?>
                        <?= e($invoice->currency) ?>
                    </td>
                </tr>
                <?php if ((float) $invoice->discount_amount > 0): ?>
                    <tr>
                        <td>Desconto:</td>
                        <td style="text-align:right">-
                            <?= number_format((float) $invoice->discount_amount, 2, ',', '.') ?>
                            <?= e($invoice->currency) ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td>IVA:</td>
                    <td style="text-align:right">
                        <?= number_format((float) $invoice->tax_amount, 2, ',', '.') ?>
                        <?= e($invoice->currency) ?>
                    </td>
                </tr>
                <tr style="font-size:11pt;border-top:2px solid #333;">
                    <td><strong>TOTAL:</strong></td>
                    <td style="text-align:right"><strong>
                            <?= number_format((float) $invoice->total_amount, 2, ',', '.') ?>
                            <?= e($invoice->currency) ?>
                        </strong></td>
                </tr>
                <?php if ($payment): ?>
                    <tr>
                        <td>Forma de pagamento:</td>
                        <td style="text-align:right">
                            <?= e($methodLabel) ?>
                        </td>
                    </tr>
                    <?php if ($payment->received && $payment->method_payment === 'cash'): ?>
                        <tr>
                            <td>Recebido:</td>
                            <td style="text-align:right">
                                <?= number_format((float) $payment->received, 2, ',', '.') ?>
                                <?= e($invoice->currency) ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Troco:</td>
                            <td style="text-align:right">
                                <?= number_format((float) $payment->change, 2, ',', '.') ?>
                                <?= e($invoice->currency) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>
            </table>

            <!-- ── RESUMO FISCAL POR TAXA (obrigatório AGT) ──── -->
            <div style="margin-top:10px;">
                <strong style="font-size:8pt;">RESUMO DE IVA</strong>
                <table class="tax-table" style="margin-top:3px;">
                    <thead>
                        <tr>
                            <th style="text-align:left">Taxa</th>
                            <th style="text-align:right">Incidência</th>
                            <th style="text-align:right">Valor IVA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?= $taxRows ?>
                    </tbody>
                </table>
            </div>

            <!-- ── RODAPÉ AGT OBRIGATÓRIO ────────────────────── -->
            <div class="footer">
                <div style="margin-bottom:4px;">
                    Os bens/serviços foram colocados à disposição do adquirente em <strong>
                        <?= $invoice->delivered_at->format('d/m/Y') ?>
                    </strong>
                </div>

                <div class="qr-section">
                    <div>
                        <!-- QR Code -->
                        <?php if (str_starts_with($qrImage, 'http')): ?>
                            <img src="<?= $qrImage ?>" width="80" height="80">
                        <?php else: ?>
                            <img src="data:image/png;base64,<?= $qrImage ?>" width="80" height="80">
                        <?php endif; ?>

                        <div style="font-size:6.5pt;margin-top:2px;">Leia o QR Code<br>para validar</div>
                    </div>

                    <div style="flex:1;margin:0 10px;">
                        <!-- Hash control — obrigatório AGT (4 caracteres visíveis) -->
                        <div class="hash-box">
                            Chave:
                            <?= substr($invoice->hash ?? '', 0, 4) ?>
                        </div>

                        <!-- Certificação AGT -->
                        <div class="cert-note" style="margin-top:6px;">
                            <?php if ($c->certificate_number): ?>
                                Processado por computador —
                                <?= e($c->certificate_issuer ?? $c->name) ?><br>
                                Certificado AGT n.º
                                <?= e($c->certificate_number) ?>
                                <?= $c->software_version ? ' — v' . e($c->software_version) : '' ?>
                            <?php else: ?>
                                Processado por computador
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($c->website): ?>
                    <div style="text-align:center;margin-top:6px;font-size:7.5pt;">
                        <?= e($c->website) ?>
                    </div>
                <?php endif; ?>
                <div style="text-align:center;font-size:7pt;color:#999;margin-top:4px;">
                    Documento emitido electronicamente — Válido sem assinatura
                </div>
            </div>

        </body>

        </html>
        <?php
        return ob_get_clean();
    }

    private function documentLabel(string $type): string
    {
        return match ($type) {
            'FT' => 'FACTURA',
            'FR' => 'FACTURA-RECIBO',
            'NC' => 'NOTA DE CRÉDITO',
            'ND' => 'NOTA DE DÉBITO',
            'VD' => 'VENDA A DINHEIRO',
            'RC' => 'RECIBO',
            default => $type,
        };
    }
}