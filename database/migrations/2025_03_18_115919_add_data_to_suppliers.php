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
            $table->string('class')->default('')->after('brand_name');
            $table->string('credit_terms')->default('')->after('class_description');

            $table->string('type')->default('Brand')->after('name');

            $table->string('main_address_line')->default('');
            $table->string('main_address_city')->default('');
            $table->string('main_address_country')->default('');

            $table->string('remit_address_line')->default('');
            $table->string('remit_address_city')->default('');
            $table->string('remit_address_country')->default('');

            $table->string('supplier_address_line')->default('');
            $table->string('supplier_address_city')->default('');
            $table->string('supplier_address_country')->default('');

            $table->string('main_contact_name')->default('');
            $table->string('main_contact_attention')->default('');
            $table->string('main_contact_email')->default('');
            $table->string('main_contact_phone1')->default('');
            $table->string('main_contact_phone2')->default('');

            $table->string('remit_contact_name')->default('');
            $table->string('remit_contact_attention')->default('');
            $table->string('remit_contact_email')->default('');
            $table->string('remit_contact_phone1')->default('');
            $table->string('remit_contact_phone2')->default('');

            $table->string('supplier_contact_name')->default('');
            $table->string('supplier_contact_attention')->default('');
            $table->string('supplier_contact_email')->default('');
            $table->string('supplier_contact_phone1')->default('');
            $table->string('supplier_contact_phone2')->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('class');
            $table->dropColumn('credit_terms');
            $table->dropColumn('type');

            $table->dropColumn('main_address_line');
            $table->dropColumn('main_address_city');
            $table->dropColumn('main_address_country');

            $table->dropColumn('remit_address_line');
            $table->dropColumn('remit_address_city');
            $table->dropColumn('remit_address_country');

            $table->dropColumn('supplier_address_line');
            $table->dropColumn('supplier_address_city');
            $table->dropColumn('supplier_address_country');

            $table->dropColumn('main_contact_name');
            $table->dropColumn('main_contact_attention');
            $table->dropColumn('main_contact_email');
            $table->dropColumn('main_contact_phone1');
            $table->dropColumn('main_contact_phone1');

            $table->dropColumn('remit_contact_name');
            $table->dropColumn('remit_contact_attention');
            $table->dropColumn('remit_contact_email');
            $table->dropColumn('remit_contact_phone1');
            $table->dropColumn('remit_contact_phone1');

            $table->dropColumn('supplier_contact_name');
            $table->dropColumn('supplier_contact_attention');
            $table->dropColumn('supplier_contact_email');
            $table->dropColumn('supplier_contact_phone1');
            $table->dropColumn('supplier_contact_phone1');
        });
    }
};
