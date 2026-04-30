<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    const STATUS_GENERATING = 'generating';
    const STATUS_GENERATED = 'generated';
    const STATUS_PENDING_STAMPING = 'pending_stamping';
    const STATUS_READY_TO_SEND = 'ready_to_send';
    const STATUS_SENDING = 'sending';
    const STATUS_SENT = 'sent';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_VOID = 'void';

    const TYPE_MONTHLY = 'monthly';
    const TYPE_ADHOC   = 'adhoc';
    const TYPE_YEARLY  = 'yearly';
    const TYPE_HISTORICAL = 'historical';

    protected $fillable = [
        'client_id',
        'billing_cycle_id',
        'invoice_number',
        'invoice_type',
        'status',
        'currency',
        'subtotal',
        'tax_amount',
        'total_amount',
        'issue_date',
        'due_date',
        'generated_at',
        'ready_to_send_at',
        'sent_at',
        'paid_at',
        'stamping_required',
        'stamping_status',
        'generated_pdf_path',
        'stamped_pdf_path',
        'stamped_uploaded_at',
        'stamped_uploaded_by',
        'email_sent',
        'recipient_email',
        'email_send_mode_snapshot',
        'last_error',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
        'generated_at' => 'datetime',
        'ready_to_send_at' => 'datetime',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'stamping_required' => 'boolean',
        'stamped_uploaded_at' => 'datetime',
        'email_sent' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function billingCycle()
    {
        return $this->belongsTo(BillingCycle::class);
    }

    public function details()
    {
        return $this->hasMany(InvoiceDetail::class)->orderBy('position');
    }

    public function paymentRecord()
    {
        return $this->hasOne(PaymentRecord::class);
    }

    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class)->latest('attempted_at');
    }

    public function statusHistories()
    {
        return $this->hasMany(InvoiceStatusHistory::class)->latest('created_at');
    }

    public function stampedUploader()
    {
        return $this->belongsTo(User::class, 'stamped_uploaded_by');
    }

    public function scopeReadyToSend($query)
    {
        return $query->where('status', self::STATUS_READY_TO_SEND)->where('email_sent', false);
    }
}
