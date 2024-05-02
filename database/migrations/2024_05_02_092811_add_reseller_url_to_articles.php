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
        Schema::table('articles', function (Blueprint $table) {
            $table->string('reseller_url_sv')->nullable()->default(null);
            $table->string('reseller_url_en')->nullable()->default(null);
            $table->string('reseller_url_fi')->nullable()->default(null);
            $table->string('reseller_url_no')->nullable()->default(null);
            $table->string('reseller_url_da')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('reseller_url_sv');
            $table->dropColumn('reseller_url_en');
            $table->dropColumn('reseller_url_fi');
            $table->dropColumn('reseller_url_no');
            $table->dropColumn('reseller_url_da');
        });
    }
};
