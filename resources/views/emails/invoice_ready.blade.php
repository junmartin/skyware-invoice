<p>Dear {{ $invoice->client->name }},</p>
<p>Your invoice <strong>{{ $invoice->invoice_number }}</strong> is attached.</p>
<p>Total: {{ number_format($invoice->total_amount, 2) }} {{ $invoice->currency }}</p>
@if(optional($invoice->paymentRecord)->payment_url)
<p>Payment Link: <a href="{{ $invoice->paymentRecord->payment_url }}">{{ $invoice->paymentRecord->payment_url }}</a></p>
@endif
<p>Thank you.</p>
