<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SubmitInvoiceToAgt;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSequence;
use App\Models\InvoiceTaxSummary;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\Shift;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // ═══════════════════════════════════════════════════════
    // 🟢 ABRIR PEDIDO
    // ═══════════════════════════════════════════════════════
    public function open(Request $request): JsonResponse
    {
        $user = Auth::user();

        $shift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (!$shift) {
            return response()->json(['message' => 'Abra o caixa antes de iniciar um pedido.'], 403);
        }

        $existingOrder = Order::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($existingOrder) {
            return response()->json([
                'message' => 'Já existe um pedido aberto.',
                'order' => $existingOrder,
            ], 409);
        }

        $order = Order::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'status' => 'open',
            'subtotal' => 0,
            'iva' => 0,
            'discount' => 0,
            'total' => 0,
            'opened_at' => now(),
        ]);

        return response()->json($order, 201);
    }

    // ═══════════════════════════════════════════════════════
    // 📋 LISTAR PEDIDOS ABERTOS
    // ═══════════════════════════════════════════════════════
    public function getOrders(): JsonResponse
    {
        $orders = Order::with('items.product:id,name,image_path', 'user:id,name')
            ->where('status', 'open')
            ->latest()
            ->get();

        return response()->json($orders);
    }

    // ═══════════════════════════════════════════════════════
    // 📊 HISTÓRICO DE VENDAS
    // ═══════════════════════════════════════════════════════
    public function getSales(Request $request): JsonResponse
    {
        $query = Order::with('items.product:id,name', 'payments', 'shift:id,terminal_id', 'refunds', 'user:id,name', 'invoice:id,invoice_number,status');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('method_payment')) {
            $query->whereHas(
                'payments',
                fn($q) =>
                $q->where('method_payment', $request->method_payment)
            );
        }

        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas(
                        'invoice',
                        fn($i) =>
                        $i->where('invoice_number', 'like', "%{$search}%")
                    )
                    ->orWhereHas(
                        'user',
                        fn($u) =>
                        $u->where('name', 'like', "%{$search}%")
                    )
                    ->orWhereHas(
                        'items.product',
                        fn($p) =>
                        $p->where('name', 'like', "%{$search}%")
                    );
            });
        }

        return response()->json(
            $query->latest()->paginate($request->input('per_page', 10))
        );
    }

    // ═══════════════════════════════════════════════════════
    // ➕ ADICIONAR ITEM AO PEDIDO
    // ═══════════════════════════════════════════════════════
    public function addItem(Request $request, int $orderId): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            $order = Order::where('id', $orderId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->firstOrFail();

            // FIX: eager load taxRate para evitar query extra e aceder a tax_code e tax_percentage
            $product = Product::with('taxRate')
                ->lockForUpdate()
                ->findOrFail($request->product_id);

            if (!$product->is_active) {
                DB::rollBack();
                return response()->json(['message' => 'Produto inactivo.'], 400);
            }

            if ($product->stock < $request->quantity) {
                DB::rollBack();
                return response()->json(['message' => 'Stock insuficiente.'], 400);
            }

            // FIX: iva_rate e tax_code lidos via relação taxRate — campo 'iva' não existe
            $ivaRate = (int) ($product->taxRate?->tax_percentage ?? 0);
            $taxCode = $product->taxRate?->tax_code ?? 'ISE';
            $exemption = $product->tax_exemption_reason
                ?? $product->taxRate?->exemption_reason;

            $item = OrderItem::where('order_id', $order->id)
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($item) {
                $newQty = $item->quantity + $request->quantity;
                $subtotal = round($newQty * $item->unit_price, 2);
                $ivaAmount = round($subtotal * ($item->iva_rate / 100), 2);

                $item->update([
                    'quantity' => $newQty,
                    'subtotal' => $subtotal,
                    'iva_amount' => $ivaAmount,
                    'total_with_iva' => $subtotal + $ivaAmount,
                ]);
            } else {
                $subtotal = round($product->price * $request->quantity, 2);
                $ivaAmount = round($subtotal * ($ivaRate / 100), 2);

                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_code' => $product->product_code,
                    'unit' => $product->unit ?? 'UN',
                    'quantity' => $request->quantity,
                    'unit_price' => $product->price,
                    'iva_rate' => $ivaRate,
                    'tax_code' => $taxCode,
                    'tax_exemption_reason' => $exemption,
                    'iva_amount' => $ivaAmount,
                    'subtotal' => $subtotal,
                    'total_with_iva' => $subtotal + $ivaAmount,
                ]);
            }

            $stockBefore = $product->stock;
            $product->decrement('stock', $request->quantity);

            StockMovement::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'type' => 'sale',
                'quantity' => -$request->quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $product->fresh()->stock,
                'reference_type' => OrderItem::class,
                'reference_id' => $item->id,
                'note' => 'Venda — Pedido #' . $orderId,
            ]);

            $this->recalcOrder($order);
            DB::commit();

            return response()->json([
                'message' => 'Produto adicionado com sucesso.',
                'item' => $item,
                'resumo' => $this->orderSummary($order),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // ➖ DECREMENTAR ITEM
    // ═══════════════════════════════════════════════════════
    public function decrementItem(Request $request, int $orderId): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'integer|min:1',
        ]);

        $qty = $request->input('quantity', 1);

        DB::beginTransaction();

        try {
            $order = Order::where('id', $orderId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->firstOrFail();

            $item = OrderItem::where('order_id', $orderId)
                ->where('product_id', $request->product_id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $product = Product::lockForUpdate()->findOrFail($item->product_id);
            $stockBefore = $product->stock;

            if ($item->quantity > $qty) {
                $newQty = $item->quantity - $qty;
                $subtotal = round($newQty * $item->unit_price, 2);
                $ivaAmount = round($subtotal * ($item->iva_rate / 100), 2);

                $item->update([
                    'quantity' => $newQty,
                    'subtotal' => $subtotal,
                    'iva_amount' => $ivaAmount,
                    'total_with_iva' => $subtotal + $ivaAmount,
                ]);
            } else {
                $qty = $item->quantity;
                $item->delete();

                if ($order->items()->count() === 0) {
                    $order->delete();
                    DB::commit();
                    return response()->json(['message' => 'Pedido cancelado (sem itens).']);
                }
            }

            $product->increment('stock', $qty);

            StockMovement::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'type' => 'adjustment',
                'quantity' => $qty,
                'stock_before' => $stockBefore,
                'stock_after' => $product->fresh()->stock,
                'note' => 'Item decrementado no pedido #' . $orderId,
            ]);

            $this->recalcOrder($order);
            DB::commit();

            return response()->json([
                'message' => 'Item decrementado.',
                'resumo' => $this->orderSummary($order),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 🗑️ REMOVER ITEM COMPLETO
    // ═══════════════════════════════════════════════════════
    public function removeItem(int $itemId): JsonResponse
    {
        DB::beginTransaction();

        try {
            $item = OrderItem::lockForUpdate()->findOrFail($itemId);
            $order = $item->order;

            if ($order->status !== 'open') {
                DB::rollBack();
                return response()->json(['message' => 'Pedido já fechado.'], 400);
            }

            $product = Product::lockForUpdate()->findOrFail($item->product_id);
            $stockBefore = $product->stock;

            $product->increment('stock', $item->quantity);

            StockMovement::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'type' => 'adjustment',
                'quantity' => $item->quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $product->fresh()->stock,
                'note' => 'Item removido do pedido #' . $order->id,
            ]);

            $item->delete();

            if ($order->items()->count() === 0) {
                $order->delete();
                DB::commit();
                return response()->json(['message' => 'Pedido cancelado (sem itens).']);
            }

            $this->recalcOrder($order);
            DB::commit();

            return response()->json([
                'message' => 'Item removido com sucesso.',
                'resumo' => $this->orderSummary($order),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 💳 FECHAR PEDIDO + GERAR FACTURA AGT
    // ═══════════════════════════════════════════════════════
    public function close(Request $request, int $orderId): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|in:cash,card,QrCode,BankTransfer,multicaixa',
            'received' => 'nullable|numeric|min:0',
            'change' => 'nullable|numeric|min:0',
            //  'document_type' => 'nullable|in:FT,FR,NC,ND,TV,RC,RG',
            'document_type' => 'nullable|in:FT,FR,TV',
            'customer_id' => 'nullable|exists:customers,id',
            'currency' => 'nullable|string|size:3',
        ]);

        DB::beginTransaction();

        try {
            $order = Order::where('id', $orderId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->items()->where('status', 'active')->doesntExist()) {
                DB::rollBack();
                return response()->json(['message' => 'O pedido não tem itens activos.'], 400);
            }

            $shift = Shift::where('id', $order->shift_id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (!$shift) {
                DB::rollBack();
                return response()->json(['message' => 'Turno fechado. Não é possível finalizar o pedido.'], 400);
            }

            if ($request->payment_method === 'cash' && $request->filled('received')) {
                if ($request->received < $order->total) {
                    DB::rollBack();
                    return response()->json(['message' => 'Valor recebido inferior ao total do pedido.'], 400);
                }
            }

            // 1. Regista pagamento
            Payment::create([
                'order_id' => $order->id,
                'shift_id' => $shift->id,
                'user_id' => Auth::id(),
                'method_payment' => $request->payment_method,
                'amount' => $order->total,
                'received' => $request->received,
                'change' => $request->change,
                'currency' => $request->input('currency', 'AOA'),
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // 2. Gera factura fiscal AGT
            $customerId = $request->customer_id
                ?? $order->customer_id
                ?? Customer::where('is_final_consumer', true)->value('id');

            $invoice = $this->generateInvoice($order, $shift, $request, $customerId);

            // 3. Fecha o pedido
            $order->update([
                'status' => 'closed',
                'customer_id' => $customerId,
                'invoice_generated' => true,
                'closed_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Pedido fechado e factura emitida com sucesso.',
                'invoice_number' => $invoice->invoice_number,
                'invoice_id' => $invoice->id,
                'total' => $order->total,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 🔄 REEMBOLSO TOTAL
    // ═══════════════════════════════════════════════════════
    public function refund(Request $request, int $orderId): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $order = Order::with('items')
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ($order->status !== 'closed') {
                DB::rollBack();
                return response()->json(['message' => 'Pedido não está fechado.'], 400);
            }

            $payment = Payment::where('order_id', $order->id)
                ->where('status', 'paid')
                ->firstOrFail();

            Refund::create([
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'shift_id' => $order->shift_id,
                'user_id' => Auth::id(),
                'amount' => $order->total,
                'type' => 'full',
                'reason' => $request->reason,
            ]);

            foreach ($order->items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item->product_id);
                $stockBefore = $product->stock;

                $product->increment('stock', $item->quantity);

                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'type' => 'refund',
                    'quantity' => $item->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $product->fresh()->stock,
                    'reference_type' => OrderItem::class,
                    'reference_id' => $item->id,
                    'note' => 'Reembolso total do pedido #' . $order->id,
                ]);
            }

            $payment->update(['status' => 'refunded']);
            $order->update(['status' => 'refunded']);

            DB::commit();

            return response()->json(['message' => 'Reembolso total realizado com sucesso.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 🔄 REEMBOLSO PARCIAL (por item)
    // ═══════════════════════════════════════════════════════
    public function refundItem(Request $request, int $itemId): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $item = OrderItem::with('order')->lockForUpdate()->findOrFail($itemId);
            $order = $item->order;

            if (!in_array($order->status, ['closed', 'partial_refund'])) {
                DB::rollBack();
                return response()->json(['message' => 'Pedido não pode ser reembolsado.'], 400);
            }

            if ($item->status === 'refunded') {
                DB::rollBack();
                return response()->json(['message' => 'Este item já foi reembolsado.'], 400);
            }

            if ($request->quantity > $item->quantity) {
                DB::rollBack();
                return response()->json(['message' => 'Quantidade superior à do item.'], 400);
            }

            $product = Product::lockForUpdate()->findOrFail($item->product_id);
            $payment = Payment::where('order_id', $order->id)
                ->where('status', 'paid')
                ->firstOrFail();

            $unitWithIva = round($item->total_with_iva / $item->quantity, 4);
            $refundAmount = round($unitWithIva * $request->quantity, 2);

            if ($request->quantity === $item->quantity) {
                $item->update([
                    'status' => 'refunded',
                    'subtotal' => 0,
                    'iva_amount' => 0,
                    'total_with_iva' => 0,
                ]);
            } else {
                $newQty = $item->quantity - $request->quantity;
                $subtotal = round($newQty * $item->unit_price, 2);
                $ivaAmount = round($subtotal * ($item->iva_rate / 100), 2);

                $item->update([
                    'quantity' => $newQty,
                    'subtotal' => $subtotal,
                    'iva_amount' => $ivaAmount,
                    'total_with_iva' => $subtotal + $ivaAmount,
                ]);
            }

            $refund = Refund::create([
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'shift_id' => $order->shift_id,
                'user_id' => Auth::id(),
                'amount' => $refundAmount,
                'type' => 'partial',
                'reason' => $request->reason,
            ]);

            // FIX: RefundItem agora tem model e migration — já não causa erro em runtime
            RefundItem::create([
                'refund_id' => $refund->id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $request->quantity,
                'unit_price' => $item->unit_price,
                'iva_rate' => $item->iva_rate,
                'iva_amount' => round($item->iva_amount / max($item->quantity + $request->quantity, 1) * $request->quantity, 2),
                'subtotal' => round($item->unit_price * $request->quantity, 2),
                'total_with_iva' => $refundAmount,
            ]);

            $stockBefore = $product->stock;
            $product->increment('stock', $request->quantity);

            StockMovement::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'type' => 'refund',
                'quantity' => $request->quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $product->fresh()->stock,
                'reference_type' => RefundItem::class,
                'reference_id' => $refund->items()->latest()->value('id'),
                'note' => 'Reembolso parcial do pedido #' . $order->id,
            ]);

            $this->recalcOrder($order);

            $allRefunded = $order->items()->where('status', 'active')->doesntExist();
            $order->update(['status' => $allRefunded ? 'refunded' : 'partial_refund']);

            DB::commit();

            return response()->json([
                'message' => 'Reembolso parcial realizado.',
                'refund_amount' => $refundAmount,
                'resumo' => $this->orderSummary($order),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 🧾 GERAR FACTURA FISCAL AGT (privado)
    // ═══════════════════════════════════════════════════════
    private function generateInvoice(Order $order, Shift $shift, Request $request, int $customerId): Invoice
    {
        $documentType = $request->input('document_type', 'FR');
        $series = $shift->terminal_id ?? 'A';
        $year = now()->year;
        $currency = $request->input('currency', 'AOA');

        // Número sequencial sem gaps — lockForUpdate garante atomicidade
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

        // FIX: formato corrigido para padrão AGT Angola — "FR A/2026/00001"
        $invoiceNumber = sprintf('%s %s/%d/%05d', $documentType, $series, $year, $number);

        $items = $order->items()->where('status', 'active')->get();
        $taxableAmount = $items->sum('subtotal');
        $taxAmount = $items->sum('iva_amount');
        $totalAmount = $items->sum('total_with_iva');

        $now = now();

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'customer_id' => $customerId,
            'user_id' => Auth::id(),
            'shift_id' => $shift->id,
            'document_type' => $documentType,
            'series' => $series,
            'sequence_number' => $number,
            'invoice_number' => $invoiceNumber,
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'discount_amount' => $order->discount,
            'paid_amount' => $totalAmount,
            'currency' => $currency,
            'status' => 'issued',
            'issued_at' => $now,
            'delivered_at' => $now, // obrigatório AGT
        ]);

        // Linhas da factura (snapshots imutáveis)
        foreach ($items as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $item->product_id,
                'order_item_id' => $item->id,
                'description' => $item->product_name,
                'product_code' => $item->product_code,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent ?? 0,
                'discount_amount' => $item->discount_amount ?? 0,
                'tax_rate' => $item->iva_rate,
                'tax_code' => $item->tax_code,
                'tax_exemption_reason' => $item->tax_exemption_reason,
                'net_amount' => $item->subtotal,
                'tax_amount' => $item->iva_amount,
                'gross_amount' => $item->total_with_iva,
            ]);
        }

        // Resumo fiscal por taxa — rodapé obrigatório AGT
        // FIX: agora agrupa por tax_code + iva_rate para separar ISE de EXC
        // (ambos têm taxa 0 mas são códigos distintos para o SAF-T)
        $taxGroups = $items->groupBy(fn($i) => $i->tax_code . '_' . $i->iva_rate);

        foreach ($taxGroups as $groupItems) {
            $first = $groupItems->first();
            InvoiceTaxSummary::create([
                'invoice_id' => $invoice->id,
                'tax_code' => $first->tax_code,
                // 'tax_rate_id' => $first->tax_id,
                'tax_rate' => $first->iva_rate,
                'taxable_amount' => $groupItems->sum('subtotal'),
                'tax_amount' => $groupItems->sum('iva_amount'),
                'tax_exemption_reason' => $first->tax_exemption_reason,
            ]);
        }

        // Hash encadeado — SHA-256 como placeholder (produção: RSA-1024 com chave privada AGT)
        $previousHash = Invoice::where('document_type', $documentType)
            ->where('series', $series)
            ->where('id', '<', $invoice->id)
            ->orderByDesc('id')
            ->value('hash') ?? '';

        $hashData = implode(';', [
            $invoice->issued_at->format('Y-m-d'),
            $now->format('Y-m-d H:i:s'),
            $invoice->invoice_number,
            number_format((float) $invoice->total_amount, 2, '.', ''),
            $previousHash,
        ]);

        $invoice->update([
            'hash' => base64_encode(hash('sha256', $hashData)),
            'hash_control' => '1;11;21;31',
        ]);

        // dispara a submissão automática à AGT (fila) — sem isto, facturas
        // de venda normal (FT/FR/TV) nunca são submetidas automaticamente,
        // só NC/ND/RC/RG a(que já disparam via InvoiceController)
        SubmitInvoiceToAgt::dispatch($invoice);

        return $invoice;
    }

    // ═══════════════════════════════════════════════════════
    // 🔧 HELPERS PRIVADOS
    // ═══════════════════════════════════════════════════════

    private function recalcOrder(Order $order): void
    {
        $order->refresh();
        $items = $order->items()->where('status', 'active');
        $subtotal = $items->sum('subtotal');
        $iva = $items->sum('iva_amount');

        $order->update([
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $subtotal + $iva,
        ]);
    }

    private function orderSummary(Order $order): array
    {
        $order->refresh();
        return [
            'subtotal' => $order->subtotal,
            'iva' => $order->iva,
            'discount' => $order->discount,
            'total' => $order->total,
        ];
    }
}