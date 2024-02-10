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
        Schema::table('customers', function (Blueprint $table) {
            $table->float('credit_limit')->default(0);
            $table->float('credit_balance')->default(0);
            $table->float('vendora_rating')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('credit_limit');
            $table->dropColumn('credit_balance');
            $table->dropColumn('vendora_rating');
        });
    }
};
