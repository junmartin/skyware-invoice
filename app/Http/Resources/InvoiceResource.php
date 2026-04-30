<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
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
            'invoice_number' => $this->invoice_number,
            'client_id' => $this->client_id,
            'client' => new ClientResource($this->whenLoaded('client')),
            'billing_cycle_id' => $this->billing_cycle_id,
            'billing_cycle' => $this->whenLoaded('billingCycle'),
            'invoice_type' => $this->invoice_type,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'issue_date' => $this->issue_date,
            'due_date' => $this->due_date,
            'generated_at' => $this->generated_at,
            'ready_to_send_at' => $this->ready_to_send_at,
            'sent_at' => $this->sent_at,
            'paid_at' => $this->paid_at,
            'stamping_required' => $this->stamping_required,
            'stamping_status' => $this->stamping_status,
            'generated_pdf_path' => $this->generated_pdf_path,
            'stamped_pdf_path' => $this->stamped_pdf_path,
            'stamped_uploaded_at' => $this->stamped_uploaded_at,
            'email_sent' => $this->email_sent,
            'recipient_email' => $this->recipient_email,
            'email_send_mode_snapshot' => $this->email_send_mode_snapshot,
            'last_error' => $this->last_error,
            'details' => InvoiceDetailResource::collection($this->whenLoaded('details')),
            'payment_record' => new PaymentRecordResource($this->whenLoaded('paymentRecord')),
            'email_logs' => EmailLogResource::collection($this->whenLoaded('emailLogs')),
            'status_histories' => StatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
