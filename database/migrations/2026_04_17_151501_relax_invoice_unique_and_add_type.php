<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RelaxInvoiceUniqueAndAddType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (DB::getDriverName() === 'mysql') {
            // The composite unique index (client_id, billing_cycle_id) is the only
            // index on client_id. MySQL refuses to drop it without another index
            // backing the client_id FK. Add a plain index first in the same statement.
            DB::statement('
                ALTER TABLE invoices
                    ADD INDEX invoices_client_id_index (client_id),
                    DROP INDEX invoices_client_id_billing_cycle_id_unique,
                    MODIFY billing_cycle_id BIGINT UNSIGNED NULL
            ');
        } else {
            // SQLite (tests): Schema builder handles table reconstruction internally.
            // SQLite doesn't enforce NOT NULL on integer FK columns the same way,
            // so just drop the unique constraint — nullability is implicitly permissive.
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropUnique(['client_id', 'billing_cycle_id']);
            });
        }

        // Add invoice_type to distinguish monthly / adhoc / yearly / historical.
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_type')->default('monthly')->after('invoice_number')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_type');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                ALTER TABLE invoices
                    MODIFY billing_cycle_id BIGINT UNSIGNED NOT NULL,
                    DROP INDEX invoices_client_id_index,
                    ADD UNIQUE KEY invoices_client_id_billing_cycle_id_unique (client_id, billing_cycle_id)
            ');
        } else {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unique(['client_id', 'billing_cycle_id']);
            });
        }
    }
}
