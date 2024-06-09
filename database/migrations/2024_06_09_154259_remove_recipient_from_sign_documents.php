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
        Schema::table('sign_documents', function (Blueprint $table) {
            $table->dropColumn('recipient_email');
            $table->dropColumn('recipient_name');
            $table->dropColumn('recipient_company');
            $table->dropColumn('recipient_org_nr');
            $table->dropColumn('sign_ip');
            $table->dropColumn('sign_user_agent');
            $table->dropColumn('sign_mac_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sign_documents', function (Blueprint $table) {
            $table->text('recipient_email')->nullable()->default(null);
            $table->text('recipient_name')->nullable()->default(null);
            $table->text('recipient_company')->nullable()->default(null);
            $table->text('recipient_org_nr')->nullable()->default(null);
            $table->string('sign_ip')->nullable()->default(null);
            $table->string('sign_user_agent')->nullable()->default(null);
            $table->string('sign_mac_address')->nullable()->default(null);
        });
    }
};
