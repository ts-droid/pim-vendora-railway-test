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
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('full_name')->nullable()->default(null)->after('id');
            $table->string('first_name')->nullable()->default(null)->after('full_name');
            $table->string('last_name')->nullable()->default(null)->after('first_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('full_name');
            $table->dropColumn('first_name');
            $table->dropColumn('last_name');
        });
    }
};
