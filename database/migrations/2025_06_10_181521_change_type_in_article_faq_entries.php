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
        Schema::table('article_faq_entries', function (Blueprint $table) {
            $table->text('question_en')->nullable()->default(null)->change();
            $table->text('question_sv')->nullable()->default(null)->change();
            $table->text('question_lt')->nullable()->default(null)->change();
            $table->text('question_lv')->nullable()->default(null)->change();
            $table->text('question_et')->nullable()->default(null)->change();
            $table->text('question_fi')->nullable()->default(null)->change();
            $table->text('question_no')->nullable()->default(null)->change();
            $table->text('question_da')->nullable()->default(null)->change();

            $table->text('answer_en')->nullable()->default(null)->change();
            $table->text('answer_sv')->nullable()->default(null)->change();
            $table->text('answer_lt')->nullable()->default(null)->change();
            $table->text('answer_lv')->nullable()->default(null)->change();
            $table->text('answer_et')->nullable()->default(null)->change();
            $table->text('answer_fi')->nullable()->default(null)->change();
            $table->text('answer_no')->nullable()->default(null)->change();
            $table->text('answer_da')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_faq_entries', function (Blueprint $table) {
            $table->string('question_en')->change();
            $table->string('question_sv')->change();
            $table->string('question_lt')->change();
            $table->string('question_lv')->change();
            $table->string('question_et')->change();
            $table->string('question_fi')->change();
            $table->string('question_no')->change();
            $table->string('question_da')->change();

            $table->string('answer_en')->change();
            $table->string('answer_sv')->change();
            $table->string('answer_lt')->change();
            $table->string('answer_lv')->change();
            $table->string('answer_et')->change();
            $table->string('answer_fi')->change();
            $table->string('answer_no')->change();
            $table->string('answer_da')->change();
        });

    }
};
