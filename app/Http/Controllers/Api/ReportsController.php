<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    // ═══════════════════════════════════════════════════════
    // 📊 RELATÓRIO DE VENDAS (baseado em facturas AGT)
    // O relatório oficial baseia-se nas FACTURAS, não nos pedidos.
    // Garante consistência com o SAF-T exportado para a AGT.
    // ═══════════════════════════════════════════════════════
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with([
            'order.payments',
            'order.refunds',
            'customer:id,name,tax_number',
            'user:id,name',
            'shift:id,terminal_id,opened_at',
            'items',
            'taxSummaries',
        ]);

        // ── Filtros ───────────────────────────────────────────

        if ($request->filled('status')) {
            $request->validate(['status' => 'in:draft,issued,cancelled,credited']);
            $query->where('status', $request->status);
        }

        if ($request->filled('document_type')) {
            $request->validate(['document_type' => 'in:FT,FR,ND,NC,VD,RC']);
            $query->where('document_type', $request->document_type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('method_payment')) {
            // FIX: era 'order.payments' em nested whereHas — sintaxe correcta é encadeada
            $query->whereHas(
                'order',
                fn($o) =>
                $o->whereHas(
                    'payments',
                    fn($p) =>
                    $p->where('method_payment', $request->method_payment)
                )
            );
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
                    )
                    ->orWhereHas(
                        'user',
                        fn($u) =>
                        $u->where('name', 'like', "%{$search}%")
                    )
                    ->orWhereHas(
                        'items',
                        fn($i) =>
                        $i->where('description', 'like', "%{$search}%")
                    );
            });
        }

        // ── Ordenação ─────────────────────────────────────────
        $allowed = ['id', 'issued_at', 'invoice_number', 'total_amount', 'status'];
        $sortBy = in_array($request->get('sort_by'), $allowed) ? $request->get('sort_by') : 'issued_at';
        $sortOrder = $request->get('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // ── Totais agregados (antes de paginar, excluindo canceladas) ─
        $totals = (clone $query)
            ->whereIn('status', ['issued', 'credited'])
            ->selectRaw('
                COUNT(*)                as total_invoices,
                SUM(total_amount)       as total_revenue,
                SUM(tax_amount)         as total_iva,
                SUM(taxable_amount)     as total_taxable,
                SUM(discount_amount)    as total_discount,
                SUM(paid_amount)        as total_paid
            ')->first();

        return response()->json([
            'success' => true,
            'totals' => $totals,
            'data' => $query->paginate($request->input('per_page', 10)),
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // 📋 RELATÓRIO DE PEDIDOS (visão operacional — não fiscal)
    // ═══════════════════════════════════════════════════════
    public function orders(Request $request): JsonResponse
    {
        // FIX: era Orders:: — renomeado para Order (singular)
        $query = Order::with([
            'items.product:id,name',
            'payments',
            'user:id,name',
            'shift:id,terminal_id,opened_at',
            'invoice:id,invoice_number,status,document_type',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }

        if ($request->filled('method_payment')) {
            $query->whereHas(
                'payments',
                fn($q) =>
                $q->where('method_payment', $request->method_payment)
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate('opened_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('opened_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas(
                        'items.product',
                        fn($p) =>
                        $p->where('name', 'like', "%{$search}%")
                    )
                    ->orWhereHas(
                        'user',
                        fn($u) =>
                        $u->where('name', 'like', "%{$search}%")
                    );
            });
        }

        $allowed = ['id', 'opened_at', 'closed_at', 'total', 'status'];
        $sortBy = in_array($request->get('sort_by'), $allowed) ? $request->get('sort_by') : 'opened_at';
        $sortOrder = $request->get('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $totals = (clone $query)->selectRaw('
            COUNT(*)        as total_orders,
            SUM(total)      as total_revenue,
            SUM(iva)        as total_iva,
            SUM(subtotal)   as total_subtotal,
            SUM(discount)   as total_discount
        ')->first();

        return response()->json([
            'success' => true,
            'totals' => $totals,
            'data' => $query->paginate($request->input('per_page', 10)),
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // 📈 RESUMO DO DIA (dashboard)
    // ═══════════════════════════════════════════════════════
    public function dailySummary(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());

        $invoices = Invoice::whereDate('issued_at', $date)
            ->whereIn('status', ['issued', 'credited'])
            ->selectRaw('
                COUNT(*)            as total_invoices,
                SUM(total_amount)   as total_revenue,
                SUM(tax_amount)     as total_iva,
                SUM(taxable_amount) as total_taxable
            ')->first();

        // Breakdown por método de pagamento
        $byPaymentMethod = \App\Models\Payment::whereDate('paid_at', $date)
            ->where('status', 'paid')
            ->selectRaw('method_payment, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('method_payment')
            ->get();

        // Breakdown por tipo de documento
        $byDocumentType = Invoice::whereDate('issued_at', $date)
            ->whereIn('status', ['issued', 'credited'])
            ->selectRaw('document_type, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('document_type')
            ->get();

        // Top 5 produtos do dia
        $topProducts = \App\Models\InvoiceItem::whereHas(
            'invoice',
            fn($q) =>
            $q->whereDate('issued_at', $date)->whereIn('status', ['issued', 'credited'])
        )
            ->selectRaw('description, product_code, SUM(quantity) as qty_sold, SUM(gross_amount) as total_sold')
            ->groupBy('description', 'product_code')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        return response()->json([
            'date' => $date,
            'invoices' => $invoices,
            'by_payment_method' => $byPaymentMethod,
            'by_document_type' => $byDocumentType,
            'top_products' => $topProducts,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // 📊 RESUMO POR TURNO
    // ═══════════════════════════════════════════════════════
    public function shiftSummary(int $shiftId): JsonResponse
    {
        $shift = \App\Models\Shift::with('user:id,name')->findOrFail($shiftId);

        $invoices = Invoice::where('shift_id', $shiftId)
            ->whereIn('status', ['issued', 'credited'])
            ->selectRaw('
                COUNT(*)            as total_invoices,
                SUM(total_amount)   as total_revenue,
                SUM(tax_amount)     as total_iva,
                SUM(taxable_amount) as total_taxable,
                SUM(discount_amount) as total_discount
            ')->first();

        $byPaymentMethod = \App\Models\Payment::where('shift_id', $shiftId)
            ->where('status', 'paid')
            ->selectRaw('method_payment, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('method_payment')
            ->get();

        $taxBreakdown = \App\Models\InvoiceTaxSummary::whereHas(
            'invoice',
            fn($q) =>
            $q->where('shift_id', $shiftId)->whereIn('status', ['issued', 'credited'])
        )
            ->selectRaw('tax_code, tax_rate, SUM(taxable_amount) as taxable, SUM(tax_amount) as iva')
            ->groupBy('tax_code', 'tax_rate')
            ->orderBy('tax_rate', 'desc')
            ->get();

        return response()->json([
            'shift' => $shift,
            'invoices' => $invoices,
            'by_payment_method' => $byPaymentMethod,
            'tax_breakdown' => $taxBreakdown,
        ]);
    }
}