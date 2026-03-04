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
        Schema::table('phrases', function (Blueprint $table) {
            $table->string('text_fr')->after('text_sv')->nullable();
            $table->string('text_is')->after('text_sv')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('phrases', function (Blueprint $table) {
            $table->dropColumn('text_fr');
            $table->dropColumn('text_is');
        });
    }
};
