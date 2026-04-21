<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('email');
            $table->boolean('is_active')->default(true)->index();
            $table->string('currency', 10)->default('IDR');
            $table->unsignedBigInteger('default_due_days')->default(14);
            $table->text('billing_address')->nullable();
            $table->string('plan_name')->nullable();
            $table->string('usage_xlsx_path')->nullable();
            $table->timestamp('last_billed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['is_active', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('clients');
    }
}
