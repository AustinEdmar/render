<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    // ═══════════════════════════════════════════════════════
    // 📋 LISTAR PRODUTOS
    // ═══════════════════════════════════════════════════════
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category:id,name', 'taxRate:id,tax_code,tax_percentage');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->boolean('only_active')) {
            $query->active();
        }

        if ($request->boolean('low_stock')) {
            $threshold = $request->input('stock_threshold', 5);
            $query->lowStock($threshold);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('product_code', 'like', "%{$search}%")
                    ->orWhere('barcode', $search);
            });
        }

        if ($request->filled('barcode')) {
            $query->where('barcode', $request->barcode);
        }

        return response()->json(
            $query->latest()->paginate($request->input('per_page', 20))
        );
    }

    // ═══════════════════════════════════════════════════════
    // 🔍 MOSTRAR UM PRODUTO
    // ═══════════════════════════════════════════════════════
    public function show(Product $product): JsonResponse
    {
        return response()->json(
            $product->load('category:id,name', 'taxRate:id,tax_code,tax_percentage,exemption_reason')
        );
    }

    // ═══════════════════════════════════════════════════════
    // ➕ CRIAR PRODUTO
    // ═══════════════════════════════════════════════════════
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'product_code' => 'nullable|string|max:50|unique:products,product_code',
            'unit' => 'nullable|string|max:20',
            // FIX: removida validação de 'iva' (campo inexistente)
            // A taxa de IVA é definida via tax_rate_id (FK para tax_rates)
            'tax_rate_id' => 'required|exists:tax_rates,id',
            'tax_exemption_reason' => 'nullable|string|max:255',
            'stock' => 'required|integer|min:0',
            'barcode' => 'nullable|string|unique:products,barcode',
            'category_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        DB::beginTransaction();

        try {
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('product_images', 'public');
            }

            $product = Product::create([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'product_code' => $request->product_code,
                'unit' => $request->input('unit', 'UN'),
                // FIX: removido campo 'iva' — substituído por tax_rate_id
                'tax_rate_id' => $request->tax_rate_id,
                'tax_exemption_reason' => $request->tax_exemption_reason,
                'stock' => $request->stock,
                'barcode' => $request->barcode,
                'category_id' => $request->category_id,
                'is_active' => $request->input('is_active', true),
                'image_path' => $imagePath,
            ]);

            if ($request->stock > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'type' => 'purchase',
                    'quantity' => $request->stock,
                    'stock_before' => 0,
                    'stock_after' => $request->stock,
                    'note' => 'Stock inicial ao criar produto.',
                ]);
            }

            DB::commit();

            return response()->json(
                $product->load('category:id,name', 'taxRate:id,tax_code,tax_percentage'),
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // ✏️ ACTUALIZAR PRODUTO
    // ═══════════════════════════════════════════════════════
    public function update(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'product_code' => 'sometimes|nullable|string|max:50|unique:products,product_code,' . $product->id,
            'unit' => 'sometimes|string|max:20',
            // FIX: removida validação de 'iva'
            'tax_rate_id' => 'sometimes|exists:tax_rates,id',
            'tax_exemption_reason' => 'sometimes|nullable|string|max:255',
            'barcode' => 'sometimes|nullable|string|unique:products,barcode,' . $product->id,
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'is_active' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Stock nunca alterado aqui — usar adjustStock()
        $validated = $request->only([
            'name',
            'description',
            'price',
            'product_code',
            'unit',
            'tax_rate_id',
            'tax_exemption_reason',
            'barcode',
            'category_id',
            'is_active',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('product_images', 'public');
        }

        $product->update($validated);

        return response()->json(
            $product->load('category:id,name', 'taxRate:id,tax_code,tax_percentage')
        );
    }

    // ═══════════════════════════════════════════════════════
    // 📦 AJUSTE DE STOCK
    // ═══════════════════════════════════════════════════════
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:purchase,adjustment,loss',
            'quantity' => 'required|integer|min:1',
            'note' => 'required_if:type,adjustment|required_if:type,loss|nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $stockBefore = $product->stock;

            if ($request->type === 'loss') {
                if ($product->stock < $request->quantity) {
                    DB::rollBack();
                    return response()->json(['message' => 'Stock insuficiente para registar quebra.'], 400);
                }
                $product->decrement('stock', $request->quantity);
                $movementQty = -$request->quantity;
            } else {
                $product->increment('stock', $request->quantity);
                $movementQty = $request->quantity;
            }

            $product->refresh();

            StockMovement::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'type' => $request->type,
                'quantity' => $movementQty,
                'stock_before' => $stockBefore,
                'stock_after' => $product->stock,
                'note' => $request->note,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Stock ajustado com sucesso.',
                'stock_before' => $stockBefore,
                'stock_after' => $product->stock,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════
    // 📜 HISTÓRICO DE STOCK
    // ═══════════════════════════════════════════════════════
    public function stockHistory(Product $product): JsonResponse
    {
        $movements = StockMovement::with('user:id,name')
            ->where('product_id', $product->id)
            ->latest()
            ->paginate(20);

        return response()->json($movements);
    }

    // ═══════════════════════════════════════════════════════
    // 🔄 ACTIVAR / DESACTIVAR
    // ═══════════════════════════════════════════════════════
    public function toggleActive(Product $product): JsonResponse
    {
        $product->update(['is_active' => !$product->is_active]);

        return response()->json([
            'message' => $product->is_active ? 'Produto activado.' : 'Produto desactivado.',
            'is_active' => $product->is_active,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // 🗑️ ELIMINAR PRODUTO
    // ═══════════════════════════════════════════════════════
    public function destroy(Product $product): JsonResponse
    {
        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'Produto não pode ser eliminado pois já foi vendido. Desactive-o em vez disso.',
            ], 409);
        }

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return response()->json(['message' => 'Produto eliminado com sucesso.']);
    }
}