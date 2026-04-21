# Skyware Invoice (Laravel 8)

Billing app with monthly invoice generation, manual e-meterai stamping workflow, Xendit Payment Links, and auto/manual email sending control.

## Stack
- Laravel 8.x
- PHP ^7.3|^8.0 compatible code
- MySQL (production) / SQLite memory (tests)
- DomPDF for invoice PDF
- Xendit Payment Links API
- SMTP (Gmail or SMTP2GO)

## Implemented Features
- Auth flow with registration/login and middleware-protected pages.
- Single-user mode behavior: registration is automatically blocked after first account is created.
- Dashboard widgets for next cycle and status counters.
- Active clients page for next cycle billing target.
- Invoice list/history with filters: client, date range, status, cycle month.
- Invoice detail page with timeline, payment status, stamping data, and email logs.
- Monthly invoice generation command with idempotency.
- Stamping queue page for manual stamped PDF upload.
- Send mode settings:
  - auto: send invoice immediately once ready.
  - manual: wait until command/button triggers send-all-ready.
- Xendit webhook endpoint updates payment status and marks invoice as paid.
- Special attachment for client code jneibb from configured usage XLSX path.

## Domain Model
- users
- clients
- billing_cycles
- invoices
- invoice_details
- payment_records
- email_logs
- invoice_status_histories
- app_settings

## Status Lifecycle
- generating
- generated
- pending_stamping
- ready_to_send
- sending
- sent
- paid
- failed

## Setup
1. Install dependencies:
   - composer install
2. Configure .env values:
   - database credentials
   - APP_TIMEZONE=Asia/Jakarta
   - SMTP credentials (MAIL_*)
   - Xendit credentials (XENDIT_*)
3. Run migrations and seeders:
   - php artisan migrate --seed
4. Start app:
   - php artisan serve

Default seeded login:
- Email: admin@example.com
- Password: password123

## Commands
- php artisan billing:generate-next-cycle
- php artisan billing:send-ready
- php artisan billing:sync-xendit-status

## Scheduler
Defined in app/Console/Kernel.php:
- billing:generate-next-cycle on day 1 monthly at 08:00, timezone Asia/Jakarta.
- billing:send-ready hourly (safe because send logic is idempotent).

Server cron entry example:
* * * * * php /var/www/skyware-invoice/artisan schedule:run >> /dev/null 2>&1

## Webhook
- Endpoint: POST /api/webhooks/xendit/invoice
- Token header: x-callback-token

## Deployment (GitHub Actions)
Workflow file: .github/workflows/deploy.yml
- SSH to VPS
- git pull origin main
- composer install
- php artisan migrate --force
- cache config/routes/views

Required GitHub secrets:
- VPS_HOST
- VPS_USER
- VPS_SSH_KEY

## Assumptions Used
- PDF library: barryvdh/laravel-dompdf.
- jneibb usage XLSX source directory: /var/www/api.skyware.systems/storage/app/client_usage.
- Meterai requirement rule: required when invoice subtotal (before tax) is >= IDR 5,000,000.
- Invoice line fields are internally generated and stored in invoice_details.

## Test Coverage
Feature tests included for:
- generator idempotency
- send-ready idempotency
- webhook payment update to paid
- duplicate invoice prevention for same client + cycle
