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
        Schema::create('sign_document_recipients', function (Blueprint $table) {
            $table->id();
            $table->integer('sign_document_id');
            $table->string('email');
            $table->string('name');
            $table->string('ip');
            $table->string('user_agent');
            $table->string('access_key');
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sign_document_recipients');
    }
};
