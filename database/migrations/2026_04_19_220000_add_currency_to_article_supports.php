<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lägg till currency på article_supports. NULL = SEK (default).
 *
 * Används när is_percentage=false för att skilja ett supplier-stöd i
 * USD (ärvt från leverantörens currency) mot ett kundstöd i SEK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('article_supports', 'currency')) {
            Schema::table('article_supports', function (Blueprint $table) {
                $table->string('currency', 3)->nullable()->after('is_percentage');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('article_supports', 'currency')) {
            Schema::table('article_supports', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }
};
