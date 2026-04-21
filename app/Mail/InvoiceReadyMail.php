<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $attachmentsData;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice, array $attachmentsData)
    {
        $this->invoice = $invoice->loadMissing(['client', 'paymentRecord']);
        $this->attachmentsData = $attachmentsData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mail = $this->subject('Invoice '.$this->invoice->invoice_number)
            ->view('emails.invoice_ready');

        foreach ($this->attachmentsData as $attachment) {
            if (! empty($attachment['path']) && file_exists($attachment['path'])) {
                $mail->attach($attachment['path'], [
                    'as' => $attachment['name'],
                    'mime' => $attachment['mime'],
                ]);
            }
        }

        return $mail;
    }
}
