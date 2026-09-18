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
 *    venda (FT/FR/TV). CORRIGIDO: venda (valor positivo) -> creditAmount;
 *    devolução/estorno (valor negativo) -> debitAmount.
 *
 * 3. NC e ND exigem 'referenceInfo' (reference, referenceItemLineNo,
 *    reason) POR LINHA, apontando para o documento base. ADICIONADO.
 *
 * 4. Recibos (RC, RG) NÃO usam 'lines' — usam 'paymentReceipt.sourceDocuments'.
 *    ADICIONADO buildPaymentReceiptDocument().
 *
 * 5. errorList da AGT pode vir como array de objectos ({errorCode,
 *    errorMessage}), não só strings — o array_filter original rebentava
 *    com "Array to string conversion". CORRIGIDO com map+filter via collect().
 *
 * 6. taxExemptionCode adicionado às linhas com taxCode=ISE — a spec
 *    da AGT torna este campo obrigatório nesse caso. TODO: 'OUT01' é
 *    placeholder até confirmarmos o Anexo 6.4 (catálogo real de códigos
 *    de motivo de isenção). Depende também das colunas tax_exemption_code
 *    em products/invoice_items, que ainda não foram criadas — ver TODO (E).
 *
 * 7. NOVO: productCode vinha vazio ("") em vez de null quando invoice_items
 *    não tinha product_code preenchido — o operador ?? não intercepta
 *    strings vazias, só null, então o fallback para product_id nunca
 *    era accionado e a AGT rejeitava com "productCode: é obrigatório" /
 *    "deve ter no mínimo 1 caracteres". CORRIGIDO com firstNonBlank().
 *    AINDA ASSIM: isto é um sintoma — confirme por que a origem das
 *    linhas de ND (provavelmente uma cópia da factura original) não
 *    está a preencher product_code; esta correção é uma rede de
 *    segurança, não resolve a causa dos dados em falta.
 *
 * 8. NOVO: validação local (validateInvoiceForSubmission) movida para
 *    ANTES de seriesService->nextDocumentNo() em submit(). Antes, um
 *    documento com productCode vazio ou sem referência NC/ND válida
 *    só falhava DEPOIS de já ter consumido um número da série — cada
 *    tentativa rejeitada deixava um buraco na numeração sequencial,
 *    o que pode ser sinalizado como anomalia em auditoria/inspeção da
 *    AGT. Agora falha antes de pedir o número, sempre que o erro for
 *    detetável localmente (sem precisar de round-trip à AGT).
 *
 * ATENÇÃO — pontos que ainda precisa de confirmar/ajustar:
 *
 * A) `operationType` por linha é OBRIGATÓRIO na v1.2. Por omissão assumo
 *    "TB" (Transmissão de bens) — ajuste para "SG" se vender serviços.
 *
 * B) `customerCountry`/`customerTaxID`: assumo "AO" e tax_number, com
 *    fallback "999999999" para consumidor final.
 *
 * C) Para NC/ND: usa creditedInvoices()/debitedInvoices() para encontrar
 *    a factura original. `referenceItemLineNo` assume-se "1" por omissão.
 *
 * D) Recibos (RC/RG) usam Invoice::paidInvoices() (pivot invoice_settlements).
 *
 * E) taxExemptionCode: depende de uma coluna tax_exemption_code em
 *    products e invoice_items, ainda não criada. Até lá, fica sempre
 *    'OUT01' (placeholder) para qualquer linha ISE.
 *
 * F) documentNo "<tipo> <seriesCode>/<seq>" (ex: "ND ND3926S2480N/1") é
 *    o formato OFICIAL da AGT, confirmado no docblock de
 *    FeSeriesService::nextDocumentNo() — o seriesCode devolvido pela
 *    AGT já inclui o prefixo do tipo de documento. Não é bug.
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
        if ($invoice->agt_document_no || in_array($invoice->fe_status, ['pending', 'valid'], true)) {
            throw new RuntimeException(
                "Invoice#{$invoice->id} já foi submetida à AGT (agt_document_no: "
                . ($invoice->agt_document_no ?? 'nenhum') . ", fe_status: {$invoice->fe_status}). "
                . 'Não é permitido reenviar — use pollStatus() para consultar o resultado.'
            );
        }
        $invoice->loadMissing(['items', 'taxSummaries', 'customer']);
        $company = Company::firstOrFail();
        $company->nif = $company->nif ?? config('agt.tax_registration_number');
        $company->agt_private_key_path = $company->agt_private_key_path ?? config('agt.private_key_path');
        $company->agt_software_private_key_path = $company->agt_software_private_key_path
            ?? config('agt.software_private_key_path')
            ?? config('agt.private_key_path');

        $documentType = self::DOCUMENT_TYPE_MAP[$invoice->document_type]
            ?? throw new RuntimeException("Tipo de documento '{$invoice->document_type}' sem mapeamento AGT.");

        // CORRIGIDO (8): validação local ANTES de consumir número da série.
        // Evita queimar sequência em erros detetáveis sem round-trip à AGT
        // (productCode em falta, referência NC/ND inexistente, etc.).
        $this->validateInvoiceForSubmission($invoice, $documentType);

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
            'submissionTimeStamp' => now()->format('Y-m-d\TH:i:s'),
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

        $errors = collect($body['errorList'] ?? [])
            ->map(function ($e) {
                if (is_array($e)) {
                    return $e['errorMessage'] ?? $e['message'] ?? json_encode($e);
                }
                return (string) $e;
            })
            ->filter(fn($e) => trim($e) !== '')
            ->values()
            ->all();

        $submission->update([
            'response_payload' => $body,
            'request_id' => $body['requestID'] ?? null,
            'error_list' => $errors ?: null,
            'fe_status' => !empty($body['requestID']) ? 'pending' : (empty($errors) ? 'pending' : 'invalid'),
        ]);

        $invoice->update([
            'fe_status' => $submission->fe_status,
            'fe_request_id' => $submission->request_id,
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
     * Validação local dos campos que a AGT já rejeitou por erro de schema
     * (E01/E03), corrida ANTES de pedir um número de série. O objectivo
     * não é substituir a validação da AGT (validarDocumento), mas apanhar
     * cedo os erros óbvios e evitar consumir a sequência com documentos
     * que nunca poderiam ser aceites.
     */
    private function validateInvoiceForSubmission(Invoice $invoice, string $documentType): void
    {


        if (in_array($documentType, self::RECEIPT_TYPES, true)) {
            $paidInvoices = method_exists($invoice, 'paidInvoices') ? $invoice->paidInvoices : collect();

            if ($paidInvoices->isEmpty()) {
                throw new RuntimeException(
                    "Recibo (Invoice#{$invoice->id}) não tem facturas associadas para liquidar. "
                    . "Implemente Invoice::paidInvoices() antes de emitir recibos reais."
                );
            }

            foreach ($paidInvoices as $paid) {
                if (!$paid->agt_document_no) {
                    throw new RuntimeException(
                        "Recibo (Invoice#{$invoice->id}): factura #{$paid->id} ({$paid->invoice_number}) "
                        . "ainda não tem agt_document_no — não pode ser referenciada num recibo."
                    );
                }
            }
            return;
        }

        if ($invoice->items->isEmpty()) {
            throw new RuntimeException("Invoice#{$invoice->id} não tem items — impossível gerar documento AGT.");
        }

        foreach ($invoice->items as $index => $item) {
            $lineNumber = $index + 1;

            if ($this->firstNonBlank($item->product_code, $item->product_id) === null) {
                throw new RuntimeException(
                    "Invoice#{$invoice->id} linha {$lineNumber}: sem product_code nem product_id — "
                    . "impossível gerar productCode para a AGT."
                );
            }

            if ($item->quantity === null || trim((string) $item->quantity) === '') {
                throw new RuntimeException("Invoice#{$invoice->id} linha {$lineNumber}: quantity em falta.");
            }

            if ($item->unit_price === null || trim((string) $item->unit_price) === '') {
                throw new RuntimeException("Invoice#{$invoice->id} linha {$lineNumber}: unit_price em falta.");
            }

            if (empty($item->description)) {
                throw new RuntimeException("Invoice#{$invoice->id} linha {$lineNumber}: description em falta.");
            }
        }

        if (in_array($documentType, self::REFERENCE_REQUIRED_TYPES, true)) {
            $referenceInvoice = match ($documentType) {
                'NC' => $invoice->creditedInvoices()->first(),
                'ND' => $invoice->debitedInvoices()->first(),
                default => null,
            };

            if (!$referenceInvoice?->agt_document_no) {
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
        }
    }

    /**
     * Devolve o primeiro valor não-nulo e não-vazio (após trim) entre os
     * candidatos, como string. Diferente de ??, que só reage a null e
     * deixa passar strings vazias "" — a causa do bug de productCode.
     */
    private function firstNonBlank(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return (string) $value;
            }
        }
        return null;
    }

    /**
     * Constrói documentos de facturação normais: FT, FR, TV, NC, ND.
     */
    private function buildDocument(Invoice $invoice, string $documentType, string $agtDocumentNo, Company $company): array
    {
        $customerTaxId = $invoice->customer?->tax_number ?: '999999999';
        $customerCountry = $invoice->customer?->country ?? 'AO'; // ver nota (B) acima
        $documentCompanyName = $invoice->customer?->name ?? 'Consumidor Final';

        $needsReference = in_array($documentType, self::REFERENCE_REQUIRED_TYPES, true);

        // A validação de existência já correu em validateInvoiceForSubmission(),
        // antes de nextDocumentNo(). Aqui só reobtemos os dados para montar as linhas.
        $referenceInvoice = match ($documentType) {
            'NC' => $invoice->creditedInvoices()->first(),
            'ND' => $invoice->debitedInvoices()->first(),
            default => null,
        };
        $referenceDocumentNo = $referenceInvoice?->agt_document_no;
        $referenceReason = $invoice->reference_reason ?? 'Nota emitida com referência ao documento original';

        $lines = [];
        foreach ($invoice->items as $index => $item) {
            $lineNumber = (string) ($index + 1);
            $netAmount = (float) $item->net_amount;
            $taxCode = $item->tax_code ?: 'ISE';

            $line = [
                'lineNumber' => $lineNumber,
                // TODO (A): ajuste conforme o tipo real de venda
                'operationType' => 'TB',
                // CORRIGIDO (7): firstNonBlank() trata "" como ausente, ao
                // contrário de ?? que só reage a null. Antes, quando
                // product_code vinha como string vazia da BD, o fallback
                // para product_id nunca era accionado e a AGT rejeitava
                // com "productCode: deve ter no mínimo 1 caracteres".
                'productCode' => $this->firstNonBlank($item->product_code, $item->product_id),
                'productDescription' => $item->description,
                'quantity' => $this->money(abs((float) $item->quantity), 3),
                'unitOfMeasure' => $item->unit ?? 'UN',
                'unitPriceBase' => $this->money(abs((float) $item->unit_price)),
                'unitPrice' => $this->money(abs((float) $item->unit_price)),
                'debitAmount' => $this->lineDebitAmount($documentType, $netAmount),
                'creditAmount' => $this->lineCreditAmount($documentType, $netAmount),
                'taxes' => [
                    [
                        'taxType' => 'IVA',
                        'taxCountryRegion' => 'AO',
                        'taxCode' => $taxCode,
                        'taxPercentage' => $this->money(abs((float) $item->tax_rate)),
                        'taxContribution' => $this->money(abs(round((float) $item->tax_amount, 2))),
                        // TODO (E): 'OUT01' é placeholder — falta o Anexo 6.4
                        // da AGT e as colunas tax_exemption_code em
                        // products/invoice_items.
                        ...($taxCode === 'ISE' ? [
                            'taxExemptionCode' => $item->tax_exemption_code ?? 'OUT01',
                        ] : []),
                    ]
                ],
                'settlementAmount' => $this->money(abs((float) ($item->discount_amount ?? 0))),
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
            'taxPayable' => $this->money(abs((float) $invoice->tax_amount)),
            'netTotal' => $this->money(abs((float) $invoice->taxable_amount)),
            'grossTotal' => $this->money(abs((float) $invoice->total_amount)),
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
     */
    private function buildPaymentReceiptDocument(Invoice $invoice, string $documentType, string $agtDocumentNo, Company $company): array
    {
        $customerTaxId = $invoice->customer?->tax_number ?: '999999999';
        $customerCountry = $invoice->customer?->country ?? 'AO';
        $documentCompanyName = $invoice->customer?->name ?? 'Consumidor Final';

        // A validação de existência já correu em validateInvoiceForSubmission().
        $paidInvoices = method_exists($invoice, 'paidInvoices') ? $invoice->paidInvoices : collect();

        $sourceDocuments = [];
        foreach ($paidInvoices as $index => $paidInvoice) {
            $sourceDocuments[] = [
                'lineNo' => (string) ($index + 1),
                'sourceDocumentID' => [
                    'originatingON' => $paidInvoice->agt_document_no,
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
            'submissionTimeStamp' => now()->format('Y-m-d\TH:i:s'),
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
    }

    public function listInvoices(array $filters = []): array
    {
        $company = Company::firstOrFail();
        $nif = $company->nif ?? config('agt.tax_registration_number');
        $company->agt_private_key_path = $company->agt_private_key_path ?? config('agt.private_key_path');
        $company->agt_software_private_key_path = $company->agt_software_private_key_path
            ?? config('agt.software_private_key_path')
            ?? config('agt.private_key_path');

        if (empty($filters['queryStartDate']) || empty($filters['queryEndDate'])) {
            throw new RuntimeException(
                'listInvoices requer queryStartDate e queryEndDate (formato a confirmar — provável Y-m-d).'
            );
        }

        $payload = [
            'schemaVersion' => config('agt.schema_version'),
            'submissionUUID' => (string) Str::uuid(),
            'taxRegistrationNumber' => $nif,
            'submissionTimeStamp' => now()->format('Y-m-d\TH:i:s'),
            'softwareInfo' => $this->buildSoftwareInfo($company),
            'queryStartDate' => $filters['queryStartDate'],
            'queryEndDate' => $filters['queryEndDate'],
        ];

        if (isset($filters['pageNumber'])) {
            $payload['pageNumber'] = $filters['pageNumber'];
        }
        if (isset($filters['pageSize'])) {
            $payload['pageSize'] = $filters['pageSize'];
        }

        $response = $this->client->listarFacturas($payload);
        $body = $response->json();

        if (!$response->successful()) {
            throw new RuntimeException('listarFacturas falhou: ' . json_encode($body));
        }

        return $body;
    }

    public function validateDocument(Invoice $invoice): array
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

        // CORRIGIDO (8): mesma validação local aplicada aqui, para
        // validateDocument() também falhar cedo em vez de gerar um payload
        // inválido e só descobrir isso na resposta da AGT.
        $this->validateInvoiceForSubmission($invoice, $documentType);

        $provisionalDocumentNo = $invoice->agt_document_no
            ?? $documentType . '/999999999/0';

        $document = in_array($documentType, self::RECEIPT_TYPES, true)
            ? $this->buildPaymentReceiptDocument($invoice, $documentType, $provisionalDocumentNo, $company)
            : $this->buildDocument($invoice, $documentType, $provisionalDocumentNo, $company);

        $payload = [
            'schemaVersion' => config('agt.schema_version'),
            'submissionUUID' => (string) Str::uuid(),
            'taxRegistrationNumber' => $company->nif,
            'submissionTimeStamp' => now()->format('Y-m-d\TH:i:s'),
            'softwareInfo' => $this->buildSoftwareInfo($company),
            'numberOfEntries' => '1',
            'documents' => [$document],
        ];

        $response = $this->client->validarDocumento($payload);
        $body = $response->json();

        if (!$response->successful()) {
            throw new RuntimeException('validarDocumento falhou: ' . json_encode($body));
        }

        return $body;
    }
}