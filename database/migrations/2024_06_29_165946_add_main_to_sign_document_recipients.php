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
        Schema::table('sign_document_recipients', function (Blueprint $table) {
            $table->tinyInteger('is_main')->after('sign_document_id')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sign_document_recipients', function (Blueprint $table) {
            $table->dropColumn('is_main');
        });
    }
};
