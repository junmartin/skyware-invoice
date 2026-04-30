<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BillingCycleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'month' => $this->month,
            'cycle_name' => $this->cycle_name,
            'is_generated' => $this->is_generated,
            'generated_at' => $this->generated_at,
            'invoices_count' => $this->invoices_count ?? $this->invoices()->count(),
            'total_amount' => $this->total_amount ?? $this->invoices()->sum('total_amount'),
            'paid_amount' => $this->paid_amount ?? $this->invoices()->where('status', 'paid')->sum('total_amount'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
