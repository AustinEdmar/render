<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SubmitInvoiceToAgt;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{

    // ═══════════════════════════════════════════════════════
    // 📋 LISTAR FACTURAS (com filtros)
    // ═══════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $query = Invoice::with([
            'customer:id,name,tax_number',
            'user:id,name',
            'shift:id,terminal_id,opened_at',
            'taxSummaries',
        ]);

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('series')) {
            $query->where('series', $request->series);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('issued_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('issued_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas(
                        'customer',
                        fn($c) =>
                        $c->where('name', 'like', "%{$search}%")
                            ->orWhere('tax_number', 'like', "%{$search}%")
                    );
            });
        }

        $sortBy = $request->get('sort_by', 'issued_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowed = ['id', 'issued_at', 'invoice_number', 'total_amount', 'status'];

        if (in_array($sortBy, $allowed)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $totals = (clone $query)->selectRaw("
            COUNT(*)             as total_invoices,
            SUM(total_amount)    as total_revenue,
            SUM(tax_amount)      as total_iva,
            SUM(taxable_amount)  as total_taxable,
            SUM(discount_amount) as total_discount
        ")->first();

        return response()->json([
            'success' => true,
            'totals' => $totals,
            'data' => $query->paginate($request->input('per_page', 15)),
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // 🔍 VER UMA FACTURA COMPLETA
    // ═══════════════════════════════════════════════════════
    public function show($id)
    {
        $invoice = Invoice::with([
            'customer',
            'user:id,name',
            'shift:id,terminal_id,opened_at,closed_at',
            'order.payments',
            'items',
            'taxSummaries',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // ❌ ANULAR FACTURA (nunca eliminar — gera nota de crédito)
    // Regra AGT: facturas não podem ser apagadas, só anuladas.
    // A anulação gera automaticamente uma Nota de Crédito (NC).
    // ═══════════════════════════════════════════════════════
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $invoice = Invoice::with(['items', 'taxSummaries'])->findOrFail($id);

            if ($invoice->status === 'cancelled') {
                return response()->json(['message' => 'Factura já está anulada.'], 400);
            }

            if ($invoice->status === 'credited') {
                return response()->json(['message' => 'Factura já tem nota de crédito emitida.'], 400);
            }

            $series = $invoice->series;
            $year = now()->year;

            $seq = InvoiceSequence::where('document_type', 'NC')
                ->where('series', $series)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$seq) {
                $seq = InvoiceSequence::create([
                    'document_type' => 'NC',
                    'series' => $series,
                    'year' => $year,
                    'last_number' => 0,
                ]);
            }

            $number = $seq->last_number + 1;
            $seq->update(['last_number' => $number]);

            $ncNumber = sprintf('NC %s.%d/%05d', $series, $year, $number);
            $now = now();

            $creditNote = Invoice::create([
                'order_id' => $invoice->order_id,
                'customer_id' => $invoice->customer_id,
                'user_id' => Auth::id(),
                'shift_id' => $invoice->shift_id,
                'document_type' => 'NC',
                'series' => $series,
                'sequence_number' => $number,
                'invoice_number' => $ncNumber,
                'taxable_amount' => -$invoice->taxable_amount,
                'tax_amount' => -$invoice->tax_amount,
                'total_amount' => -$invoice->total_amount,
                'discount_amount' => -$invoice->discount_amount,
                'paid_amount' => -$invoice->paid_amount,
                'currency' => $invoice->currency,
                'status' => 'issued',
                'credit_note_id' => $invoice->id,
                'reference_reason' => $request->reason,
                'issued_at' => $now,
                'delivered_at' => $now,
                'notes' => 'Anulação de ' . $invoice->invoice_number . '. Motivo: ' . $request->reason,
            ]);

            $previousHash = Invoice::where('document_type', 'NC')
                ->where('series', $series)
                ->where('sequence_number', '<', $number)
                ->orderByDesc('sequence_number')
                ->value('hash') ?? '';

            $hashData = implode(';', [
                $creditNote->issued_at->format('Y-m-d'),
                $now->format('Y-m-d\TH:i:s'),
                $creditNote->invoice_number,
                number_format(abs((float) $creditNote->total_amount), 2, '.', ''),
                $previousHash,
            ]);

            $creditNote->update([
                'hash' => base64_encode(hash('sha256', $hashData)),
                'hash_control' => (string) $number,
            ]);

            foreach ($invoice->items as $item) {
                $creditNote->items()->create([
                    'product_id' => $item->product_id,
                    'order_item_id' => $item->order_item_id,
                    'description' => $item->description,
                    'product_code' => $item->product_code,
                    'unit' => $item->unit,
                    'quantity' => -$item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'discount_amount' => -$item->discount_amount,
                    'tax_rate' => $item->tax_rate,
                    'tax_code' => $item->tax_code,
                    'tax_exemption_reason' => $item->tax_exemption_reason,
                    'net_amount' => -$item->net_amount,
                    'tax_amount' => -$item->tax_amount,
                    'gross_amount' => -$item->gross_amount,
                ]);
            }

            foreach ($invoice->taxSummaries as $summary) {
                $creditNote->taxSummaries()->create([
                    'tax_rate_id' => $summary->tax_rate_id,
                    'tax_code' => $summary->tax_code,
                    'tax_rate' => $summary->tax_rate,
                    'taxable_amount' => -$summary->taxable_amount,
                    'tax_amount' => -$summary->tax_amount,
                    'tax_exemption_reason' => $summary->tax_exemption_reason,
                ]);
            }

            $invoice->update([
                'status' => 'credited',
                'credit_note_id' => $creditNote->id,
                'notes' => ($invoice->notes ? $invoice->notes . ' | ' : '')
                    . 'Anulada. NC: ' . $ncNumber . '. Motivo: ' . $request->reason,
            ]);

            DB::commit();

            // NOVO: dispara a submissão real à AGT (fila) — antes, nada fazia
            // isto e a NC ficava só na base de dados local, sem nunca ser
            // enviada. buildDocument() no FeInvoiceService já sabe tratar
            // 'NC' correctamente: usa creditedInvoices() para encontrar a
            // factura original (via este mesmo credit_note_id que acabámos
            // de definir) e monta o referenceInfo/débito por linha.
            SubmitInvoiceToAgt::dispatch($creditNote);

            return response()->json([
                'success' => true,
                'message' => 'Factura anulada. Nota de crédito emitida.',
                'original_invoice' => $invoice->invoice_number,
                'credit_note_number' => $ncNumber,
                'credit_note_id' => $creditNote->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // ➕ NOTA DE DÉBITO — adiciona encargo(s) a uma factura já emitida
    // Espelha cancel()/NC, mas SEM negar valores (funciona como uma
    // venda normal do ponto de vista de debitAmount/creditAmount) e
    // SEM alterar o status da factura original (um ND não a anula).
    //
    // POST /api/invoices/{id}/debit-note
    // body: { reason: string, items: [{ description, quantity,
    //          unit_price, tax_rate, tax_code, product_id? }] }
    // ═══════════════════════════════════════════════════════
    public function debitNote(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'required|numeric|min:0',
            'items.*.tax_code' => 'required|string|max:10',
            'items.*.product_id' => 'nullable|exists:products,id',
        ]);

        DB::beginTransaction();

        try {
            $invoice = Invoice::findOrFail($id);

            if (!in_array($invoice->status, ['issued', 'credited'], true)) {
                DB::rollBack();
                return response()->json(['message' => 'Só é possível emitir ND sobre uma factura emitida.'], 400);
            }

            $series = $invoice->series;
            $year = now()->year;

            $seq = InvoiceSequence::where('document_type', 'ND')
                ->where('series', $series)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$seq) {
                $seq = InvoiceSequence::create([
                    'document_type' => 'ND',
                    'series' => $series,
                    'year' => $year,
                    'last_number' => 0,
                ]);
            }

            $number = $seq->last_number + 1;
            $seq->update(['last_number' => $number]);

            $ndNumber = sprintf('ND %s.%d/%05d', $series, $year, $number);
            $now = now();

            $itemsInput = collect($request->items)->map(function ($i) {
                $subtotal = round($i['quantity'] * $i['unit_price'], 2);
                $taxAmount = round($subtotal * ($i['tax_rate'] / 100), 2);

                return array_merge($i, [
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'gross_amount' => $subtotal + $taxAmount,
                ]);
            });

            $taxableAmount = $itemsInput->sum('subtotal');
            $taxAmount = $itemsInput->sum('tax_amount');
            $totalAmount = $itemsInput->sum('gross_amount');

            $debitNote = Invoice::create([
                'order_id' => $invoice->order_id,
                'customer_id' => $invoice->customer_id,
                'user_id' => Auth::id(),
                'shift_id' => $invoice->shift_id,
                'document_type' => 'ND',
                'series' => $series,
                'sequence_number' => $number,
                'invoice_number' => $ndNumber,
                'taxable_amount' => $taxableAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'discount_amount' => 0,
                'paid_amount' => 0,
                'currency' => $invoice->currency,
                'status' => 'issued',
                'debit_note_id' => $invoice->id, // referência à factura base
                'reference_reason' => $request->reason,
                'issued_at' => $now,
                'delivered_at' => $now,
                'notes' => 'Encargo adicional sobre ' . $invoice->invoice_number . '. Motivo: ' . $request->reason,
            ]);

            $previousHash = Invoice::where('document_type', 'ND')
                ->where('series', $series)
                ->where('sequence_number', '<', $number)
                ->orderByDesc('sequence_number')
                ->value('hash') ?? '';

            $hashData = implode(';', [
                $debitNote->issued_at->format('Y-m-d'),
                $now->format('Y-m-d\TH:i:s'),
                $debitNote->invoice_number,
                number_format($totalAmount, 2, '.', ''),
                $previousHash,
            ]);

            $debitNote->update([
                'hash' => base64_encode(hash('sha256', $hashData)),
                'hash_control' => (string) $number,
            ]);

            foreach ($itemsInput as $item) {
                $debitNote->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'order_item_id' => null,
                    'description' => $item['description'],
                    'product_code' => $item['product_code'] ?? null,
                    'unit' => $item['unit'] ?? 'UN',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'tax_rate' => $item['tax_rate'],
                    'tax_code' => $item['tax_code'],
                    'tax_exemption_reason' => $item['tax_exemption_reason'] ?? null,
                    'net_amount' => $item['subtotal'],
                    'tax_amount' => $item['tax_amount'],
                    'gross_amount' => $item['gross_amount'],
                ]);
            }

            $taxGroups = $itemsInput->groupBy(fn($i) => $i['tax_code'] . '_' . $i['tax_rate']);
            foreach ($taxGroups as $groupItems) {
                $first = $groupItems->first();
                $debitNote->taxSummaries()->create([
                    'tax_code' => $first['tax_code'],
                    'tax_rate' => $first['tax_rate'],
                    'taxable_amount' => $groupItems->sum('subtotal'),
                    'tax_amount' => $groupItems->sum('tax_amount'),
                    'tax_exemption_reason' => $first['tax_exemption_reason'] ?? null,
                ]);
            }

            $invoice->update(['debit_note_id' => $debitNote->id]);

            DB::commit();

            SubmitInvoiceToAgt::dispatch($debitNote);

            return response()->json([
                'success' => true,
                'message' => 'Nota de débito emitida.',
                'original_invoice' => $invoice->invoice_number,
                'debit_note_number' => $ndNumber,
                'debit_note_id' => $debitNote->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 🧾 EMITIR RECIBO (RC/RG) — liquida 1+ facturas já emitidas
    //
    // ATENÇÃO — limitação do schema actual: 'order_id' e 'shift_id' são
    // NOT NULL/FK obrigatórias na tabela invoices. Um recibo pode em teoria
    // liquidar facturas de pedidos diferentes; por omissão uso o order_id/
    // shift_id da PRIMEIRA factura da lista. Se isto não servir o teu caso
    // de uso real, vale a pena tornar essas colunas nullable numa migration
    // futura especificamente para documentos RC/RG.
    //
    // POST /api/invoices/receipt
    // body: { document_type: 'RC'|'RG', settlements: [{ invoice_id, amount_paid }] }
    // ═══════════════════════════════════════════════════════
    public function receipt(Request $request)
    {
        $request->validate([
            'document_type' => 'required|in:RC,RG',
            'settlements' => 'required|array|min:1',
            'settlements.*.invoice_id' => 'required|exists:invoices,id',
            'settlements.*.amount_paid' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();

        try {
            $settlements = collect($request->settlements);
            $invoices = Invoice::whereIn('id', $settlements->pluck('invoice_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($settlements as $s) {
                $inv = $invoices->get($s['invoice_id']);

                if (!$inv) {
                    DB::rollBack();
                    return response()->json(['message' => "Factura #{$s['invoice_id']} não encontrada."], 404);
                }

                if (!in_array($inv->status, ['issued', 'credited'], true)) {
                    DB::rollBack();
                    return response()->json(['message' => "Factura {$inv->invoice_number} não está emitida."], 400);
                }

                if (!$inv->agt_document_no) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Factura {$inv->invoice_number} ainda não tem agt_document_no "
                            . '(precisa de ter sido submetida e aceite pela AGT antes de gerar um recibo).',
                    ], 400);
                }

                if ($s['amount_paid'] > $inv->amount_outstanding + 0.01) { // pequena tolerância de arredondamento
                    DB::rollBack();
                    return response()->json([
                        'message' => "Valor a pagar ({$s['amount_paid']}) excede o saldo em aberto "
                            . "de {$inv->invoice_number} ({$inv->amount_outstanding}).",
                    ], 400);
                }
            }

            $firstInvoice = $invoices->get($settlements->first()['invoice_id']);
            $documentType = $request->document_type;
            $series = $firstInvoice->series;
            $year = now()->year;

            $seq = InvoiceSequence::where('document_type', $documentType)
                ->where('series', $series)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$seq) {
                $seq = InvoiceSequence::create([
                    'document_type' => $documentType,
                    'series' => $series,
                    'year' => $year,
                    'last_number' => 0,
                ]);
            }

            $number = $seq->last_number + 1;
            $seq->update(['last_number' => $number]);

            $receiptNumber = sprintf('%s %s.%d/%05d', $documentType, $series, $year, $number);
            $now = now();
            $totalPaid = $settlements->sum('amount_paid');

            $receipt = Invoice::create([
                'order_id' => $firstInvoice->order_id, // ver nota de limitação acima
                'customer_id' => $firstInvoice->customer_id,
                'user_id' => Auth::id(),
                'shift_id' => $firstInvoice->shift_id,
                'document_type' => $documentType,
                'series' => $series,
                'sequence_number' => $number,
                'invoice_number' => $receiptNumber,
                'taxable_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $totalPaid,
                'discount_amount' => 0,
                'paid_amount' => $totalPaid,
                'currency' => $firstInvoice->currency,
                'status' => 'issued',
                'issued_at' => $now,
                'delivered_at' => $now,
                'notes' => 'Recibo referente a ' . $settlements->count() . ' factura(s).',
            ]);

            $previousHash = Invoice::where('document_type', $documentType)
                ->where('series', $series)
                ->where('sequence_number', '<', $number)
                ->orderByDesc('sequence_number')
                ->value('hash') ?? '';

            $hashData = implode(';', [
                $receipt->issued_at->format('Y-m-d'),
                $now->format('Y-m-d\TH:i:s'),
                $receipt->invoice_number,
                number_format($totalPaid, 2, '.', ''),
                $previousHash,
            ]);

            $receipt->update([
                'hash' => base64_encode(hash('sha256', $hashData)),
                'hash_control' => (string) $number,
            ]);

            foreach ($settlements as $s) {
                $inv = $invoices->get($s['invoice_id']);

                $receipt->paidInvoices()->attach($inv->id, ['amount_paid' => $s['amount_paid']]);

                $inv->update(['paid_amount' => (float) $inv->paid_amount + $s['amount_paid']]);
            }

            DB::commit();

            // buildPaymentReceiptDocument() no FeInvoiceService lê
            // $receipt->paidInvoices() (que acabámos de popular) e monta
            // 'paymentReceipt.sourceDocuments' a partir daí.
            SubmitInvoiceToAgt::dispatch($receipt);

            return response()->json([
                'success' => true,
                'message' => 'Recibo emitido.',
                'receipt_number' => $receiptNumber,
                'receipt_id' => $receipt->id,
                'total_paid' => $totalPaid,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 🖨️ DADOS PARA IMPRESSÃO / REIMPRESSÃO
    // ═══════════════════════════════════════════════════════
    public function print($id)
    {
        $invoice = Invoice::with([
            'customer',
            'user:id,name',
            'shift:id,terminal_id',
            'items',
            'taxSummaries',
            'order.payments',
        ])->findOrFail($id);

        $company = \App\Models\Company::first();

        $deliveryDate = ($invoice->delivered_at ?? $invoice->issued_at)->format('d/m/Y');

        return response()->json([
            'success' => true,
            'data' => [
                'invoice' => $invoice,
                'company' => $company,
                'legal_text' => sprintf(
                    'Os bens/serviços foram colocados à disposição do adquirente/prestados em %s.',
                    $deliveryDate
                ),
                'footer_text' => sprintf(
                    'Processado por programa validado %s | %s',
                    $company?->certificate_number ?? '',
                    $company?->certificate_issuer ?? ''
                ),
                'operator_name' => $invoice->user->name,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // 📊 EXPORTAÇÃO SAF-T
    // ═══════════════════════════════════════════════════════
    public function saftExport(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $company = \App\Models\Company::first();

        if (!$company) {
            return response()->json(['message' => 'Dados da empresa não configurados.'], 400);
        }

        $invoices = Invoice::with(['customer', 'user', 'items', 'taxSummaries'])
            ->whereYear('issued_at', $request->year)
            ->whereMonth('issued_at', $request->month)
            ->whereIn('status', ['issued', 'credited'])
            ->orderBy('document_type')
            ->orderBy('series')
            ->orderBy('sequence_number')
            ->get();

        $totals = $invoices->groupBy('document_type')->map(fn($group) => [
            'count' => $group->count(),
            'total_debit' => $group->where('total_amount', '>', 0)->sum('total_amount'),
            'total_credit' => abs($group->where('total_amount', '<', 0)->sum('total_amount')),
            'total_tax' => $group->sum('tax_amount'),
        ]);

        return response()->json([
            'success' => true,
            'period' => sprintf('%04d-%02d', $request->year, $request->month),
            'company' => $company,
            'totals' => $totals,
            'invoice_count' => $invoices->count(),
            'invoices' => $invoices,
        ]);
    }
}