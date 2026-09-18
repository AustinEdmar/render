<?php

namespace App\Services\Agt;

use App\Models\Company;
use App\Models\FeSubmission;
use App\Models\Invoice;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Converte um Invoice (do seu sistema POS) para o payload registarFactura
 * da AGT e submete. Não substitui a emissão local — corre DEPOIS de
 * `OrderController::generateInvoice()` já ter criado o Invoice.
 *
 * ============================================================
 * CORREÇÕES APLICADAS NESTA VERSÃO (face à certificação C1):
 * ============================================================
 *
 * 1. buildSoftwareInfo() estava sem 'signatureVersion' no array enviado,
 *    mas o SignatureService::signSoftwareInfo() lê essa chave — gerava
 *    'Undefined array key' e assinatura potencialmente inconsistente
 *    com o payload enviado. CORRIGIDO: adicionado 'signatureVersion' => 1.
 *
 * 2. debitAmount/creditAmount estavam invertidos para documentos de
 *    venda (FT/FR/TV). A AGT confirmou no teste de certificação:
 *    "A soma dos valores a crédito para as diferentes linhas de um
 *    documento de facturação deve ser obrigatoriamente superior à soma
 *    dos valores a débito quando se trata de um documento diferente de
 *    nota de crédito." CORRIGIDO: venda (valor positivo) -> creditAmount;
 *    devolução/estorno (valor negativo) -> debitAmount.
 *
 * 3. NC e ND exigem 'referenceInfo' (reference, referenceItemLineNo,
 *    reason) POR LINHA, apontando para o documento base. Isto não
 *    existia. ADICIONADO — mas depende de campos no seu modelo Invoice/
 *    InvoiceItem que ainda não vi (ver TODOs abaixo).
 *
 * 4. Recibos (RC, RG) NÃO usam 'lines' — usam uma estrutura própria
 *    'paymentReceipt.sourceDocuments' (confirmado no teste C1 de RC/RG).
 *    ADICIONADO um método buildPaymentReceipt() e ramificação em submit()
 *    — mas depende de como o seu sistema regista que facturas um
 *    recibo está a liquidar (ver TODOs abaixo).
 *
 * ATENÇÃO — pontos que ainda precisa de confirmar/ajustar:
 *
 * A) `operationType` por linha é OBRIGATÓRIO na v1.2 e não existe ainda
 *    no seu InvoiceItem. Por omissão assumo "TB" (Transmissão de bens) —
 *    ajuste para "SG" (serviços) se vender serviços, ou adicione uma
 *    coluna própria se vender os dois.
 *
 * B) `customerCountry`/`customerTaxID`: o seu model Customer não tem
 *    (pelos controllers que vi) um campo de país ISO — assumo "AO" e
 *    tax_number, com fallback "999999999" para consumidor final.
 *
 * C) Para NC/ND: assumo que o Invoice tem os campos
 *    `reference_document_no` (documentNo da factura original) e
 *    `reference_reason` (motivo). Se não existirem, adicione-os via
 *    migration antes de emitir a primeira NC/ND real. O
 *    `referenceItemLineNo` assume-se "1" por omissão — ajuste se a sua
 *    NC/ND puder devolver linhas específicas de items diferentes.
 *
 * D) RESOLVIDO: Recibos (RC/RG) usam a relação Invoice::paidInvoices()
 *    (tabela pivot invoice_settlements) para saber que facturas estão a
 *    liquidar. Ver migration 2026_08_30_000000_create_invoice_settlements_table
 *    e os métodos adicionados a Invoice.php (paidInvoices/settledByReceipts).
 *    Ainda precisa de UI/lógica de negócio para: (1) o utilizador escolher
 *    que facturas um recibo está a pagar antes de chamar submit(), e
 *    (2) popular a pivot com os amount_paid correspondentes.
 */
class FeInvoiceService
{
    private const DOCUMENT_TYPE_MAP = [
        'FT' => 'FT',
        'FR' => 'FR',
        'NC' => 'NC',
        'ND' => 'ND',
        'VD' => 'TV', // convenção interna original da migration ("Venda a Dinheiro")
        'TV' => 'TV', // CORRIGIDO: OrderController::close() valida 'TV' directamente —
        // sem esta chave, um Invoice com document_type='TV' rebentava em
        // submit() com "Tipo de documento 'TV' sem mapeamento AGT."
        'RC' => 'RC',
        'RG' => 'RG', // Outros recibos — confirme se o seu sistema já distingue RC de RG
    ];

    /** Tipos de documento que usam a estrutura paymentReceipt em vez de lines. */
    private const RECEIPT_TYPES = ['RC', 'RG'];

    /** Tipos de documento que exigem referenceInfo por linha, apontando para o documento base. */
    private const REFERENCE_REQUIRED_TYPES = ['NC', 'ND'];

    public function __construct(
        private AgtClient $client,
        private SignatureService $signer,
        private FeSeriesService $seriesService,
    ) {
    }

    public function submit(Invoice $invoice): FeSubmission
    {
        $invoice->loadMissing(['items', 'taxSummaries', 'customer']);
        $company = Company::firstOrFail();
        $company->nif = $company->nif ?? config('agt.tax_registration_number');
        $company->agt_private_key_path = $company->agt_private_key_path ?? config('agt.private_key_path');
        $company->agt_software_private_key_path = $company->agt_software_private_key_path
            ?? config('agt.software_private_key_path')
            ?? config('agt.private_key_path');

        $documentType = self::DOCUMENT_TYPE_MAP[$invoice->document_type]
            ?? throw new RuntimeException("Tipo de documento '{$invoice->document_type}' sem mapeamento AGT.");

        // documentNo no formato exigido pela AGT (obtido via série já autorizada)
        $agtDocumentNo = $this->seriesService->nextDocumentNo($documentType);

        $document = in_array($documentType, self::RECEIPT_TYPES, true)
            ? $this->buildPaymentReceiptDocument($invoice, $documentType, $agtDocumentNo, $company)
            : $this->buildDocument($invoice, $documentType, $agtDocumentNo, $company);

        $submissionUUID = (string) Str::uuid();

        $payload = [
            'schemaVersion' => config('agt.schema_version'),
            'submissionUUID' => $submissionUUID,
            'taxRegistrationNumber' => $company->nif,
            'submissionTimeStamp' => now()->toIso8601String(),
            'softwareInfo' => $this->buildSoftwareInfo($company),
            'numberOfEntries' => '1',
            'documents' => [$document],
        ];

        $submission = FeSubmission::create([
            'invoice_id' => $invoice->id,
            'submission_uuid' => $submissionUUID,
            'endpoint' => 'registarFactura',
            'request_payload' => $payload,
            'fe_status' => 'pending',
        ]);

        $response = $this->client->registarFactura($payload);
        $body = $response->json();

        // CORRIGIDO: a AGT devolve por vezes 'errorList' => [""] (array com uma
        // string vazia) mesmo quando não há erro real na resposta imediata de
        // registo — empty() de um array com 1 elemento é sempre false, o que
        // marcava 'invalid' incorretamente. Filtramos entradas vazias antes de
        // decidir o estado; o resultado definitivo só vem mesmo do obterEstado.
        $errors = array_filter($body['errorList'] ?? [], fn($e) => trim((string) $e) !== '');

        $submission->update([
            'response_payload' => $body,
            'request_id' => $body['requestID'] ?? null,
            'error_list' => $errors ?: null,
            'fe_status' => !empty($body['requestID']) ? 'pending' : (empty($errors) ? 'pending' : 'invalid'),
        ]);

        $invoice->update([
            'fe_status' => $submission->fe_status,
            'fe_request_id' => $submission->request_id,
            // CORRIGIDO: $agtDocumentNo era gerado mas nunca persistido —
            // buildPaymentReceiptDocument() precisa dele em $paidInvoice->agt_document_no
            // para referenciar esta factura a partir de um recibo futuro.
            'agt_document_no' => $agtDocumentNo,
        ]);

        if (!$response->successful() && empty($body['requestID'])) {
            throw new RuntimeException(
                'registarFactura rejeitado: ' . json_encode($body['errorList'] ?? $body)
            );
        }

        return $submission;
    }

    /**
     * Constrói documentos de facturação normais: FT, FR, TV, NC, ND.
     */
    private function buildDocument(Invoice $invoice, string $documentType, string $agtDocumentNo, Company $company): array
    {
        $customerTaxId = $invoice->customer?->tax_number ?: '999999999';
        $customerCountry = $invoice->customer?->country ?? 'AO'; // ver nota (B) acima
        // CORRIGIDO (E40): companyName tem de ser o MESMO valor no jwsDocumentSignature
        // e no campo final do documento. Estava a assinar $company->name (emissor) mas
        // a enviar o nome do cliente — a AGT detectou a inconsistência.
        $documentCompanyName = $invoice->customer?->name ?? 'Consumidor Final';

        $needsReference = in_array($documentType, self::REFERENCE_REQUIRED_TYPES, true);

        // CORRIGIDO: o schema real não tem 'reference_document_no' na NC/ND.
        // Em vez disso, segue o padrão já existente no model: a factura
        // ORIGINAL guarda credit_note_id/debit_note_id apontando para esta
        // NC/ND. A partir da NC/ND chamamos creditedInvoices()/debitedInvoices()
        // (inverso da relação) para encontrar a factura original.
        $referenceInvoice = match ($documentType) {
            'NC' => $invoice->creditedInvoices()->first(),
            'ND' => $invoice->debitedInvoices()->first(),
            default => null,
        };
        $referenceDocumentNo = $referenceInvoice?->agt_document_no;
        $referenceReason = $invoice->reference_reason ?? 'Nota emitida com referência ao documento original';

        if ($needsReference && !$referenceDocumentNo) {
            throw new RuntimeException(
                "Documento {$documentType} exige referência ao documento base, mas "
                . "Invoice#{$invoice->id} não tem uma factura associada via "
                . ($documentType === 'NC' ? 'creditedInvoices()' : 'debitedInvoices()')
                . ' com agt_document_no preenchido. Confirme que a factura original já '
                . 'foi submetida à AGT antes de emitir esta nota, e que o link '
                . ($documentType === 'NC' ? 'credit_note_id' : 'debit_note_id')
                . ' foi criado na factura original.'
            );
        }

        $lines = [];
        foreach ($invoice->items as $index => $item) {
            $lineNumber = (string) ($index + 1);
            $netAmount = (float) $item->net_amount;

            $line = [
                'lineNumber' => $lineNumber,
                // TODO (A): ajuste conforme o tipo real de venda
                'operationType' => 'TB',
                'productCode' => $item->product_code ?? (string) $item->product_id,
                'productDescription' => $item->description,
                // Todos estes campos são Tipo: String na spec da AGT (confirmado
                // no exemplo do SDK Node agt-fe-sdk) — enviar como número JSON
                // quebra a verificação de jwsDocumentSignature.
                'quantity' => $this->money($item->quantity, 3),
                'unitOfMeasure' => $item->unit ?? 'UN',
                'unitPriceBase' => $this->money($item->unit_price),
                'unitPrice' => $this->money($item->unit_price),
                // CORRIGIDO: para documentos de venda (FT/FR/TV) o valor vai em
                // creditAmount; debitAmount é só para estornos/devoluções pontuais.
                // Para NC (documento inteiro de devolução) é o INVERSO — por isso
                // decidimos pelo tipo de documento, não só pelo sinal do valor.
                'debitAmount' => $this->lineDebitAmount($documentType, $netAmount),
                'creditAmount' => $this->lineCreditAmount($documentType, $netAmount),
                'taxes' => [
                    [
                        'taxType' => 'IVA',
                        'taxCountryRegion' => 'AO',
                        'taxCode' => $item->tax_code ?: 'ISE',
                        'taxPercentage' => $this->money($item->tax_rate),
                        'taxContribution' => $this->money(round((float) $item->tax_amount, 2)),
                    ]
                ],
                'settlementAmount' => $this->money($item->discount_amount ?? 0),
            ];

            if ($needsReference) {
                $line['referenceInfo'] = [
                    'reference' => $referenceDocumentNo,
                    // TODO (C): se a NC/ND referenciar linhas específicas da
                    // factura original, substitua "1" pelo número real da linha.
                    'referenceItemLineNo' => (string) ($item->reference_item_line_no ?? '1'),
                    'reason' => $referenceReason,
                ];
            }

            $lines[] = $line;
        }

        $documentTotals = [
            'taxPayable' => $this->money($invoice->tax_amount),
            'netTotal' => $this->money($invoice->taxable_amount),
            'grossTotal' => $this->money($invoice->total_amount),
        ];

        return [
            'documentNo' => $agtDocumentNo,
            'documentStatus' => 'N',
            'jwsDocumentSignature' => $this->signer->signDocument([
                'documentNo' => $agtDocumentNo,
                'documentType' => $documentType,
                'documentDate' => $invoice->issued_at->format('Y-m-d'),
                'customerTaxID' => $customerTaxId,
                'customerCountry' => $customerCountry,
                'companyName' => $documentCompanyName,
                'documentTotals' => $documentTotals,
            ], $company->nif, $company->agt_private_key_path),
            'documentDate' => $invoice->issued_at->format('Y-m-d'),
            'documentType' => $documentType,
            'systemEntryDate' => now()->format('Y-m-d\TH:i:s'),
            'customerTaxID' => $customerTaxId,
            'customerCountry' => $customerCountry,
            'companyName' => $documentCompanyName,
            'lines' => $lines,
            'documentTotals' => $documentTotals,
        ];
    }

    /**
     * Decide o debitAmount de uma linha consoante o tipo de documento.
     * - FT/FR/TV (venda normal): débito só em estornos pontuais (valor negativo).
     * - NC (documento inteiro de devolução): o valor da devolução vai em débito.
     * - ND (documento inteiro de encargo adicional): funciona como venda normal.
     */
    private function lineDebitAmount(string $documentType, float $netAmount): string
    {
        if ($documentType === 'NC') {
            return $this->money(abs($netAmount));
        }

        return $netAmount < 0 ? $this->money(abs($netAmount)) : $this->money(0);
    }

    private function lineCreditAmount(string $documentType, float $netAmount): string
    {
        if ($documentType === 'NC') {
            return $this->money(0);
        }

        return $netAmount >= 0 ? $this->money($netAmount) : $this->money(0);
    }

    /**
     * Constrói documentos de recibo: RC (numerário) e RG (outros recibos).
     * Estrutura CONFIRMADA na certificação: usa 'paymentReceipt.sourceDocuments'
     * em vez de 'lines'.
     *
     * Depende de Invoice::paidInvoices() (ver migration invoice_settlements) —
     * o chamador deve ter associado as facturas a pagar ANTES de chamar submit(),
     * por exemplo:
     *   $recibo->paidInvoices()->attach($facturaId, ['amount_paid' => 570.00]);
     *   $feInvoiceService->submit($recibo);
     */
    private function buildPaymentReceiptDocument(Invoice $invoice, string $documentType, string $agtDocumentNo, Company $company): array
    {
        $customerTaxId = $invoice->customer?->tax_number ?: '999999999';
        $customerCountry = $invoice->customer?->country ?? 'AO';
        // CORRIGIDO (E40): mesmo valor tem de ir na assinatura e no campo final.
        $documentCompanyName = $invoice->customer?->name ?? 'Consumidor Final';

        // TODO (D): substitua por como o seu sistema regista as facturas pagas.
        $paidInvoices = method_exists($invoice, 'paidInvoices')
            ? $invoice->paidInvoices
            : collect();

        if ($paidInvoices->isEmpty()) {
            throw new RuntimeException(
                "Recibo (Invoice#{$invoice->id}) não tem facturas associadas para liquidar. "
                . "Implemente Invoice::paidInvoices() antes de emitir recibos reais."
            );
        }

        $sourceDocuments = [];
        foreach ($paidInvoices as $index => $paidInvoice) {
            $sourceDocuments[] = [
                'lineNo' => (string) ($index + 1),
                'sourceDocumentID' => [
                    'originatingON' => $paidInvoice->agt_document_no, // documentNo original já registado na AGT
                    'documentDate' => $paidInvoice->issued_at->format('Y-m-d'),
                ],
                'creditAmount' => $this->money($paidInvoice->pivot->amount_paid ?? $paidInvoice->total_amount),
            ];
        }

        $documentTotals = [
            'taxPayable' => $this->money($invoice->tax_amount),
            'netTotal' => $this->money($invoice->taxable_amount),
            'grossTotal' => $this->money($invoice->total_amount),
        ];

        return [
            'documentNo' => $agtDocumentNo,
            'documentStatus' => 'N',
            'jwsDocumentSignature' => $this->signer->signDocument([
                'documentNo' => $agtDocumentNo,
                'documentType' => $documentType,
                'documentDate' => $invoice->issued_at->format('Y-m-d'),
                'customerTaxID' => $customerTaxId,
                'customerCountry' => $customerCountry,
                'companyName' => $documentCompanyName,
                'documentTotals' => $documentTotals,
            ], $company->nif, $company->agt_private_key_path),
            'documentDate' => $invoice->issued_at->format('Y-m-d'),
            'documentType' => $documentType,
            'systemEntryDate' => now()->format('Y-m-d\TH:i:s'),
            'customerTaxID' => $customerTaxId,
            'customerCountry' => $customerCountry,
            'companyName' => $documentCompanyName,
            'documentTotals' => $documentTotals,
            'paymentReceipt' => [
                'sourceDocuments' => $sourceDocuments,
            ],
        ];
    }

    /**
     * Formata valores numéricos/monetários como STRING com casas decimais
     * fixas — a spec DS.120 declara estes campos como Tipo: String, e o
     * exemplo do SDK Node (agt-fe-sdk) confirma isso ("140.00" entre aspas).
     * Essencial para bater com o que foi assinado no JWS.
     */
    private function money(int|float|string $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, '.', '');
    }

    private function buildSoftwareInfo(Company $company): array
    {
        $detail = [
            'productId' => config('agt.software.product_id'),
            'productVersion' => config('agt.software.product_version'),
            'softwareValidationNumber' => config('agt.software.software_validation_number'),
            // CORRIGIDO: faltava esta chave, mas signSoftwareInfo() já a lia,
            // causando 'Undefined array key' e inconsistência entre o payload
            // enviado e o que era efectivamente assinado.
            'signatureVersion' => 1,
        ];

        return [
            'softwareInfoDetail' => $detail,
            'jwsSoftwareSignature' => $this->signer->signSoftwareInfo(
                $detail,
                $company->agt_software_private_key_path ?? $company->agt_private_key_path
            ),
        ];
    }

    /**
     * Consulta obterEstado para uma submissão pendente e actualiza o Invoice.
     */
    public function pollStatus(FeSubmission $submission): void
    {
        $company = Company::firstOrFail();
        $nif = $company->nif ?? config('agt.tax_registration_number');
        $company->agt_private_key_path = $company->agt_private_key_path ?? config('agt.private_key_path');
        $company->agt_software_private_key_path = $company->agt_software_private_key_path
            ?? config('agt.software_private_key_path')
            ?? config('agt.private_key_path');

        $payload = [
            'schemaVersion' => config('agt.schema_version'),
            'submissionUUID' => (string) Str::uuid(),
            'taxRegistrationNumber' => $nif,
            'submissionTimeStamp' => now()->toIso8601String(),
            'softwareInfo' => $this->buildSoftwareInfo($company),
            'requestID' => $submission->request_id,
        ];

        $response = $this->client->obterEstado($payload);
        $body = $response->json();

        $submission->increment('poll_attempts');
        $submission->update([
            'last_polled_at' => now(),
            'response_payload' => $body,
        ]);

        $docStatus = $body['documentStatusList'][0]['documentStatus'] ?? null; // V=válida, I=inválida

        if ($docStatus === 'V') {
            $submission->update(['fe_status' => 'valid']);
            $submission->invoice->update(['fe_status' => 'valid']);
        } elseif ($docStatus === 'I') {
            $submission->update([
                'fe_status' => 'invalid',
                'error_list' => $body['documentStatusList'][0]['errorList'] ?? null,
            ]);
            $submission->invoice->update(['fe_status' => 'invalid']);
        }
        // se resultCode ainda indicar processamento em curso, mantém 'pending'
        // para o job de polling tentar novamente (ver FeSubmission::fe_status)
    }
}