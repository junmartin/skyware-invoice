<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'email',
        'is_active',
        'currency',
        'default_due_days',
        'billing_address',
        'plan_name',
        'usage_xlsx_path',
        'last_billed_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_billed_at' => 'datetime',
    ];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
