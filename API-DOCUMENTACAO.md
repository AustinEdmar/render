# POS AGT-Ready — Documentação da API

Base URL: `http://localhost:8000/api` (troca pelo URL real em produção/staging)

Autenticação: **Bearer Token** (Laravel Sanctum). Depois do login, envia em todos os pedidos protegidos:

```
Authorization: Bearer {access_token}
Accept: application/json
Content-Type: application/json
```

---

## 🔁 Fluxo completo (segue por esta ordem)

Login → abrir turno (com fundo de maneio) → abrir pedido → adicionar produto → fechar pedido (gera factura e submete à AGT automaticamente) → consultar estado na AGT.

### 1. Login
```
POST /login
Content-Type: application/json

{
  "email": "joao@example.com",
  "password": "password123"
}
```
→ guarda `access_token` da resposta. Usa em todos os passos seguintes.

### 2. Abrir turno (com fundo de maneio)
```
POST /shifts/open
Authorization: Bearer {token}
Content-Type: application/json

{
  "initial_amount": 5000,
  "terminal_id": "A"
}
```
`initial_amount` **é** o fundo de maneio — não precisas de um passo à parte para isso. O `close()` do turno soma `initial_amount + vendas em dinheiro − reembolsos em dinheiro + movimentos de caixa` para calcular o valor esperado no caixa, por isso o fundo de maneio entra aqui, uma única vez.

Guarda o `id` da resposta como `{shift_id}` (usado só se precisares de consultar o turno depois — os próximos passos resolvem o turno sozinhos pelo utilizador autenticado).

> `POST /cash-movements` (`type: inflow` / `outflow`) é para reforços ou sangrias **durante** o turno — não para o fundo de maneio inicial. Ver secção "Movimentos de Caixa" mais abaixo.

### 3. Abrir pedido (iniciar venda)
```
POST /orders/open
Authorization: Bearer {token}
```
→ guarda `id` da resposta como `{order_id}`.

### 4. Adicionar produto ao pedido
```
POST /orders/{order_id}/add-item
Authorization: Bearer {token}
Content-Type: application/json

{
  "product_id": 1,
  "quantity": 2
}
```
Repete para cada produto diferente da venda.

### 5. Fechar pedido (gera a factura)
```
POST /orders/{order_id}/close
Authorization: Bearer {token}
Content-Type: application/json

{
  "payment_method": "cash",
  "received": 5000,
  "change": 500,
  "document_type": "FR",
  "customer_id": null,
  "currency": "AOA"
}
```
- `payment_method`: `cash` | `card` | `QrCode` | `BankTransfer` | `multicaixa`
- `document_type`: `FT` | `FR` | `NC` | `ND` | `TV` | `RC` | `RG`

→ resposta traz `invoice_id`. Guarda como `{invoice_id}`.

✅ Ao fechar o pedido, `OrderController::generateInvoice()` já dispara `SubmitInvoiceToAgt::dispatch($invoice)` sozinho — a factura entra na fila `agt` automaticamente, sem precisares de chamar nada. O `PollFeSubmissionStatus` também se auto-reagenda até `valid`/`invalid`. Não há passo manual aqui a não ser para consultar o estado ou reenviar em caso de falha.

### 6. Consultar estado na AGT
```
GET /fe/invoices/{invoice_id}/status
Authorization: Bearer {token}
```
Faz polling até `fe_status` deixar de ser `pending` (`valid` / `invalid`). Normalmente nem precisas de forçar isto — o job de polling em fila já actualiza sozinho — mas serve para o teu amigo confirmar o estado a pedido do utilizador (ex: mostrar no ecrã "aguardando validação AGT" → "validado").

### (Reenvio manual, só se necessário)
```
POST /fe/invoices/{invoice_id}/submit
Authorization: Bearer {token}
```
Usa isto apenas se uma submissão anterior falhou de vez (esgotou `poll_max_attempts`) e precisares de tentar de novo manualmente.

---

## Auth

| Método | Rota | Auth | Body |
|---|---|---|---|
| POST | `/register` | não | `{ name, phone, active, access_level_id, email, password, password_confirmation }` |
| POST | `/login` | não | `{ email, password }` |
| POST | `/forgot-password` | não | `{ email }` |
| POST | `/reset-password` | não | `{ token, email, password, password_confirmation }` |
| GET | `/user` | sim | — |
| POST | `/logout` | sim | — |

## Utilizadores

| Método | Rota | Body |
|---|---|---|
| GET | `/users` | — |
| PUT | `/users/{user}` | `{ name, email, active, access_level_id }` (multipart se enviares `profile_photo`) |
| GET | `/admin/users` | ⚠️ método `listUsers` não existe no `AuthController` — dá 500 |
| PUT | `/admin/users/{user}` | ⚠️ método `updateUser` não existe no `AuthController` — dá 500 |

## Dashboard

| Método | Rota |
|---|---|
| GET | `/dashboard` |

## Níveis de Acesso

| Método | Rota | Body |
|---|---|---|
| GET | `/access-levels` | — |
| POST | `/access-levels` | `{ name }` |
| GET | `/access-levels/{id}` | — |
| PUT | `/access-levels/{id}` | `{ name }` |
| DELETE | `/access-levels/{id}` | — |

## Categorias

⚠️ `CategoryController` não foi partilhado — campos assumidos por padrão (`apiResource`). Confirma antes de usar.

| Método | Rota | Body |
|---|---|---|
| GET | `/categories` | — |
| POST | `/categories` | `{ name }` |
| GET | `/categories/{id}` | — |
| PUT | `/categories/{id}` | `{ name }` |
| DELETE | `/categories/{id}` | — |

## Produtos

| Método | Rota | Body / Query |
|---|---|---|
| GET | `/products` | query: `only_active`, `low_stock`, `stock_threshold`, `search`, `category_id`, `barcode`, `per_page` |
| GET | `/products/{product}` | — |
| POST | `/products` | multipart: `name, description, price, product_code, unit, tax_rate_id, tax_exemption_reason, stock, barcode, category_id, is_active, image(file)` |
| PUT | `/products/{product}` | mesmos campos, todos `sometimes` |
| DELETE | `/products/{product}` | — |
| POST | `/products/{product}/adjust-stock` | `{ type: purchase\|adjustment\|loss, quantity, note }` (`note` obrigatório se `adjustment`/`loss`) |
| GET | `/products/{product}/stock-history` | — |
| PATCH | `/products/{product}/toggle-active` | — |

## Turnos (Shifts)

| Método | Rota | Body |
|---|---|---|
| GET | `/shifts` | — |
| GET | `/shifts/current` | — |
| GET | `/shifts/{id}` | — |
| POST | `/shifts/open` | `{ initial_amount, terminal_id }` |
| POST | `/shifts/close` | `{ final_cash_amount }` |

## Movimentos de Caixa

Reforços (`inflow`) e sangrias (`outflow`) **durante** o turno — não é aqui que entra o fundo de maneio inicial (esse vai em `initial_amount` no `POST /shifts/open`, ver secção Turnos).

| Método | Rota | Body |
|---|---|---|
| POST | `/cash-movements` | `{ type: inflow\|outflow, amount, reason, currency }` |
| GET | `/cash-movements/current` | — |
| GET | `/cash-movements/shift/{shiftId}` | — |

## Pedidos (Orders)

| Método | Rota | Body / Query |
|---|---|---|
| GET | `/orders` | — (lista pedidos abertos) |
| GET | `/orders/sales` | query: `status, method_payment, shift_id, date, search, per_page` |
| POST | `/orders/open` | — |
| POST | `/orders/{orderId}/add-item` | `{ product_id, quantity }` |
| POST | `/orders/{orderId}/decrement-item` | `{ product_id, quantity }` |
| DELETE | `/orders/items/{itemId}` | — |
| POST | `/orders/{orderId}/close` | ver secção "Fluxo completo", passo 6 |
| POST | `/orders/{orderId}/refund` | `{ reason }` |
| POST | `/orders/items/{itemId}/refund` | `{ quantity, reason }` |

## Facturas (Invoices)

| Método | Rota | Body / Query |
|---|---|---|
| GET | `/invoices` | query: `document_type, status, series, user_id, customer_id, date_from, date_to, search, sort_by, sort_order, per_page` |
| GET | `/invoices/{id}` | — |
| POST | `/invoices/{id}/cancel` | `{ reason }` (gera Nota de Crédito, dispara AGT automaticamente) |
| POST | `/invoices/{id}/debit-note` | `{ reason, items: [{ description, quantity, unit_price, tax_rate, tax_code, product_id }] }` (dispara AGT automaticamente) |
| POST | `/invoices/receipt` | `{ document_type: RC\|RG, settlements: [{ invoice_id, amount_paid }] }` (dispara AGT automaticamente; a factura precisa de já ter `agt_document_no`) |
| GET | `/invoices/{id}/pdf` | download |
| GET | `/invoices/{id}/pdf/view` | stream no browser |
| GET | `/invoices/{id}/qrcode` | imagem PNG |

## Facturação Electrónica AGT

| Método | Rota |
|---|---|
| POST | `/fe/invoices/{invoice}/submit` | reenvio manual — normalmente não precisas, ver nota abaixo |
| GET | `/fe/invoices/{invoice}/status` | consultar/poll do estado na AGT |

✅ `OrderController::close()` (via `generateInvoice()`) já dispara `SubmitInvoiceToAgt::dispatch()` automaticamente, tal como `cancel()`, `debitNote()` e `receipt()` do `InvoiceController`. Toda factura (FT/FR/TV/NC/ND/RC/RG) entra na fila `agt` sozinha ao ser criada — não precisas de chamar `/submit` no fluxo normal.

⚠️ **Gap que continua por resolver:** não há endpoint para reenviar em massa as facturas que falharam definitivamente (esgotaram `poll_max_attempts` e ficaram `invalid`), nem filtro por `fe_status` em `GET /invoices` para as encontrares. Para tratar esses casos hoje, o teu amigo tem de saber o `invoice_id` de cada uma (ex: via `GET /invoices?status=...` e cruzar manualmente) e chamar `/submit` uma a uma.

## Relatórios

⚠️ `ReportsController` não foi partilhado — não há confirmação da assinatura exacta.

| Método | Rota |
|---|---|
| GET | `/reports/invoices` |
| GET | `/reports/orders` |
| GET | `/reports/daily` |
| GET | `/reports/shift/{shiftId}` |
