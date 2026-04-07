<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('description');
            $table->string('description_clean')->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['credit', 'debit']);
            $table->string('bank')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'category_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            // pgvector column — Blueprint has no native support
            DB::statement('ALTER TABLE transactions ADD COLUMN embedding vector(1536)');

            // HNSW index for fast cosine similarity search
            DB::statement('CREATE INDEX ON transactions USING hnsw (embedding vector_cosine_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
