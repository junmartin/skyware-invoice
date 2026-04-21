<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'month',
        'cycle_start_date',
        'cycle_end_date',
        'scheduled_run_at',
        'generated_at',
    ];

    protected $casts = [
        'cycle_start_date' => 'date',
        'cycle_end_date' => 'date',
        'scheduled_run_at' => 'datetime',
        'generated_at' => 'datetime',
    ];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function getLabelAttribute()
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }
}
