<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TaxRate;
use DOMDocument;
use DOMElement;

/**
 * SAF-T AO — Standard Audit File for Tax purposes (Angola)
 * Baseado no esquema AGT / Decreto Executivo n.º 82/22 de 8 de Abril
 *
 * Estrutura obrigatória do ficheiro:
 *   <AuditFile>
 *     <Header>              — Dados da empresa e período
 *     <MasterFiles>         — Produtos, clientes, taxas
 *     <SourceDocuments>     — Facturas, notas de crédito
 *   </AuditFile>
 */
class SaftExportService
{
    private DOMDocument $xml;
    private DOMElement $root;
    private Company $company;
    private int $year;
    private int $month; // 0 = ano completo

    public function __construct()
    {
        $this->xml = new DOMDocument('1.0', 'UTF-8');
        $this->xml->formatOutput = true;
    }

    // ═══════════════════════════════════════════════════════
    // 🏁 PONTO DE ENTRADA — gera o XML para um período
    // ═══════════════════════════════════════════════════════
    public function generate(int $year, int $month = 0): string
    {
        $this->year = $year;
        $this->month = $month;
        $this->company = Company::firstOrFail();

        $this->root = $this->xml->createElement('AuditFile');
        // Namespace oficial AGT — versão 1.01_01 (XSD: SAFTAO1.01_01.xsd)
        $this->root->setAttribute('xmlns', 'urn:OECD:StandardAuditFile-Tax:AO_1.01_01');
        $this->root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $this->root->setAttribute(
            'xsi:schemaLocation',
            'urn:OECD:StandardAuditFile-Tax:AO_1.01_01 https://raw.githubusercontent.com/assoft-portugal/SAF-T-AO/master/XSD/SAFTAO1.01_01.xsd'
        );
        $this->xml->appendChild($this->root);

        $this->buildHeader();
        $this->buildMasterFiles();
        $this->buildSourceDocuments();

        return $this->xml->saveXML();
    }

    // ═══════════════════════════════════════════════════════
    // 📋 HEADER — identificação da empresa e do período
    // ═══════════════════════════════════════════════════════
    private function buildHeader(): void
    {
        $header = $this->el('Header');

        $this->text($header, 'AuditFileVersion', '1.01_01');
        $this->text($header, 'CompanyID', $this->company->nif);
        $this->text($header, 'TaxRegistrationNumber', $this->company->nif);
        $this->text($header, 'TaxAccountingBasis', 'I'); // I = Invoice (facturação)
        $this->text($header, 'CompanyName', $this->company->name);
        $this->text($header, 'BusinessName', $this->company->trade_name ?? $this->company->name);

        $address = $this->el('CompanyAddress', $header);
        $this->text($address, 'AddressDetail', $this->company->address);
        $this->text($address, 'City', $this->company->city);
        // XSD AddressStructureAO: ordem obrigatória — PostalCode → Province → Country
        if ($this->company->postal_code) {
            $this->text($address, 'PostalCode', $this->company->postal_code);
        }
        if ($this->company->province) {
            $this->text($address, 'Province', $this->company->province);
        }
        $this->text($address, 'Country', 'AO'); // fixed="AO" no XSD

        $this->text($header, 'FiscalYear', (string) $this->year);
        $this->text($header, 'StartDate', $this->periodStart());
        $this->text($header, 'EndDate', $this->periodEnd());
        $this->text($header, 'CurrencyCode', $this->company->currency ?? 'AOA');
        $this->text($header, 'DateCreated', now()->format('Y-m-d'));
        $this->text($header, 'TaxEntity', 'Global');
        $this->text($header, 'ProductCompanyTaxID', $this->company->nif);
        // SoftwareValidationNumber: padrão XSD "\d+/AGT/\d{4}" ex: "73/AGT/2019" — ou "0" se não certificado
        $certNumber = $this->company->certificate_number ?? '0';
        $this->text($header, 'SoftwareValidationNumber', $certNumber);
        // ProductID: padrão XSD "[^/]+/[^/]+" — "NomeAplicação/NomeEmpresa"
        $productId = ($this->company->software_name ?? 'KambaPOS') . '/' . ($this->company->name ?? 'Empresa');
        $this->text($header, 'ProductID', $productId);
        $this->text($header, 'ProductVersion', $this->company->software_version ?? '1.0');

        $this->root->appendChild($header);
    }

    // ═══════════════════════════════════════════════════════
    // 📦 MASTER FILES — produtos, clientes, taxas
    // ═══════════════════════════════════════════════════════
    private function buildMasterFiles(): void
    {
        $master = $this->el('MasterFiles');

        $this->buildGeneralLedgerAccounts($master);
        $this->buildCustomers($master);
        $this->buildProducts($master);
        $this->buildTaxTable($master);

        $this->root->appendChild($master);
    }

    private function buildGeneralLedgerAccounts(DOMElement $parent): void
    {
        // Para POS simples usamos a conta de vendas genérica (conta de 1.º grau)
        // XSD GroupingCodeConstraint: GroupingCode deve referenciar um AccountID existente.
        // Conta de 1.º grau não tem conta agregadora — GroupingCode é omitido.
        $account = $this->el('GeneralLedgerAccounts', $parent);
        $entry = $this->el('Account', $account);
        $this->text($entry, 'AccountID', '71');
        $this->text($entry, 'AccountDescription', 'Vendas');
        $this->text($entry, 'OpeningDebitBalance', '0.00');
        $this->text($entry, 'OpeningCreditBalance', '0.00');
        $this->text($entry, 'ClosingDebitBalance', '0.00');
        $this->text($entry, 'ClosingCreditBalance', '0.00');
        $this->text($entry, 'GroupingCategory', 'GR');
        // GroupingCode omitido: conta de 1.º grau não tem agregadora (evita GroupingCodeConstraint error)
    }

    private function buildCustomers(DOMElement $parent): void
    {
        // Inclui apenas clientes que têm facturas no período
        $customerIds = Invoice::whereBetween('issued_at', [$this->periodStart(), $this->periodEnd() . ' 23:59:59'])
            ->whereIn('status', ['issued', 'credited'])
            ->pluck('customer_id')
            ->unique()
            ->filter();

        $customers = Customer::whereIn('id', $customerIds)->get();

        foreach ($customers as $customer) {
            $node = $this->el('Customer', $parent);

            $this->text($node, 'CustomerID', (string) $customer->id);
            $this->text($node, 'AccountID', 'Desconhecido');
            $this->text($node, 'CustomerTaxID', $customer->tax_number ?? '999999999');
            $this->text($node, 'CompanyName', $customer->name);

            $addr = $this->el('BillingAddress', $node);
            $this->text($addr, 'AddressDetail', $customer->address ?? 'Desconhecido');
            $this->text($addr, 'City', $customer->city ?? 'Desconhecido');
            // XSD: PostalCode → Province → Country (Province opcional)
            if ($customer->postal_code) {
                $this->text($addr, 'PostalCode', $customer->postal_code);
            }
            $this->text($addr, 'Country', $customer->country ?? 'AO');

            // XSD minLength=1 — omitir se vazio (campo opcional no XSD)
            if (!empty($customer->phone)) {
                $this->text($node, 'Telephone', $customer->phone);
            }
            if (!empty($customer->email)) {
                $this->text($node, 'Email', $customer->email);
            }
            $this->text($node, 'SelfBillingIndicator', '0');
        }
    }

    private function buildProducts(DOMElement $parent): void
    {
        // Inclui apenas produtos vendidos no período
        $items = \App\Models\InvoiceItem::whereHas(
            'invoice',
            fn($q) =>
            $q->whereBetween('issued_at', [$this->periodStart(), $this->periodEnd() . ' 23:59:59'])
                ->whereIn('status', ['issued', 'credited'])
        )->get(['product_id', 'product_code', 'description']);

        // Produtos com product_id válido — carregados da tabela de produtos
        $productIds = $items->pluck('product_id')->filter()->unique();
        $products = Product::with('taxRate')->whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            $node = $this->el('Product', $parent);

            // P = Produto | S = Serviço | O = Outro | E = Encargo (vd. SAF-T AO)
            $this->text($node, 'ProductType', 'P');
            $this->text($node, 'ProductCode', $product->product_code ?? (string) $product->id);
            $this->text($node, 'ProductGroup', $product->category?->name ?? 'Geral');
            $this->text($node, 'ProductDescription', $product->name);
            $this->text($node, 'ProductNumberCode', $product->barcode ?? $product->product_code ?? (string) $product->id);
        }

        // R2 FIX: itens sem product_id (produto apagado ou linha manual)
        // precisam de uma entrada na lista mestre para o validador não falhar.
        // Agrupamos por product_code para evitar duplicados.
        $orphanItems = $items->whereNull('product_id')->unique('product_code');

        foreach ($orphanItems as $item) {
            $code = $item->product_code ?: 'MISC';

            // Evita duplicar se já existe um produto com o mesmo código
            $alreadyExported = $products->contains(
                fn($p) => ($p->product_code ?? (string) $p->id) === $code
            );
            if ($alreadyExported) {
                continue;
            }

            $node = $this->el('Product', $parent);
            $this->text($node, 'ProductType', 'P');
            $this->text($node, 'ProductCode', $code);
            $this->text($node, 'ProductGroup', 'Geral');
            $this->text($node, 'ProductDescription', $item->description ?: 'Produto sem referência');
            $this->text($node, 'ProductNumberCode', $code);
        }
    }

    private function buildTaxTable(DOMElement $parent): void
    {
        $taxTable = $this->el('TaxTable', $parent);

        // XSD Regra 3: TaxPercentage na TaxTable deve corresponder exactamente
        // às percentagens usadas nas linhas — exportar apenas taxas do período
        $usedTaxCodes = \App\Models\InvoiceItem::whereHas(
            'invoice',
            fn($q) => $q->whereBetween('issued_at', [$this->periodStart(), $this->periodEnd() . ' 23:59:59'])
                ->whereIn('status', ['issued', 'credited'])
        )->pluck('tax_code')->unique()->filter()->values();

        $codeMap = [
            'RED5' => 'RED',
            'RED7' => 'RED',
            'EXC' => 'OUT',
        ];

        // Carrega apenas as taxas usadas no período
        $rates = TaxRate::where('is_active', true)
            ->whereIn('tax_code', $usedTaxCodes)
            ->get();

        // Deduplicamos por (taxCode + taxPercentage) — RED/5.00 e RED/7.00 são entradas distintas
        $exportedKeys = [];

        foreach ($rates as $rate) {
            $taxCode = $codeMap[$rate->tax_code] ?? $rate->tax_code;
            $percentage = number_format((float) $rate->tax_percentage, 2, '.', '');
            $key = $taxCode . '|' . $percentage;

            if (in_array($key, $exportedKeys)) {
                continue;
            }
            $exportedKeys[] = $key;

            $entry = $this->el('TaxTableEntry', $taxTable);
            $this->text($entry, 'TaxType', $rate->tax_type);
            $this->text($entry, 'TaxCountryRegion', $rate->country ?? 'AO');
            $this->text($entry, 'TaxCode', $taxCode);
            $this->text($entry, 'Description', $rate->description);

            if ((float) $rate->tax_percentage > 0) {
                $this->text($entry, 'TaxPercentage', $percentage);
            } else {
                $this->text($entry, 'TaxAmount', '0.00');
            }
        }
    }

    // ═══════════════════════════════════════════════════════
    // 🧾 SOURCE DOCUMENTS — as facturas do período
    // ═══════════════════════════════════════════════════════
    private function buildSourceDocuments(): void
    {
        $sourceDocs = $this->el('SourceDocuments');
        $salesInvoices = $this->el('SalesInvoices', $sourceDocs);

        // XSD exige ordem estrita: NumberOfEntries → TotalDebit → TotalCredit → Invoice(s)
        // Calculamos os totais primeiro iterando os dados, depois escrevemos tudo na ordem certa.
        $invoices = Invoice::with(['items', 'taxSummaries', 'customer'])
            ->whereBetween('issued_at', [$this->periodStart(), $this->periodEnd() . ' 23:59:59'])
            ->whereIn('status', ['issued', 'credited'])
            ->orderBy('issued_at')
            ->orderBy('sequence_number')
            ->get();

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($invoices as $invoice) {
            $isCredit = in_array($invoice->document_type, ['NC', 'ND']);
            // TotalDebit/TotalCredit devem ser a soma dos DebitAmount/CreditAmount das linhas
            // que usam taxable_amount (net, sem IVA) — R7 valida esta consistência
            if ($isCredit) {
                $totalCredit += abs((float) $invoice->taxable_amount);
            } else {
                $totalDebit += abs((float) $invoice->taxable_amount);
            }
        }

        // ── Escreve cabeçalho do bloco na ordem do XSD ──────
        $this->text($salesInvoices, 'NumberOfEntries', (string) $invoices->count());
        $this->text($salesInvoices, 'TotalDebit', number_format($totalDebit, 2, '.', ''));
        $this->text($salesInvoices, 'TotalCredit', number_format($totalCredit, 2, '.', ''));

        // ── Escreve cada factura após os totais ──────────────
        foreach ($invoices as $invoice) {
            $isCredit = in_array($invoice->document_type, ['NC', 'ND']);

            $node = $this->el('Invoice', $salesInvoices);

            // XSD InvoiceNo padrão: "[^ ]+ [^/^ ]+/[0-9]+"
            // Ex válido: "FT A/2026/1" — série sem espaços, barra, número sem zeros à esquerda
            // Normaliza invoice_number para garantir conformidade
            $this->text($node, 'InvoiceNo', $this->normalizeInvoiceNo($invoice->invoice_number));

            // DocumentStatus
            $status = $this->el('DocumentStatus', $node);
            $invoiceStatus = match (true) {
                $invoice->status === 'cancelled' => 'A',
                $invoice->status === 'credited' && !$isCredit => 'A',
                default => 'N',
            };
            $this->text($status, 'InvoiceStatus', $invoiceStatus);
            $this->text($status, 'InvoiceStatusDate', $invoice->updated_at->format('Y-m-d\TH:i:s'));
            $this->text($status, 'SourceID', (string) ($invoice->user_id ?? 0));
            $this->text($status, 'SourceBilling', 'P'); // P = POS

            $this->text($node, 'Hash', $invoice->hash ?? '');
            $this->text($node, 'HashControl', $invoice->hash_control ?? '1');
            $this->text($node, 'Period', (string) $invoice->issued_at->month);
            $this->text($node, 'InvoiceDate', $invoice->issued_at->format('Y-m-d'));
            $this->text($node, 'InvoiceType', $invoice->document_type);
            // SpecialRegimes é um elemento complexo no XSD (não texto "0")
            // Filhos obrigatórios: SelfBillingIndicator, CashVATSchemeIndicator, ThirdPartiesBillingIndicator
            $specialRegimes = $this->el('SpecialRegimes', $node);
            $this->text($specialRegimes, 'SelfBillingIndicator', '0');
            $this->text($specialRegimes, 'CashVATSchemeIndicator', '0');
            $this->text($specialRegimes, 'ThirdPartiesBillingIndicator', '0');
            // XSD: SourceID aparece aqui no Invoice (fora do DocumentStatus) — obrigatório
            $this->text($node, 'SourceID', (string) ($invoice->user_id ?? 0));
            $this->text($node, 'EACCode', $this->company->cae ?? '');
            $this->text($node, 'SystemEntryDate', $invoice->created_at->format('Y-m-d\TH:i:s'));
            $this->text($node, 'CustomerID', (string) $invoice->customer_id);

            // Linhas — LineNumber sequencial por documento
            foreach ($invoice->items as $index => $item) {
                $line = $this->el('Line', $node);

                $this->text($line, 'LineNumber', (string) ($index + 1));
                $this->text($line, 'ProductCode', $item->product_code ?? '');
                $this->text($line, 'ProductDescription', $item->description);
                $this->text($line, 'Quantity', number_format((float) $item->quantity, 3, '.', ''));
                $this->text($line, 'UnitOfMeasure', $item->unit ?? 'UN');
                $this->text($line, 'UnitPrice', number_format((float) $item->unit_price, 2, '.', ''));

                // TaxPointDate com fallback para issued_at se delivered_at for null
                $taxPointDate = ($invoice->delivered_at ?? $invoice->issued_at)->format('Y-m-d');
                $this->text($line, 'TaxPointDate', $taxPointDate);
                $this->text($line, 'Description', $item->description);

                // R13/R14: DebitAmount/CreditAmount = net_amount (Qty × UnitPrice, sem IVA)
                if ($isCredit || (float) $item->net_amount < 0) {
                    $this->text($line, 'CreditAmount', number_format(abs((float) $item->net_amount), 2, '.', ''));
                } else {
                    $this->text($line, 'DebitAmount', number_format((float) $item->net_amount, 2, '.', ''));
                }

                // IVA por linha — obrigatório SAF-T
                $tax = $this->el('Tax', $line);
                $this->text($tax, 'TaxType', 'IVA');
                $this->text($tax, 'TaxCountryRegion', 'AO');
                // Mapeia para código válido XSD (RED5/RED7→RED, EXC→OUT)
                $lineTaxCode = $this->mapTaxCode($item->tax_code);
                $this->text($tax, 'TaxCode', $lineTaxCode);

                if ((float) $item->tax_rate > 0) {
                    $this->text($tax, 'TaxPercentage', number_format((float) $item->tax_rate, 2, '.', ''));
                } else {
                    $this->text($tax, 'TaxAmount', '0.00');
                    if ($item->tax_exemption_reason) {
                        $this->text($tax, 'TaxExemptionReason', $item->tax_exemption_reason);
                        $this->text($tax, 'TaxExemptionCode', $item->tax_code);
                    }
                }
            }

            // DocumentTotals — ordem XSD: TaxPayable → NetTotal → GrossTotal
            $totals = $this->el('DocumentTotals', $node);
            $this->text($totals, 'TaxPayable', number_format((float) $invoice->tax_amount, 2, '.', ''));
            $this->text($totals, 'NetTotal', number_format((float) $invoice->taxable_amount, 2, '.', ''));
            $this->text($totals, 'GrossTotal', number_format((float) $invoice->total_amount, 2, '.', ''));
            // TaxSummary não existe no XSD DocumentTotals — removido
        }

        $this->root->appendChild($sourceDocs);
    }

    // ═══════════════════════════════════════════════════════
    // 🔧 HELPERS DOM
    // ═══════════════════════════════════════════════════════

    private function el(string $tag, ?DOMElement $parent = null): DOMElement
    {
        $node = $this->xml->createElement($tag);
        if ($parent) {
            $parent->appendChild($node);
        }
        return $node;
    }

    private function text(DOMElement $parent, string $tag, string $value): void
    {
        $node = $this->xml->createElement($tag);
        $node->appendChild($this->xml->createTextNode($value));
        $parent->appendChild($node);
    }

    private function periodStart(): string
    {
        if ($this->month > 0) {
            return sprintf('%d-%02d-01', $this->year, $this->month);
        }
        return $this->year . '-01-01';
    }

    private function periodEnd(): string
    {
        if ($this->month > 0) {
            return \Carbon\Carbon::createFromDate($this->year, $this->month, 1)
                ->endOfMonth()
                ->format('Y-m-d');
        }
        return $this->year . '-12-31';
    }

    /**
     * Normaliza o número de factura para o padrão XSD: "[^ ]+ [^/^ ]+/[0-9]+"
     *
     * Padrão: "TIPO SERIE/NUMERO"
     *   - TIPO: sem espaços (ex: FT, NC, FR)
     *   - SERIE: sem barras nem espaços (ex: A2026, M001)
     *   - NUMERO: inteiro sem zeros à esquerda
     *
     * Exemplos de conversão:
     *   "FT A/2026/00001" → "FT A2026/1"
     *   "NC A/2026/00003" → "NC A2026/3"
     */
    private function normalizeInvoiceNo(string $invoiceNumber): string
    {
        // Formato interno: "TIPO SERIE.ANO/NNNNN" ou "TIPO SERIE/ANO/NNNNN"
        // Extrai: tipo (antes do primeiro espaço), resto
        if (!preg_match('/^([^ ]+) (.+)\/(\d+)$/', $invoiceNumber, $m)) {
            return $invoiceNumber;
        }

        $type = $m[1];                          // ex: FT
        $series = preg_replace('/[\/\s]/', '', $m[2]); // remove barras e espaços da série
        $number = (int) $m[3];                    // remove zeros à esquerda

        return "{$type} {$series}/{$number}";
    }

    /**
     * Mapeia tax_code interno para código válido no XSD SAF-T AO.
     * Padrão aceite: RED|INT|NOR|ISE|OUT|([0-9.])*|NS|NA
     */
    private function mapTaxCode(string $code): string
    {
        return match ($code) {
            'RED5', 'RED7' => 'RED',
            'EXC' => 'OUT',
            default => $code,
        };
    }
}