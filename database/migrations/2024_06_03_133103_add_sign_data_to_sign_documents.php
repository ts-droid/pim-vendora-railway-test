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
            $table->timestamp('signed_at')->nullable()->default(null);
            $table->string('sign_ip')->nullable()->default(null);
            $table->string('sign_user_agent')->nullable()->default(null);
            $table->string('sign_mac_address')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sign_documents', function (Blueprint $table) {
            $table->dropColumn('signed_at');
            $table->dropColumn('sign_ip');
            $table->dropColumn('sign_user_agent');
            $table->dropColumn('sign_mac_address');
        });
    }
};
