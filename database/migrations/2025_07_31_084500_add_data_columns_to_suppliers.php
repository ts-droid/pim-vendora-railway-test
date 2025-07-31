<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->integer('limit')->default(0);
            $table->text('manufacturer_information')->nullable()->default(null);
            $table->text('eu_representative')->nullable()->default(null);
            $table->integer('general_delivery_time')->default(0);
            $table->integer('purchase_min_value')->default(0);
            $table->integer('purchase_min_quantity')->default(0);
            $table->text('shipping_instructions')->default('[]');

            $table->string('po_contact_name')->default('');
            $table->string('po_contact_attention')->default('');
            $table->string('po_contact_email')->default('');
            $table->string('po_contact_phone1')->default('');
            $table->string('po_contact_phone2')->default('');
            $table->string('po_address_line')->default('');
            $table->string('po_address_city')->default('');
            $table->string('po_address_country')->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('limit');
            $table->dropColumn('manufacturer_information');
            $table->dropColumn('eu_representative');
            $table->dropColumn('general_delivery_time');
            $table->dropColumn('purchase_min_value');
            $table->dropColumn('purchase_min_quantity');
            $table->dropColumn('shipping_instructions');

            $table->dropColumn('po_contact_name');
            $table->dropColumn('po_contact_attention');
            $table->dropColumn('po_contact_email');
            $table->dropColumn('po_contact_phone1');
            $table->dropColumn('po_contact_phone2');
            $table->dropColumn('po_address_line');
            $table->dropColumn('po_address_city');
            $table->dropColumn('po_address_country');
        });
    }
};
