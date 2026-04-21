<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'status',
        'note',
        'performed_by',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
