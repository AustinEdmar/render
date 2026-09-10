<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class HomeBoardController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();

        // ── Turno actual ──────────────────────────────────────
        // FIX: era Shifts:: — renomeado para Shift (singular)
        $shift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->with('cashMovements')
            ->first();

        // ── Pedidos abertos no turno actual ───────────────────
        // FIX: era Orders:: — renomeado para Order (singular)
        $openOrders = Order::with([
            'items.product:id,name,image_path,stock',
            'payments:id,order_id,method_payment,amount,status',
            'user:id,name',
        ])
            ->where('status', 'open')
            ->when($shift, fn($q) => $q->where('shift_id', $shift->id))
            ->get();

        // ── Resumo do turno para o dashboard ──────────────────
        $summary = null;

        if ($shift) {
            // Vendas do turno por método de pagamento
            $paymentBreakdown = Payment::where('shift_id', $shift->id)
                ->where('status', 'paid')
                ->selectRaw('method_payment, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('method_payment')
                ->get();

            // Últimas 5 facturas emitidas no turno
            $recentInvoices = Invoice::where('shift_id', $shift->id)
                ->whereIn('status', ['issued', 'credited'])
                ->with('customer:id,name,tax_number')
                ->orderByDesc('issued_at')
                ->limit(5)
                ->get(['id', 'invoice_number', 'document_type', 'total_amount', 'issued_at', 'customer_id', 'status']);

            $summary = [
                'shift_id' => $shift->id,
                'opened_at' => $shift->opened_at,
                'terminal_id' => $shift->terminal_id,
                'initial_amount' => $shift->initial_amount,
                'gross_sales' => $shift->gross_sales,
                'refund_total' => $shift->refund_total,
                'net_sales' => $shift->net_sales,
                'open_orders_count' => $openOrders->count(),
                // FIX: campo threshold parametrizado — era hardcoded 5
                'low_stock_count' => Product::active()->lowStock(5)->count(),
                'payment_breakdown' => $paymentBreakdown,
                'recent_invoices' => $recentInvoices,
            ];
        }

        return response()->json([
            'success' => true,
            'shift' => $summary,
            'data' => $openOrders,
        ]);
    }
}