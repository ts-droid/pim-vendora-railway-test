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
            $table->string('collectables')->nullable()->default(null);
            $table->string('collectables_data')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sign_documents', function (Blueprint $table) {
            $table->dropColumn('collectables');
            $table->dropColumn('collectables_data');
        });
    }
};
