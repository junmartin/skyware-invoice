<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h2>Invoice {{ $invoice->invoice_number }}</h2>
    <p>Client: {{ $invoice->client->name }} ({{ $invoice->client->code }})</p>
    <p>Issue Date: {{ $invoice->issue_date->format('Y-m-d') }}</p>
    <p>Due Date: {{ $invoice->due_date->format('Y-m-d') }}</p>

    <table>
        <thead>
            <tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr>
        </thead>
        <tbody>
            @foreach($invoice->details as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="text-right">{{ $line->quantity }}</td>
                    <td class="text-right">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="text-right">Subtotal: {{ number_format($invoice->subtotal, 2) }}</p>
    <p class="text-right">Tax: {{ number_format($invoice->tax_amount, 2) }}</p>
    <p class="text-right"><strong>Total: {{ number_format($invoice->total_amount, 2) }} {{ $invoice->currency }}</strong></p>
</body>
</html>
