<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StatusHistoryResource extends JsonResource
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
            'invoice_id' => $this->invoice_id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'note' => $this->note,
            'performer_id' => $this->performer_id,
            'performer_name' => $this->performer?->name,
            'created_at' => $this->created_at,
        ];
    }
}
