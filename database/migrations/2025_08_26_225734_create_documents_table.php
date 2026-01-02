<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title')->nullable();
            $table->string('doc_id')->unique();
            $table->string('file_type');
            $table->foreignUuid('user_id')->constrained('users');
            $table->json('metadata')->nullable();
            $table->text('file_path');
            $table->integer('file_size')->default(0);
            $table->enum('status', ['pending', 'processing', 'processed', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->longText('processed_text')->nullable();
            $table->integer('total_chunks')->default(0);
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
