<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceStatusHistory;
use App\Models\PaymentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportHistoricalInvoices extends Command
{
    protected $signature = 'billing:import-historical
                            {file : Absolute path to the CSV file}
                            {--dry-run : Preview what would be imported without writing to the database}';

    protected $description = 'Import historical invoices from a CSV export of the legacy system';

    /**
     * Columns expected in the CSV (header row).
     */
    private const REQUIRED_COLUMNS = [
        'id', 'invoice_number', 'customer_name', 'customer_address',
        'customer_pic', 'date', 'due_date', 'amount', 'sequence',
        'url', 'file_path', 'payment_link', 'paid', 'paid_date',
        'is_void', 'created_at', 'updated_at',
    ];

    public function handle(): int
    {
        $csvPath = $this->argument('file');
        $dryRun  = $this->option('dry-run');

        if (! file_exists($csvPath)) {
            $this->error("File not found: {$csvPath}");
            return 1;
        }

        $rows = $this->readCsv($csvPath);

        if (empty($rows)) {
            $this->error('CSV is empty or could not be parsed.');
            return 1;
        }

        // Validate headers.
        $headers = array_keys($rows[0]);
        $missing = array_diff(self::REQUIRED_COLUMNS, $headers);
        if (!empty($missing)) {
            $this->error('Missing CSV columns: ' . implode(', ', $missing));
            return 1;
        }

        $this->info(sprintf('Found %d rows. Dry-run: %s', count($rows), $dryRun ? 'YES' : 'NO'));

        $stats = ['clients_created' => 0, 'invoices_imported' => 0, 'skipped' => 0, 'errors' => 0];
        $clientCache = [];   // normalized name => Client model

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $lineNo => $row) {
            try {
                $row = array_map(static function ($value) {
                    return is_string($value) ? trim($value) : $value;
                }, $row);

                // --- Resolve or create client ---
                $normalizedName = $this->normalizeName($row['customer_name']);

                if (!isset($clientCache[$normalizedName])) {
                    $client = Client::query()->where('name', $row['customer_name'])->first();

                    if (!$client) {
                        $code  = $this->generateClientCode($row['customer_name'], $clientCache);
                        $email = $this->guessEmail($row['customer_name']);

                        if (!$dryRun) {
                            $client = Client::query()->create([
                                'code'            => $code,
                                'name'            => $row['customer_name'],
                                'email'           => $email,
                                'is_active'       => true,
                                'currency'        => 'IDR',
                                'default_due_days'=> 14,
                                'billing_address' => $row['customer_address'] ?: null,
                                'plan_name'       => null,
                            ]);
                        } else {
                            // Dry-run: create a fake unsaved object so the rest of the loop works.
                            $client = new Client([
                                'id'   => 0,
                                'code' => $code,
                                'name' => $row['customer_name'],
                            ]);
                        }

                        $stats['clients_created']++;
                    }

                    $clientCache[$normalizedName] = $client;
                }

                $client = $clientCache[$normalizedName];

                // --- Determine status ---
                $isVoid = (bool)(int)$row['is_void'];
                $status = $isVoid ? Invoice::STATUS_VOID : Invoice::STATUS_PAID;

                $legacyCreatedAt = $this->normalizeDateTime($row['created_at']) ?? now()->toDateTimeString();
                $legacyUpdatedAt = $this->normalizeDateTime($row['updated_at']) ?? $legacyCreatedAt;
                $filePath = $this->normalizeNullableString($row['file_path']);
                $paymentLink = $this->normalizeNullableString($row['payment_link']);

                // --- Determine paid_at ---
                $paidAt = null;
                if (!$isVoid) {
                    $paidAt = $this->normalizeDateTime($row['paid_date']);
                    if ($paidAt === null) {
                        // Fallback: use due_date — all non-void historicals are treated as paid.
                        $paidAt = $this->normalizeDateTime($row['due_date']);
                    }
                }

                // --- Check for duplicate invoice_number ---
                if (Invoice::query()->where('invoice_number', $row['invoice_number'])->exists()) {
                    $this->warn("\n  Skipping duplicate: {$row['invoice_number']}");
                    $stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                $amount = (float)$row['amount'];

                if ($dryRun) {
                    $this->line(sprintf(
                        "\n  [DRY] %s | client=%s | status=%s | amount=%s | paid_at=%s",
                        $row['invoice_number'],
                        $row['customer_name'],
                        $status,
                        number_format($amount, 0, '.', ','),
                        $paidAt ?? '-'
                    ));
                    $stats['invoices_imported']++;
                    $bar->advance();
                    continue;
                }

                DB::transaction(function () use ($row, $client, $status, $paidAt, $amount, $isVoid, $legacyCreatedAt, $legacyUpdatedAt, $filePath, $paymentLink, &$stats) {
                    // --- Create invoice ---
                    $invoice = Invoice::query()->create([
                        'client_id'              => $client->id,
                        'billing_cycle_id'       => null,
                        'invoice_number'         => $row['invoice_number'],
                        'invoice_type'           => Invoice::TYPE_HISTORICAL,
                        'status'                 => $status,
                        'currency'               => 'IDR',
                        'subtotal'               => $amount,
                        'tax_amount'             => 0,
                        'total_amount'           => $amount,
                        'issue_date'             => $row['date'],
                        'due_date'               => $row['due_date'],
                        'generated_at'           => $legacyCreatedAt,
                        'ready_to_send_at'       => $legacyCreatedAt,
                        'sent_at'                => $legacyCreatedAt,
                        'paid_at'                => $paidAt,
                        'stamping_required'      => false,
                        'stamping_status'        => 'not_required',
                        'generated_pdf_path'     => $filePath,
                        'email_sent'             => true,
                        'email_send_mode_snapshot' => 'historical_import',
                        'created_at'             => $legacyCreatedAt,
                        'updated_at'             => $legacyUpdatedAt,
                    ]);

                    // --- Single detail line ---
                    InvoiceDetail::query()->create([
                        'invoice_id'  => $invoice->id,
                        'item_code'   => 'HISTORICAL',
                        'description' => 'Historical invoice import',
                        'quantity'    => 1,
                        'unit_price'  => $amount,
                        'line_total'  => $amount,
                        'position'    => 1,
                    ]);

                    // --- Status history entry ---
                    InvoiceStatusHistory::query()->create([
                        'invoice_id'  => $invoice->id,
                        'status'      => $status,
                        'note'        => 'Imported from legacy system',
                        'performed_by'=> null,
                        'created_at'  => $legacyCreatedAt,
                        'updated_at'  => $legacyUpdatedAt,
                    ]);

                    // --- Payment record (skip for void) ---
                    if (!$isVoid && $paymentLink !== null) {
                        PaymentRecord::query()->create([
                            'invoice_id'         => $invoice->id,
                            'provider'           => 'xendit',
                            'provider_reference' => null,
                            'external_id'        => $invoice->invoice_number,
                            'payment_url'        => $paymentLink,
                            'status'             => 'paid',
                            'amount'             => $amount,
                            'paid_at'            => $paidAt,
                        ]);
                    }

                    $stats['invoices_imported']++;
                });

            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->error("\n  Error on row " . ($lineNo + 2) . " ({$row['invoice_number']}): " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Clients created',    $stats['clients_created']],
                ['Invoices imported',  $stats['invoices_imported']],
                ['Skipped (duplicate)', $stats['skipped']],
                ['Errors',             $stats['errors']],
            ]
        );

        if ($dryRun) {
            $this->warn('Dry-run complete. No data was written.');
        } else {
            $this->info('Import complete.');
        }

        return $stats['errors'] > 0 ? 1 : 0;
    }

    // -------------------------------------------------------------------------

    private function readCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) === false) {
            return [];
        }

        $headers = null;
        while (($line = fgetcsv($handle, 0, ',', '"')) !== false) {
            if ($headers === null) {
                $headers = $line;
                continue;
            }
            if (count($line) < count($headers)) {
                // Pad short rows (multiline addresses can wrap).
                $line = array_pad($line, count($headers), '');
            }
            $rows[] = array_combine($headers, array_slice($line, 0, count($headers)));
        }

        fclose($handle);
        return $rows;
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    private function generateClientCode(string $name, array $cache): string
    {
        // Strip common legal suffixes, take first 3 meaningful words, slugify.
        $clean = preg_replace('/\b(PT\.?|CV\.?|Tbk\.?|Pte\.?|Ltd\.?)\b/i', '', $name);
        $words = array_filter(explode(' ', preg_replace('/[^a-z0-9 ]/i', '', $clean)));
        $slug  = strtolower(implode('', array_slice(array_values($words), 0, 3)));
        $slug  = substr($slug, 0, 12);

        // Avoid collisions with existing DB codes and codes assigned in this run.
        $base  = $slug;
        $i     = 1;
        $existing = Client::query()->pluck('code')->flip()->toArray();
        $assignedCodes = collect($cache)->pluck('code')->flip()->toArray();

        while (isset($existing[$slug]) || isset($assignedCodes[$slug])) {
            $slug = $base . $i++;
        }

        return $slug;
    }

    private function guessEmail(string $name): string
    {
        $slug = Str::slug($name, '.');
        return "billing+{$slug}@placeholder.invalid";
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || strcasecmp($trimmed, 'NULL') === 0) {
            return null;
        }

        return $trimmed;
    }

    private function normalizeDateTime(?string $value): ?string
    {
        $normalized = $this->normalizeNullableString($value);

        if ($normalized === null) {
            return null;
        }

        if ($normalized === '0000-00-00' || $normalized === '0000-00-00 00:00:00') {
            return null;
        }

        return $normalized;
    }
}
