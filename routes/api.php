<?php

use App\Events\PublicMessage;

use App\Http\Controllers\Api\AccessLevelController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashMovementController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FeInvoiceController;
use App\Http\Controllers\Api\HomeBoardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InvoicePdfController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\ShiftsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — POS AGT-Ready
| FIX (revisão 2): 
|   - Removida rota pública duplicada POST /invoices/{id}/cancel (estava
|     acessível SEM auth:sanctum e "escondia" a versão protegida, porque
|     o Laravel resolve pela ordem de registo).
|   - debit-note e receipt movidas para dentro do grupo auth:sanctum —
|     ambas chamam Auth::id() internamente e não podem ser públicas.
|   - Removido import de ShiftController (não usado — rotas de shift
|     usam ShiftsController em todo o lado).
|   - Comentadas 3 rotas que apontam para métodos inexistentes no
|     InvoiceController actual (sequences, reissue, updateCustomer) —
|     causariam BadMethodCallException (500) se chamadas. Descomenta
|     quando os métodos forem implementados.
|   - Removido SAF-T (SaftController e prefix 'saft') — a AGT exige
|     agora facturação eletrónica (submissão em tempo real via
|     fe/invoices), que já substitui a exportação SAF-T em lote.
|--------------------------------------------------------------------------
*/

// ── Rota de teste ──────────────────────────────────────
Route::get('/teste', fn() => 'teste');

Route::prefix('fe/invoices')->group(function () {
    Route::post('/{invoice}/submit', [FeInvoiceController::class, 'submit']);
    Route::get('/{invoice}/status', [FeInvoiceController::class, 'status']);
});

// ── Autenticação (público) ─────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// ══════════════════════════════════════════════════════
// ROTAS PROTEGIDAS
// ══════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {

    // ── Auth / Utilizador autenticado ──────────────────
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ── Gestão de utilizadores ─────────────────────────
    Route::get('/users', [AuthController::class, 'index']);
    Route::put('/users/{user}', [AuthController::class, 'update']);

    // ── Dashboard ──────────────────────────────────────
    Route::get('/dashboard', [HomeBoardController::class, 'index']);

    // ── Níveis de acesso ───────────────────────────────
    Route::apiResource('access-levels', AccessLevelController::class);

    // ── Categorias ─────────────────────────────────────
    Route::apiResource('categories', CategoryController::class);

    // ── Produtos ───────────────────────────────────────
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::get('/{product}', [ProductController::class, 'show'])->name('show');
        Route::put('/{product}', [ProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
        Route::post('/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->name('adjustStock');
        Route::get('/{product}/stock-history', [ProductController::class, 'stockHistory'])->name('stockHistory');
        Route::patch('/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('toggleActive');
    });

    // ── Turno ──────────────────────────────────────────
    Route::prefix('shifts')->name('shifts.')->group(function () {
        Route::get('/', [ShiftsController::class, 'index'])->name('index');
        Route::get('/current', [ShiftsController::class, 'current'])->name('current');
        Route::get('/{id}', [ShiftsController::class, 'show'])->name('show');
        Route::post('/open', [ShiftsController::class, 'open'])->name('open');
        Route::post('/close', [ShiftsController::class, 'close'])->name('close');
    });

    // ── Movimentos de caixa ────────────────────────────
    Route::prefix('cash-movements')->name('cash-movements.')->group(function () {
        Route::post('/', [CashMovementController::class, 'store'])->name('store');
        Route::get('/current', [CashMovementController::class, 'current'])->name('current');
        Route::get('/shift/{shiftId}', [CashMovementController::class, 'byShift'])->name('byShift');
    });

    // ── Pedidos ────────────────────────────────────────
// ATENÇÃO: /sales e /open devem vir ANTES de /{orderId} para não serem
// capturados como parâmetro pelo router do Laravel
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'getOrders'])->name('index');
        Route::get('/sales', [OrderController::class, 'getSales'])->name('sales');
        Route::post('/open', [OrderController::class, 'open'])->name('open');
        Route::post('/{orderId}/close', [OrderController::class, 'close'])->name('close');
        Route::post('/{orderId}/add-item', [OrderController::class, 'addItem'])->name('addItem');
        Route::post('/{orderId}/decrement-item', [OrderController::class, 'decrementItem'])->name('decrementItem');
        Route::delete('/items/{itemId}', [OrderController::class, 'removeItem'])->name('removeItem');
        Route::post('/{orderId}/refund', [OrderController::class, 'refund'])->name('refund');
        Route::post('/items/{itemId}/refund', [OrderController::class, 'refundItem'])->name('refundItem');
    });

    // ── Facturas ───────────────────────────────────────
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/{id}', [InvoiceController::class, 'show'])->name('show');

        // FIX: estas 3 rotas viviam FORA do auth:sanctum e a de 'cancel'
        // estava duplicada (colisão silenciosa — a pública ganhava sempre).
        // Todas chamam Auth::id() internamente, por isso têm de estar aqui.
        Route::post('/{id}/cancel', [InvoiceController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/debit-note', [InvoiceController::class, 'debitNote'])->name('debitNote');
        Route::post('/receipt', [InvoiceController::class, 'receipt'])->name('receipt');

        // ⚠️ Comentadas — 'sequences', 'reissue' e 'updateCustomer' NÃO
        // existem no InvoiceController actual. Descomenta só depois de
        // implementar os métodos correspondentes, senão dá 500.
        // Route::get('/sequences', [InvoiceController::class, 'sequences'])->name('sequences');
        // Route::post('/{id}/reissue', [InvoiceController::class, 'reissue'])->name('reissue');
        // Route::patch('/{id}/customer', [InvoiceController::class, 'updateCustomer'])->name('updateCustomer');

        Route::get('/{id}/pdf', [InvoicePdfController::class, 'download'])->name('pdf');
        Route::get('/{id}/pdf/view', [InvoicePdfController::class, 'view'])->name('pdf.view');
        Route::get('/{id}/qrcode', [InvoicePdfController::class, 'qrCode'])->name('qrcode');
    });

    // ── Relatórios ─────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/invoices', [ReportsController::class, 'index'])->name('invoices');
        Route::get('/orders', [ReportsController::class, 'orders'])->name('orders');
        Route::get('/daily', [ReportsController::class, 'dailySummary'])->name('daily');
        Route::get('/shift/{shiftId}', [ReportsController::class, 'shiftSummary'])->name('shift');
    });

    // ══════════════════════════════════════════════════
// ROTAS DE ADMINISTRADOR (access_level_id = 1)
// ══════════════════════════════════════════════════
    Route::middleware('access.level:1')->group(function () {
        Route::get('/admin/users', [AuthController::class, 'listUsers']);
        Route::put('/admin/users/{user}', [AuthController::class, 'updateUser']);
    });
});

// ── WebSocket: envio de mensagens públicas ─────────────
/* Route::post('/send-message', function (Request $request) {
    $validated = $request->validate([
        'user' => 'sometimes|string|max:255',
        'message' => 'required|string|max:1000',
    ]);
    event(new PublicMessage($validated['user'] ?? 'Utilizador Anónimo', $validated['message']));
    return response()->json(['status' => 'Mensagem enviada']);
}); */