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
        Schema::table('users', function (Blueprint $table) {
            $table->text('openai_api_key')->nullable()->after('email');
            $table->string('openai_chat_model', 100)->nullable()->after('openai_api_key');
            $table->string('openai_embedding_model', 100)->nullable()->after('openai_chat_model');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['openai_api_key', 'openai_chat_model', 'openai_embedding_model']);
        });
    }
};
