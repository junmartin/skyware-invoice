<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'status',
        'recipient',
        'subject',
        'attachment_types',
        'attempted_at',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'attachment_types' => 'array',
        'attempted_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
