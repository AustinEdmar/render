<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'customer_id',
        'user_id',
        'shift_id',
        'document_type',
        'series',
        'sequence_number',
        'invoice_number',
        'taxable_amount',
        'tax_amount',
        'total_amount',
        'discount_amount',
        'paid_amount',
        'currency',
        'status',
        'credit_note_id',
        'issued_at',
        'due_at',
        'delivered_at',
        'hash',
        'hash_control',
        'qr_code_data',
        'notes',
        // NOVO — campos AGT (ver migration 2026_08_30_000000_add_agt_fields_to_invoices_table)
        'agt_document_no',
        'fe_status',
        'fe_request_id',
        'debit_note_id',
        'reference_reason',
    ];

    protected function casts(): array
    {
        return [
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function taxSummaries(): HasMany
    {
        return $this->hasMany(InvoiceTaxSummary::class);
    }

    // Nota de crédito que anulou esta factura
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'credit_note_id');
    }

    // Facturas que esta nota de crédito anulou (chamar a partir da NC)
    public function creditedInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'credit_note_id');
    }

    // NOVO — espelha creditNote()/creditedInvoices(), mas para Nota de Débito.
    public function debitNote(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'debit_note_id');
    }

    // Facturas que esta nota de débito debitou (chamar a partir da ND)
    public function debitedInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'debit_note_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'credit_note_invoice_id');
    }

    // NOVO — para um recibo (RC/RG): as facturas que este recibo liquida.
    public function paidInvoices(): BelongsToMany
    {
        return $this->belongsToMany(
            Invoice::class,
            'invoice_settlements',
            'receipt_invoice_id',
            'settled_invoice_id'
        )->withPivot('amount_paid')->withTimestamps();
    }

    // NOVO — para uma factura (FT/FR/TV): os recibos que a foram liquidando.
    public function settledByReceipts(): BelongsToMany
    {
        return $this->belongsToMany(
            Invoice::class,
            'invoice_settlements',
            'settled_invoice_id',
            'receipt_invoice_id'
        )->withPivot('amount_paid')->withTimestamps();
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function scopeByDocumentType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeByPeriod($query, string $from, string $to)
    {
        return $query->whereBetween('issued_at', [$from, $to]);
    }

    // ─── Helpers AGT ───────────────────────────────────────

    public function isCancellable(): bool
    {
        return $this->status === 'issued';
    }

    public function isCreditNote(): bool
    {
        return $this->document_type === 'NC';
    }

    // NOVO
    public function isDebitNote(): bool
    {
        return $this->document_type === 'ND';
    }

    public function isReceipt(): bool
    {
        return in_array($this->document_type, ['RC', 'RG'], true);
    }

    public function getAmountOutstandingAttribute(): float
    {
        return (float) $this->total_amount - (float) $this->paid_amount;
    }
}