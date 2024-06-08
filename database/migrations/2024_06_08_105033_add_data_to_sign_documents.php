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
            $table->integer('template_id')->default(0);
            $table->string('template_sections')->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sign_documents', function (Blueprint $table) {
            $table->dropColumn('template_id');
            $table->dropColumn('template_sections');
        });
    }
};
