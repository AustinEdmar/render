<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashMovementController extends Controller
{
    // ═══════════════════════════════════════════════════════
    // 💵 REGISTAR MOVIMENTO (sangria ou reforço)
    // ═══════════════════════════════════════════════════════
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:inflow,outflow',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3',
        ]);

        $user = Auth::user();

        // FIX: era Shifts:: — renomeado para Shift (singular)
        $shift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (!$shift) {
            return response()->json([
                'message' => 'Nenhum turno aberto. Abra o caixa primeiro.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            // FIX: era Cashmovement:: — renomeado para CashMovement (PascalCase)
            $movement = CashMovement::create([
                'shift_id' => $shift->id,
                'user_id' => $user->id,
                'type' => $request->type,
                'amount' => $request->amount,
                'currency' => $request->input('currency', 'AOA'),
                'reason' => $request->reason,
            ]);

            DB::commit();

            return response()->json([
                'message' => $request->type === 'inflow'
                    ? 'Reforço de caixa registado com sucesso.'
                    : 'Sangria registada com sucesso.',
                'movement' => $movement,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 📋 MOVIMENTOS DO TURNO ACTUAL
    // ═══════════════════════════════════════════════════════
    public function current(): JsonResponse
    {
        $user = Auth::user();

        $shift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (!$shift) {
            return response()->json(['message' => 'Nenhum turno aberto.'], 404);
        }

        $movements = CashMovement::where('shift_id', $shift->id)
            ->latest()
            ->get();

        $totalInflow = $movements->where('type', 'inflow')->sum('amount');
        $totalOutflow = $movements->where('type', 'outflow')->sum('amount');

        return response()->json([
            'shift_id' => $shift->id,
            'total_inflow' => $totalInflow,
            'total_outflow' => $totalOutflow,
            'net' => $totalInflow - $totalOutflow,
            'movements' => $movements,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // 📊 MOVIMENTOS DE UM TURNO ESPECÍFICO
    // ═══════════════════════════════════════════════════════
    public function byShift(int $shiftId): JsonResponse
    {
        $shift = Shift::findOrFail($shiftId);

        $movements = CashMovement::with('user:id,name')
            ->where('shift_id', $shift->id)
            ->latest()
            ->get();

        $totalInflow = $movements->where('type', 'inflow')->sum('amount');
        $totalOutflow = $movements->where('type', 'outflow')->sum('amount');

        return response()->json([
            'shift_id' => $shift->id,
            'opened_at' => $shift->opened_at,
            'closed_at' => $shift->closed_at,
            'status' => $shift->status,
            'total_inflow' => $totalInflow,
            'total_outflow' => $totalOutflow,
            'net' => $totalInflow - $totalOutflow,
            'movements' => $movements,
        ]);
    }
}