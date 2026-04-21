<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('status')->default('generating')->index();
            $table->string('currency', 10)->default('IDR');
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('tax_amount', 16, 2)->default(0);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->date('issue_date');
            $table->date('due_date');
            $table->timestamp('generated_at')->nullable()->index();
            $table->timestamp('ready_to_send_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();

            $table->boolean('stamping_required')->default(false)->index();
            $table->string('stamping_status')->default('not_required')->index();
            $table->string('generated_pdf_path')->nullable();
            $table->string('stamped_pdf_path')->nullable();
            $table->timestamp('stamped_uploaded_at')->nullable();
            $table->foreignId('stamped_uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('email_sent')->default(false)->index();
            $table->string('email_send_mode_snapshot')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'billing_cycle_id']);
            $table->index(['billing_cycle_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoices');
    }
}
