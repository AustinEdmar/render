<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// FIX: renomeado de ShiftsController para ShiftController — convenção Laravel
class ShiftsController extends Controller
{
    // ═══════════════════════════════════════════════════════
    // 🟢 ABRIR TURNO
    // ═══════════════════════════════════════════════════════
    public function open(Request $request): JsonResponse
    {
        $request->validate([
            'initial_amount' => 'required|numeric|min:0',
            'terminal_id' => 'nullable|string|max:20',
        ]);

        $user = Auth::user();

        $existingShift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($existingShift) {
            return response()->json([
                'message' => 'Já existe um turno aberto.',
                'shift' => $existingShift,
            ], 400);
        }

        $shift = Shift::create([
            'user_id' => $user->id,
            'initial_amount' => $request->initial_amount,
            'terminal_id' => $request->input('terminal_id', 'A'),
            'status' => 'open',
            'opened_at' => now(),
        ]);

        return response()->json($shift, 201);
    }

    // ═══════════════════════════════════════════════════════
    // 🔴 FECHAR TURNO
    // ═══════════════════════════════════════════════════════
    public function close(Request $request): JsonResponse
    {
        $request->validate([
            'final_cash_amount' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();

        $shift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (!$shift) {
            return response()->json(['message' => 'Nenhum turno aberto encontrado.'], 404);
        }

        $openOrders = Order::where('shift_id', $shift->id)
            ->where('status', 'open')
            ->exists();

        if ($openOrders) {
            return response()->json([
                'message' => 'Existem pedidos abertos neste turno. Feche todos os pedidos primeiro.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Vendas em dinheiro (paid + partial_refund)
            $cashSales = Payment::where('shift_id', $shift->id)
                ->where('method_payment', 'cash')
                ->whereIn('status', ['paid', 'partial_refund'])
                ->sum('amount');

            // Reembolsos em dinheiro — FIX: whereHas correcto com shift do payment
            $cashRefunds = Refund::whereHas('payment', function ($q) use ($shift) {
                $q->where('shift_id', $shift->id)
                    ->where('method_payment', 'cash');
            })->where('shift_id', $shift->id)->sum('amount');
            // FIX: adicionado ->where('shift_id', $shift->id) para garantir que
            // o refund pertence ao turno actual, não apenas o payment

            // Entradas e saídas manuais
            $totalInflow = CashMovement::where('shift_id', $shift->id)->where('type', 'inflow')->sum('amount');
            $totalOutflow = CashMovement::where('shift_id', $shift->id)->where('type', 'outflow')->sum('amount');

            // Valor esperado no caixa
            $expected = $shift->initial_amount + $cashSales - $cashRefunds + $totalInflow - $totalOutflow;
            $difference = $request->final_cash_amount - $expected;

            // Totais gerais do turno (todas as formas de pagamento)
            $grossSales = Payment::where('shift_id', $shift->id)->where('status', 'paid')->sum('amount');
            $refundTotal = Refund::where('shift_id', $shift->id)->sum('amount');

            $shift->update([
                'gross_sales' => $grossSales,
                'refund_total' => $refundTotal,
                'net_sales' => $grossSales - $refundTotal,
                'expected_cash_amount' => $expected,
                'final_cash_amount' => $request->final_cash_amount,
                'difference' => $difference,
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Turno fechado com sucesso.',
                'gross_sales' => $grossSales,
                'refund_total' => $refundTotal,
                'net_sales' => $grossSales - $refundTotal,
                'expected_cash' => $expected,
                'final_cash' => $request->final_cash_amount,
                'difference' => $difference,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erro ao fechar turno.', 'error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 📋 TURNO ACTUAL DO UTILIZADOR AUTENTICADO
    // ═══════════════════════════════════════════════════════
    public function current(): JsonResponse
    {
        $user = Auth::user();

        $shift = Shift::with('user:id,name')
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (!$shift) {
            return response()->json(['message' => 'Nenhum turno aberto.'], 404);
        }

        return response()->json($shift);
    }

    // ═══════════════════════════════════════════════════════
    // 📊 LISTAR TODOS OS TURNOS (histórico paginado)
    // ═══════════════════════════════════════════════════════
    public function index(Request $request): JsonResponse
    {
        $shifts = Shift::with('user:id,name')
            ->withCount('orders')
            ->latest('opened_at')
            ->paginate($request->input('per_page', 20));

        return response()->json($shifts);
    }

    // ═══════════════════════════════════════════════════════
    // 🔍 DETALHE DE UM TURNO ESPECÍFICO
    // ═══════════════════════════════════════════════════════
    public function show(int $id): JsonResponse
    {
        $shift = Shift::with([
            'user:id,name',
            'orders.payments',
            'orders.refunds',
            'payments',
            'cashMovements',
        ])->findOrFail($id);

        return response()->json($shift);
    }
}